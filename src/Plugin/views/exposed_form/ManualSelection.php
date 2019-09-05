<?php

namespace Drupal\flexible_views\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\TableSort;
use Drupal\views\Plugin\views\exposed_form\ExposedFormPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

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
   * Temporary store to save ajax submitted values.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PrivateTempStoreFactory $temp_store_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->tempStore = $temp_store_factory->get('flexible_views');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.private_tempstore')
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
    // TODO: Exclude column_selector.
    foreach ($filters as $label => $filter) {
      if ($filter->isExposed()) {
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
   * @param array $form
   *   The form array.
   * @param array $filter_always_visible
   *   The exposed filters which are always visible.
   *
   * @return array
   *   The form array.
   */
  public static function sortCheckboxes(array &$form, array $filter_always_visible = []) {
    $checkboxes = array_filter(array_keys($form), function ($element) {
      return strpos($element, '_check_deactivate');
    });

    foreach ($checkboxes as $checkbox) {
      $filter_name = str_replace('_check_deactivate', '', $checkbox);

      if ($form['#info']['filter-' . $filter_name]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter_name]['operator']])) {
        $weight = $form[$form['#info']['filter-' . $filter_name]['operator']]['#weight'];
      }
      else {
        $weight = $form[$filter_name]['#weight'];
      }

      $form[$checkbox]['#weight'] = floatval($weight) - 0.001;
    }

    // Sort always visible filters.
    foreach (array_filter($filter_always_visible) as $filter) {
      $form[$filter]['#weight'] = $form[$filter]['#weight'] - 100;

      if ($form['#info']['filter-' . $filter]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter]['operator']])) {
        $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] = $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] - 100;
      }
    }

    return $form;
  }

  /**
   * Generate the tempStorage ID to use.
   *
   * @return string
   *   The tempStorage ID.
   */
  public function setTempStorageId() {
    return 'selected_filters_' . $this->view->id() . '_' . $this->view->current_display;
  }

  /**
   * {@inheritdoc}
   */
  public function renderExposedForm($block = FALSE) {
    $form = parent::renderExposedForm($block);

    $form['#attached']['library'][] = 'flexible_views/manual_selection';
    $form['#attributes']['class'][] = 'manual-selection-form';

    // Set the correct weight for the deactivate_checkboxes.
    $filter_always_visible = $this->options['filter_always_visible'] ? $this->options['filter_always_visible'] : [];
    $this->sortCheckboxes($form, $filter_always_visible);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);

    $filters = array_keys($form['#info']);
    $query = TableSort::getQueryParameters(\Drupal::request());
    $input = $form_state->getUserInput();
    $filter_always_visible = array_filter($this->options['filter_always_visible']);
    $manual_select_filter_options = [];

    // Add always visible options hidden to the view, because we cant access
    // them in static callbacks.
    // TODO: Remove when static callback problem is solved.
    $form['filter_always_visible'] = [
      '#type' => 'hidden',
      '#value' => $filter_always_visible,
    ];


    $selected_filters_tempstore = $this->tempStore->get($this->setTempStorageId()) ? $this->tempStore->get($this->setTempStorageId()) : [];

    // Show rest button if we have something in the tempstore.
    if (count($selected_filters_tempstore) > 0) {
      $form['actions']['reset']['#access'] = TRUE;
    }

    // Remove the column_selector filter from the elements we want to process.
    if ($column_selector_index = array_search('filter-column_selector', $filters)) {
      unset($filters[$column_selector_index]);
    }

    // Deactivate the filters on default.
    foreach ($filters as $filter) {
      $filter_name = str_replace('filter-', '', $filter);

      if (!in_array($filter_name, $filter_always_visible, TRUE)) {
        // Wrap each pair of filters with html div.
        $form[$filter_name]['#suffix'] = '</div>';

        // Add checkboxes to deactivate filter.
        $form[$filter_name . '_check_deactivate'] = [
          '#type' => 'checkbox',
          '#title' => $form['#info'][$filter]['label'],
          '#checked' => array_key_exists($filter_name, $query) || in_array($filter_name, $selected_filters_tempstore) ? TRUE : FALSE,
          '#access' => array_key_exists($filter_name, $query) || in_array($filter_name, $selected_filters_tempstore) ? TRUE : FALSE,
          '#prefix' => "<div class='filter-wrap'>",
          '#ajax' => [
            'callback' => 'Drupal\flexible_views\Plugin\views\exposed_form\ManualSelection::deactivateFilterCallback',
            'event' => 'change',
            'wrapper' => $form['#id'],
            'effect' => 'fade',
            'method' => 'replace',
          ],
        ];

        // Hide the labels and hide the filters if the checkbox is set to false.
        if ($form['#info'][$filter]['operator'] !== "" && isset($form[$form['#info'][$filter]['operator']])) {
          $form[$form['#info'][$filter]['operator']]['#title_display'] = 'invisible';
          $form[$form['#info'][$filter]['operator']]['#states']['visible'][] = [
            ":input[name='{$filter_name}_check_deactivate']" => [
              'checked' => TRUE,
            ],
          ];
        }

        $form[$filter_name]['#title_display'] = 'invisible';
        $form[$filter_name]['#states']['visible'][] = [
          ":input[name='{$filter_name}_check_deactivate']" => [
            'checked' => TRUE,
          ],
        ];

        // If there is an min/max operator, hide the label from the min element.
        if (isset($form[$filter_name]['min'])) {
          $form[$filter_name]['min']['#title_display'] = 'invisible';
        }
        if (isset($form[$filter_name]['max'])) {
          $form[$filter_name]['max']['#attributes']['class'][] = 'label-before';
        }

        // Hide the filters if they are not active.
        if ((!array_key_exists($filter_name, $query) && !in_array($filter_name, $selected_filters_tempstore)) || (array_key_exists($filter_name, $input) && (!array_key_exists($form['#info'][$filter]['operator'], $input) && $form['#info'][$filter]['operator'] !== ""))) {
          // Exposed filter.
          $form[$filter_name]['#access'] = FALSE;

          // Exposed operator.
          if ($form['#info'][$filter]['operator'] !== '' && isset($form[$form['#info'][$filter]['operator']])) {
            $form[$form['#info'][$filter]['operator']]['#access'] = FALSE;
          }

          // Checkbox.
          $form[$filter_name . '_check_deactivate']['#checked'] = FALSE;
          $form[$filter_name . '_check_deactivate']['#access'] = FALSE;

          $manual_select_filter_options[$filter_name] = $form['#info'][$filter]['label'];
        }

        if ($this->options['wrap_with_details']) {
          $form['manual_selection_filter_details'][$filter_name] = $form[$filter_name];
          unset($form[$filter_name]);

          $form['manual_selection_filter_details'][$form['#info'][$filter]['operator']] = $form[$form['#info'][$filter]['operator']];
          unset($form[$form['#info'][$filter]['operator']]);
        }
      }
    }

    // TODO: If this is enabled, we getting the bug:
    // @see https://www.drupal.org/project/drupal/issues/2842525
    if ($this->options['wrap_with_details']) {
      $form['manual_selection_filter_details'] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->options['details_label'],
        '#weight' => -200,
        '#attributes' => [
          'style' => 'float: none;clear: both;',
        ],
      ];
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
      '#attributes' => [
        'class' => count($selected_filters_tempstore) > 0 ? ['active'] : [''],
      ],
      '#ajax' => [
        // TODO: use OOP, not static context.
        'callback' => 'Drupal\flexible_views\Plugin\views\exposed_form\ManualSelection::manualSelectFilterChangeCallback',
        'event' => 'change',
        'wrapper' => $form['#id'],
        'effect' => 'fade',
        'method' => 'replace',
      ],
    ];

    if ($this->options['wrap_with_details']) {
      $form['manual_selection_filter_details']['manual_select_filter'] = $form['manual_select_filter'];
      unset($form['manual_select_filter']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetForm(&$form, FormStateInterface $form_state) {
    parent::resetForm($form, $form_state);

    $selected_filters_tempstore = $this->tempStore->get($this->setTempStorageId());

    if (count($selected_filters_tempstore) > 0) {
      $this->tempStore->delete($this->setTempStorageId());
    }
  }

  /**
   * Ajax callback function for changing active filters.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state.
   *
   * @return mixed
   *   The Form render array.
   *
   * @todo: Using $this when not in object context...
   * @see https://www.drupal.org/project/drupal/issues/2842525
   * @see https://www.drupal.org/docs/8/api/javascript-api/ajax-forms
   */
  public function manualSelectFilterChangeCallback(array &$form, FormStateInterface $form_state) {
    $selected_filter = $form_state->getValue('manual_select_filter');

    // TODO: Somehow we need to re-attach the form library and class?
    $form['#attached']['library'][] = 'flexible_views/manual_selection';
    $form['#attributes']['class'][] = 'manual-selection-form';

    // Remove the selected filter from the drop down.
    $manual_select_options = $form['manual_select_filter']['#options'];
    unset($manual_select_options[$selected_filter]);
    $form['manual_select_filter']['#options'] = $manual_select_options;

    // Enable the selected filter.
    $form[$selected_filter]['#access'] = TRUE;
    if (isset($form[$selected_filter]['value'])) {
      $form[$selected_filter]['value']['#access'] = TRUE;
    }

    // Enable the selected operator.
    if ($form['#info']['filter-' . $selected_filter]['operator'] !== '' && isset($form[$form['#info']['filter-' . $selected_filter]['operator']])) {
      $form[$form['#info']['filter-' . $selected_filter]['operator']]['#access'] = TRUE;
      $form[$form['#info']['filter-' . $selected_filter]['operator']]['#default_value'] = '';
    }

    // Enable (if available) the min/max filters.
    if (isset($form[$selected_filter]['min'])) {
      $form[$selected_filter]['min']['#access'] = TRUE;
    }
    if (isset($form[$selected_filter]['max'])) {
      $form[$selected_filter]['max']['#access'] = TRUE;
    }

    // TODO: Same as in sortCheckboxes() but we can't call it...
    // @start $this->sortCheckboxes($form);
    $checkboxes = array_filter(array_keys($form), function ($element) {
      return strpos($element, '_check_deactivate');
    });

    foreach ($checkboxes as $checkbox) {
      $filter_name = str_replace('_check_deactivate', '', $checkbox);

      if ($form['#info']['filter-' . $filter_name]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter_name]['operator']])) {
        $weight = $form[$form['#info']['filter-' . $filter_name]['operator']]['#weight'];
      }
      else {
        $weight = $form[$filter_name]['#weight'];
      }

      $form[$checkbox]['#weight'] = floatval($weight) - 0.001;
    }

    // Sort always visible filters.
    $filter_always_visible = $form_state->getValue('filter_always_visible');
    foreach ($filter_always_visible as $filter) {
      $form[$filter]['#weight'] = $form[$filter]['#weight'] - 100;

      if ($form['#info']['filter-' . $filter]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter]['operator']])) {
        $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] = $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] - 100;
      }
    }
    // @end $this->sortCheckboxes($form);

    // Enable the checkbox.
    $form[$selected_filter . '_check_deactivate']['#access'] = TRUE;
    $form[$selected_filter . '_check_deactivate']['#checked'] = TRUE;

    // TODO: Use service injection & document.
    // Save the selected filter to the tempstore.
    $view_executable = $form_state->getStorage()["view"];
    $temp_storage_id = 'selected_filters_' . $view_executable->id() . '_' . $view_executable->current_display;
    $tempstore = \Drupal::service('user.private_tempstore')->get('flexible_views');

    $selected_filters = $tempstore->get($temp_storage_id) ? $tempstore->get($temp_storage_id) : [];
    if (!in_array($selected_filter, $selected_filters)) {
      $selected_filters[] = $selected_filter;
      $tempstore->set($temp_storage_id, $selected_filters);
    }

    return $form;
  }

  /**
   * Activate / Deactivate a filter via a checkbox.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The manipulated form array.
   */
  public function deactivateFilterCallback(array &$form, FormStateInterface $form_state) {
    // Get the trigger name (filter) and value.
    $trigger = $form_state->getTriggeringElement();

    $trigger_name = $trigger['#name'];
    $trigger_value = $form_state->getUserInput()[$trigger_name];

    $form[$trigger_name]['#suffix'] = $trigger_value ? '' : '</div>';
    if ($trigger_value) {
      $form[$trigger_name]['#access'] = TRUE;
      $form[$trigger_name]['#checked'] = TRUE;
    }

    // TODO: Somehow we need to re-attach the form library and class?
    $form['#attached']['library'][] = 'flexible_views/manual_selection';
    $form['#attributes']['class'][] = 'manual-selection-form';

    $form_element = str_replace('_check_deactivate', '', $trigger_name);

    // Enable / Disable the filter which belongs to the checkbox.
    $form[$form_element]['#access'] = $trigger_value ? TRUE : FALSE;
    //$form[$form_element]['#default_value'] = $trigger_value ? '' : FALSE;
    $form[$form_element]['#suffix'] = $trigger_value ? '</div>' : '';

    if ($form['#info']['filter-' . $form_element]['operator']) {
      $form[$form['#info']['filter-' . $form_element]['operator']]['#access'] = $trigger_value ? TRUE : FALSE;
      //$form[$form['#info']['filter-' . $form_element]['operator']]['#default_value'] = $trigger_value ? TRUE : FALSE;
    }

    // Remove the values from the query if the filter was deactivated.
    $form_state->unsetValue($form_element);
    if ($form['#info']['filter-' . $form_element]['operator']) {
      $form_state->unsetValue($form['#info']['filter-' . $form_element]['operator']);
    }

    // TODO: Same as in sortCheckboxes() but we can't call it...
    // @start $this->sortCheckboxes($form);
    $checkboxes = array_filter(array_keys($form), function ($element) {
      return strpos($element, '_check_deactivate');
    });

    foreach ($checkboxes as $checkbox) {
      $filter_name = str_replace('_check_deactivate', '', $checkbox);

      if ($form['#info']['filter-' . $filter_name]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter_name]['operator']])) {
        $weight = $form[$form['#info']['filter-' . $filter_name]['operator']]['#weight'];
      }
      else {
        $weight = $form[$filter_name]['#weight'];
      }

      $form[$checkbox]['#weight'] = floatval($weight) - 0.001;
    }

    // Sort always visible filters.
    $filter_always_visible = $form_state->getValue('filter_always_visible');
    foreach ($filter_always_visible as $filter) {
      $form[$filter]['#weight'] = $form[$filter]['#weight'] - 100;

      if ($form['#info']['filter-' . $filter]['operator'] !== "" && isset($form[$form['#info']['filter-' . $filter]['operator']])) {
        $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] = $form[$form['#info']['filter-' . $filter]['operator']]['#weight'] - 100;
      }
    }
    // @end $this->sortCheckboxes($form);

    // TODO: Use service injection.
    // Add or remove the filter from the tempstore.
    // TODO: We can't access this...
    $view_executable = $form_state->getStorage()["view"];
    $temp_storage_id = 'selected_filters_' . $view_executable->id() . '_' . $view_executable->current_display;
    $tempstore = \Drupal::service('user.private_tempstore')->get('flexible_views');
    $selected_filters = $tempstore->get($temp_storage_id);

    if ($trigger_value) {
      $selected_filters[] = $form_element;
      $tempstore->set($temp_storage_id, $selected_filters);
    }
    else {
      if (in_array($form_element, $selected_filters)) {
        unset($selected_filters[array_search($form_element, $selected_filters)]);
        $tempstore->set($temp_storage_id, $selected_filters);
      }
    }

    $form_state->setRebuild();

    return $form;
  }

}
