<?php

namespace Drupal\flexible_views\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\TableSort;
use Drupal\views\Plugin\views\exposed_form\ExposedFormPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Class ManualSelection.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @ViewsExposedForm(
 *   id = "manual_selection",
 *   title = @Translation("Manual selection"),
 *   help = @Translation("Manual selection form")
 * )
 */
class ManualSelection extends ExposedFormPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['wrap_with_details'] = ['default' => FALSE];
    $options['details_label'] = ['default' => $this->t('Filter')];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['wrap_with_details'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap with details element'),
      '#description' => $this->t('Wrap the whole exposed filters section with a details element, so it can be collapsed.'),
      '#default_value' => $this->options['wrap_with_details'],
    ];

    $form['details_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Details wrapper label'),
      '#description' => $this->t('Text to display in the details wrapper element of the exposed form.'),
      '#default_value' => $this->options['details_label'],
      '#required' => FALSE,
      '#states' => [
        'invisible' => [
          'input[name="exposed_form_options[wrap_with_details]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Get all filters of the view and add the possibility to make them visible
    // by default.
    $filters = $this->view->display_handler->getHandlers('filter');
    $form['filter_always_visible'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select the filters which should be always visible'),
      '#default_value' => $this->options['filter_always_visible'],
    ];

    /* @var \Drupal\views\Plugin\views\HandlerBase $filter */
    foreach ($filters as $label => $filter) {
      if ($filter->isExposed() && $filter->realField !== 'column_selector') {
        $form['filter_always_visible']['#options'][$label] = $filter->options["expose"]["label"];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $view = $this->view;
    $exposed_data = isset($view->exposed_data) ? $view->exposed_data : [];
    $sort_by = isset($exposed_data['sort_by']) ? $exposed_data['sort_by'] : NULL;
    if (!empty($sort_by)) {
      // Make sure the original order of sorts is preserved
      // (e.g. a sticky sort is often first)
      if (isset($view->sort[$sort_by])) {
        $view->query->orderby = [];
        foreach ($view->sort as $key => $sort) {
          if (!$sort->isExposed()) {
            $sort->query();
          }
          elseif ($key == $sort_by) {
            if (isset($exposed_data['sort_order']) && in_array($exposed_data['sort_order'], ['ASC', 'DESC'])) {
              $sort->options['order'] = $exposed_data['sort_order'];
            }
            $sort->setRelationship();
            $sort->query();
          }
        }
      }
    }
  }

  /**
   * Sorts checkboxes and always visible filters for each exposed filter.
   *
   * @todo Add Unit test.
   *
   * @param array $form
   *   The form array.
   * @param array $filter_always_visible
   *   The exposed filters which are always visible.
   *
   * @return array
   *   The form array.
   */
  public static function sortCheckboxes(array $form, array $filter_always_visible = []) {
    $checkboxes = array_filter(array_keys($form), function ($element) {
      return strpos($element, '_check_deactivate');
    });

    foreach ($checkboxes as $checkbox) {
      $filter_name = str_replace('_check_deactivate', '', $checkbox);

      if (isset($form['#info']['filter-' . $filter_name]['operator']) &&$form['#info']['filter-' . $filter_name]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter_name]['operator']])) {
        $weight = $form[$form['#info']['filter-' . $filter_name]['operator']]['#weight'];
      }
      else {
        $weight = $form[$filter_name]['#weight'];
      }

      $form[$checkbox]['#weight'] = floatval($weight) - 0.001;
    }

    // Sort always visible filters.
    foreach (array_keys(array_filter($filter_always_visible)) as $filter) {
      $form[$filter]['#weight'] = $form[$filter]['#weight'] - 100;

      if ($form['#info']['filter-' . $filter]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter]['operator']])) {
        $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] = $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] - 100;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function renderExposedForm($block = FALSE) {
    $form = parent::renderExposedForm($block);

    // Set the correct weight for the deactivate_checkboxes.
    $filter_always_visible = isset($this->options['filter_always_visible']) ? $this->options['filter_always_visible'] : [];
    $form = self::sortCheckboxes($form, $filter_always_visible);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);

    $form['#attached']['library'][] = 'flexible_views/manual_selection';
    $form['#attributes']['class'][] = 'manual-selection-form';

    $filters = array_keys($form['#info']);
    // TODO: Dependency injection.
    $query = TableSort::getQueryParameters(\Drupal::request());
    $filter_always_visible = isset($this->options['filter_always_visible']) ? array_filter($this->options['filter_always_visible']) : [];
    $manual_select_filter_options = [];

    // We add a hidden field to the form to store the selected filters.
    $form['selected_filters'] = [
      '#type' => 'hidden',
      '#size' => 1024,
      '#maxlength' => 1024,
    ];

    // Remove the column_selector filter from the elements we want to process.
    // Unset the column selector if it is in the filters array.
    if ($column_selector_index = array_search('filter-column_selector', $filters)) {
      unset($filters[$column_selector_index]);
    }
    // The above query does not trigger if the column selector filter is
    // the first filter in the array (or if it is the only exposed filter).
    elseif ($filters[0] === 'filter-column_selector') {
      unset($filters[0]);
    }

    // Add the details element if the wrap_with_details option is enabled.
    if ($this->options['wrap_with_details']) {
      $form['manual_selection_filter_details'] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->options['details_label'],
        '#weight' => -80,
        '#attributes' => [
          'style' => 'float: none;clear: both;',
          'class' => ['flexible-views-manual-selection-details'],
        ],
      ];
    }

    // Deactivate the filters on default.
    foreach ($filters as $filter) {
      $filter_name = str_replace('filter-', '', $filter);

      if (!in_array($filter_name, $filter_always_visible, TRUE)) {
        // Wrap each pair of filters with html div.
        $form[$filter_name]['#suffix'] = '</div>';
        $form[$filter_name]['#chosen'] = FALSE;

        // Add checkboxes to deactivate filter.
        $form[$filter_name . '_check_deactivate'] = [
          '#type' => 'checkbox',
          '#title' => $form['#info'][$filter]['label'],
          '#checked' => array_key_exists($filter_name, $query) ? TRUE : FALSE,
          '#value' => array_key_exists($filter_name, $query) ? TRUE : FALSE,
          '#prefix' => array_key_exists($filter_name, $query) ? "<div class='filter-wrap active'>" : "<div class='filter-wrap'>",
          '#states' => [
            'visible' => [
              ":input[name='{$filter_name}_check_deactivate']" => ['checked' => TRUE],
            ],
          ],
        ];

        // Hide the labels and hide the filters if the checkbox is set to false.
        if (isset($form['#info'][$filter]['operator']) && $form['#info'][$filter]['operator'] !== "" && isset($form[$form['#info'][$filter]['operator']])) {
          $form[$form['#info'][$filter]['operator']]['#title_display'] = 'invisible';
          $form[$form['#info'][$filter]['operator']]['#states']['enabled'] = [
            ":input[name='{$filter_name}_check_deactivate']" => ['checked' => TRUE],
          ];
          $form[$form['#info'][$filter]['operator']]['#chosen'] = FALSE;
        }

        $form[$filter_name]['#title_display'] = 'invisible';

        if (isset($form[$filter_name]['value'])) {
          $form[$filter_name]['value']['#states']['enabled'][] = [
            ":input[name='{$filter_name}_check_deactivate']" => ['checked' => TRUE],
          ];
        }
        else {
          $form[$filter_name]['#states']['enabled'][] = [
            ":input[name='{$filter_name}_check_deactivate']" => ['checked' => TRUE],
          ];
        }

        // If there is an min/max operator, hide the label from the min element.
        if (isset($form[$filter_name]['min'])) {
          $form[$filter_name]['min']['#title_display'] = 'invisible';
          $form[$filter_name]['min']['#states']['enabled'][] = [
            ":input[name='{$filter_name}_check_deactivate']" => ['checked' => TRUE],
          ];
        }
        if (isset($form[$filter_name]['max'])) {
          $form[$filter_name]['max']['#attributes']['class'][] = 'label-before';
          $form[$filter_name]['max']['#states']['enabled'][] = [
            ":input[name='{$filter_name}_check_deactivate']" => ['checked' => TRUE],
          ];
        }

        $manual_select_filter_options[$filter_name] = $form['#info'][$filter]['label'];

        if ($this->options['wrap_with_details']) {
          $form['manual_selection_filter_details'][$filter_name . '_check_deactivate'] = $form[$filter_name . '_check_deactivate'];
          unset($form[$filter_name . '_check_deactivate']);

          if ($form['#info'][$filter]['operator'] !== "" && isset($form[$form['#info'][$filter]['operator']])) {
            $form['manual_selection_filter_details'][$form['#info'][$filter]['operator']] = $form[$form['#info'][$filter]['operator']];
            unset($form[$form['#info'][$filter]['operator']]);
          }

          $form['manual_selection_filter_details'][$filter_name] = $form[$filter_name];
          unset($form[$filter_name]);
        }
      }
      else {
        // Wrap the always visible filters with a wrap.
        if ($form['#info'][$filter]['operator'] !== "" && isset($form[$form['#info'][$filter]['operator']])) {
          $form[$form['#info'][$filter]['operator']]['#title_display'] = 'invisible';
          $form[$form['#info'][$filter]['operator']]['#prefix'] = "<div class='filter-wrap always-visible'><span class='label'>{$form['#info'][$filter]['label']}</span>";
          // Disable chosen.
          $form[$form['#info'][$filter]['operator']]['#chosen'] = FALSE;
        }
        else {
          $form[$filter_name]['#prefix'] = "<div class='filter-wrap always-visible'><span class='label'>{$form['#info'][$filter]['label']}</span>";
        }

        // Label handling.
        if (isset($form[$filter_name]['min'])) {
          $form[$filter_name]['min']['#title_display'] = 'invisible';
        }
        $form[$filter_name]['#title_display'] = 'invisible';

        // Disable chosen.
        $form[$filter_name]['#chosen'] = FALSE;
        $form[$filter_name]['#suffix'] = '</div>';
      }
    }

    // Add manual filter selection.
    $form['manual_select_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Select filter'),
      '#options' => $manual_select_filter_options,
      '#empty_value' => '',
      '#empty_option' => $this->t('- Select a filter -'),
      '#default_value' => '',
      '#weight' => -99,
      '#chosen' => FALSE,
    ];

    if (count($manual_select_filter_options) === 0) {
      $form['manual_select_filter']['#prefix'] = '<div class="hidden">';
      $form['manual_select_filter']['#suffix'] = '</div>';
    }

    if ($this->options['wrap_with_details']) {
      $form['manual_selection_filter_details']['manual_select_filter'] = $form['manual_select_filter'];
      unset($form['manual_select_filter']);
    }
  }

}
