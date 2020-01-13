<?php

namespace Drupal\flexible_views\Plugin\views\filter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\TableSort;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filter class which allows to show and hide columns.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("column_selector")
 */
class ColumnSelector extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public $no_operator = TRUE;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Visible Column Selector.');
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['wrap_with_details'] = ['default' => TRUE];
    $options['exposed'] = ['default' => TRUE];
    $options['expose']['contains']['label'] = ['default' => 'Column Options'];
    $options["expose"]["contains"]["identifier"] = ['default' => 'columns_selector'];

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
      '#description' => $this->t('Wrap the column selector section with a details element, so it can be collapsed.'),
      '#default_value' => $this->options['wrap_with_details'],
    ];

    // Disable the expose options - this filter needs to be exposed to work.
    $form["expose_button"]["checkbox"]["checkbox"]['#attributes']['disabled'] = 'disabled';

    // Disable the 'expose multiple' checkbox - it does not make sense.
    unset($form["expose"]["multiple"]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
  }

  /**
   * Maps query input from field_machine_name to label.
   *
   * @param array $query
   *   The inout query.
   * @param array $fields
   *   View Fields.
   *
   * @return array
   *   The mapped query.
   */
  public static function mapSelectedColumnsSubmit(array $query, array $fields) {
    $query_mapped = [];

    foreach ($query as $field_name) {
      if (isset($fields[$field_name])) {
        $query_mapped[$field_name] = $fields[$field_name]->options['label'];
      }
    }

    return $query_mapped;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);

    $form['#attached']['library'][] = 'flexible_views/column_selector';

    $fields = $form_state->getStorage()['view']->style_plugin->options['info'];
    $fields_info = $form_state->getStorage()['view']->field;
    $query = TableSort::getQueryParameters(\Drupal::request());

    // Get the selected columns from the query of from the session storage.
    $display_id = ($this->view->display_handler->isDefaulted('filters')) ? 'default' : $this->view->current_display;
    $query_selected_columns = [];
    if (isset($query['selected_columns_submit_order']) && !empty($query['selected_columns_submit_order'])) {
      $query_selected_columns = $this::mapSelectedColumnsSubmit(Json::decode($query['selected_columns_submit_order']), $fields_info);
    }
    elseif (isset($_SESSION['views'][$this->view->storage->id()][$display_id]['selected_columns_submit_order']) && !empty($_SESSION['views'][$this->view->storage->id()][$display_id]['selected_columns_submit_order'])) {
      $query_selected_columns = $this::mapSelectedColumnsSubmit(Json::decode($_SESSION['views'][$this->view->storage->id()][$display_id]['selected_columns_submit_order']), $fields_info);
    }

    // If we have a query['selected_columns_submit_order'], use this for the
    // columns, otherwise render the default_visible columns and populate the
    // form elements.
    $options = [];
    $options_default_visible = [];

    foreach ($fields_info as $field_name => $field_info) {
      // We dont want to process the node_bulk_form and the operations.
      if (!in_array($field_name, [
        'operations',
        'node_bulk_form',
        'views_bulk_operations_bulk_form',
      ])) {
        if (!$field_info->options['exclude']) {
          $options[$field_name] = $field_info->options['label'];
        }
        // Defaults.
        if ($fields[$field_name]['default_visible']) {
          $options_default_visible[$field_name] = $field_info->options['label'];
        }
      }
    }

    $form['flexible_tables_fieldset'] = [
      '#type' => $this->options['wrap_with_details'] ? 'details' : 'container',
      '#open' => FALSE,
      '#title' => $this->options['expose']['label'],
      '#attributes' => [
        'style' => 'float: none;clear: both;',
      ],
    ];

    $form['flexible_tables_fieldset']['available_columns'] = [
      '#type' => 'select',
      '#title' => t('Available Columns'),
      '#options' => count($query_selected_columns) > 0 ? array_diff($options, $query_selected_columns) : array_diff($options, $options_default_visible),
      '#size' => count($options),
      '#multiple' => TRUE,
      '#attributes' => [
        'id' => 'flexible-table-available-columns',
      ],
      '#prefix' => $this->options['wrap_with_details'] ? '' : '<div class="details-wrapper fake-detail">',
      '#chosen' => FALSE,
    ];

    $move_left_right_buttons = <<<EOT
<div class="form-item move-buttons">
  <div class="move-right">→</div>
  <div class="move-left">←</div>
</div>
EOT;

    $form['flexible_tables_fieldset']['move_left_right_buttons']['#markup'] = $move_left_right_buttons;

    $form['flexible_tables_fieldset']['selected_columns'] = [
      '#type' => 'select',
      '#title' => t('Selected Columns'),
      '#options' => count($query_selected_columns) > 0 ? $query_selected_columns : $options_default_visible,
      '#size' => count($options),
      '#multiple' => TRUE,
      '#attributes' => [
        'id' => 'flexible-table-selected-columns',
      ],
      '#chosen' => FALSE,
    ];

    $move_top_down_buttons = <<<EOT
<div class="form-item move-buttons">
  <div class="move-top">↑</div>
  <div class="move-down">↓</div>
</div>
EOT;

    $form['flexible_tables_fieldset']['move_top_down_buttons']['#markup'] = $move_top_down_buttons;

    $form['flexible_tables_fieldset']['selected_columns_submit_order'] = [
      '#type' => 'textfield',
      '#default_value' => FALSE,
      '#size' => 60,
      '#maxlength' => 524288,
      '#required' => FALSE,
      '#attributes' => [
        'id' => 'flexible-table-selected-columns-submit-order',
      ],
      '#suffix' => $this->options['wrap_with_details'] ? '' : '</div>',
    ];

    // Add our submit routine to process.
    $form['#validate'][] = [$this, 'exposedFormValidate'];
  }

  /**
   * Reset the selected_columns to an empty array, we dont need this.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function exposedFormValidate(array &$form, FormStateInterface $form_state) {
    // Clear the values on available_columns and selected_columns, we dont need
    // these, and it's also confusing for the user.
    $input = $form_state->getUserInput();

    foreach ($input as $key => $item) {
      if (in_array($key, ['available_columns', 'selected_columns'])) {
        unset($input[$key]);
      }
    }

    $form_state->setUserInput($input);

    // Clear the validation error on the selected_columns field. We supply an
    // empty array [] as options, but the user can select something and this
    // results in a validation error.
    if ($form_state->getError($form['flexible_tables_fieldset']['selected_columns'])) {
      $form_errors = $form_state->getErrors();

      // Clear the form errors.
      $form_state->clearErrors();

      // Remove the field_mobile form error.
      unset($form_errors['selected_columns']);

      // Now loop through and re-apply the remaining form error messages.
      foreach ($form_errors as $name => $error_message) {
        $form_state->setErrorByName($name, $error_message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function storeExposedInput($input, $status) {
    if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return TRUE;
    }

    if (empty($this->options['expose']['remember'])) {
      return;
    }

    // Check if we store exposed value for current user.
    $user = \Drupal::currentUser();
    $allowed_rids = empty($this->options['expose']['remember_roles']) ? [] : array_filter($this->options['expose']['remember_roles']);
    $intersect_rids = array_intersect(array_keys($allowed_rids), $user->getRoles());
    if (empty($intersect_rids)) {
      return;
    }

    // Figure out which display id is responsible for the filters, so we
    // know where to look for session stored values.
    $display_id = ($this->view->display_handler->isDefaulted('filters')) ? 'default' : $this->view->current_display;

    // False means that we got a setting that means to recurse ourselves,
    // so we should erase whatever happened to be there.
    if (!$status && isset($_SESSION['views'][$this->view->storage->id()][$display_id])) {
      $session = &$_SESSION['views'][$this->view->storage->id()][$display_id];

      if (isset($session[$this->options['expose']['identifier']])) {
        unset($session[$this->options['expose']['identifier']]);
        // We use two form_elements different from the identifier.
        unset($session['selected_columns_submit_order']);
      }
    }

    if ($status) {
      if (!isset($_SESSION['views'][$this->view->storage->id()][$display_id])) {
        $_SESSION['views'][$this->view->storage->id()][$display_id] = [];
      }

      $session = &$_SESSION['views'][$this->view->storage->id()][$display_id];

      if (isset($input[$this->options['expose']['identifier']])) {
        $session[$this->options['expose']['identifier']] = $input[$this->options['expose']['identifier']];
      }

      $session['selected_columns_submit_order'] = $input['selected_columns_submit_order'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function canGroup() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    return TRUE;
  }

}
