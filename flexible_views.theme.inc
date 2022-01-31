<?php

/**
 * @file
 * Preprocessors and theme functions for the flexible views tables plugin..
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Utility\TableSort;
use Drupal\Component\Serialization\Json;

/**
 * Prepares variables for style plugin table templates.
 *
 * Default template: views-ui-style-plugin-table.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - form: A render element representing the form.
 */
function template_preprocess_flexible_views_style_plugin_flexible_table(array &$variables) {
  $form = $variables['form'];

  $header = [
    t('Field'),
    t('Column'),
    t('Align'),
    t('Separator'),
    [
      'data' => t('Visible on default'),
      'align' => 'center',
    ],
    [
      'data' => t('Sortable'),
      'align' => 'center',
    ],
    [
      'data' => t('Default order'),
      'align' => 'center',
    ],
    [
      'data' => t('Default sort'),
      'align' => 'center',
    ],
    [
      'data' => t('Hide empty column'),
      'align' => 'center',
    ],
    [
      'data' => t('Responsive'),
      'align' => 'center',
    ],
  ];
  $rows = [];
  foreach (Element::children($form['columns']) as $id) {
    $row = [];
    $row[]['data'] = $form['info'][$id]['name'];
    $row[]['data'] = $form['columns'][$id];
    $row[]['data'] = $form['info'][$id]['align'];
    $row[]['data'] = $form['info'][$id]['separator'];
    $row[]['data'] = $form['info'][$id]['default_visible'];

    if (!empty($form['info'][$id]['sortable'])) {
      $row[] = [
        'data' => $form['info'][$id]['sortable'],
        'align' => 'center',
      ];
      $row[] = [
        'data' => $form['info'][$id]['default_sort_order'],
        'align' => 'center',
      ];
      $row[] = [
        'data' => $form['default'][$id],
        'align' => 'center',
      ];
    }
    else {
      $row[] = '';
      $row[] = '';
      $row[] = '';
    }
    $row[] = [
      'data' => $form['info'][$id]['empty_column'],
      'align' => 'center',
    ];
    $row[] = [
      'data' => $form['info'][$id]['responsive'],
      'align' => 'center',
    ];
    $rows[] = $row;
  }

  // Add the special 'None' row.
  $rows[] = [
    ['data' => t('None'), 'colspan' => 6],
    ['align' => 'center', 'data' => $form['default'][-1]], ['colspan' => 2],
  ];

  // Unset elements from the form array that are used to build the table so that
  // they are not rendered twice.
  unset($form['default']);
  unset($form['info']);
  unset($form['columns']);

  $variables['table'] = [
    '#type' => 'table',
    '#theme' => 'table__views_ui_style_plugin_table',
    '#header' => $header,
    '#rows' => $rows,
  ];
  $variables['form'] = $form;
}

/**
 * Prepares variables for views table templates.
 *
 * Default template: views-view-table.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - view: A ViewExecutable object.
 *   - rows: The raw row data.
 */
function template_preprocess_views_view_flexible_table(array &$variables) {
  $view = $variables['view'];

  // We need the raw data for this grouping, which is passed in
  // as $variables['rows'].
  // However, the template also needs to use for the rendered fields.  We
  // therefore swap the raw data out to a new variable and reset
  // $variables['rows'] so that it can get rebuilt.
  // Store rows so that they may be used by further preprocess functions.
  $result = $variables['result'] = $variables['rows'];
  $variables['rows'] = [];
  $variables['header'] = [];

  $options = $view->style_plugin->options;
  $handler = $view->style_plugin;

  $fields = &$view->field;
  $columns = $handler->sanitizeColumns($options['columns'], $fields);

  $active = !empty($handler->active) ? $handler->active : '';
  $order = !empty($handler->order) ? $handler->order : 'asc';

  // A boolean variable which stores whether the table has a responsive class.
  $responsive = FALSE;

  // For the actual site we want to not render full URLs, because this would
  // make pagers cacheable per URL, which is problematic in blocks, for example.
  // For the actual live preview though the javascript relies on properly
  // working URLs.
  $route_name = !empty($view->live_preview) ? '<current>' : '<none>';

  $query = TableSort::getQueryParameters(\Drupal::request());
  if (isset($view->exposed_raw_input)) {
    $query += $view->exposed_raw_input;
  }

  $display_id = ($view->display_handler->isDefaulted('filters')) ? 'default' : $view->current_display;

  // Get the column order.
  if (isset($query['selected_columns_submit_order']) && !empty($query['selected_columns_submit_order'])) {
    $column_settings = $query['selected_columns_submit_order'];
  }
  elseif (isset($_SESSION['views'][$view->storage->id()][$display_id]['selected_columns_submit_order']) && !empty($_SESSION['views'][$view->storage->id()][$display_id]['selected_columns_submit_order'])) {
    $column_settings = $_SESSION['views'][$view->storage->id()][$display_id]['selected_columns_submit_order'];
  }

  if (isset($column_settings)) {
    $column_order = Json::decode($column_settings);

    // Add the columns which are always visible.
    $columns_reverse = array_reverse($columns, TRUE);
    foreach ($columns_reverse as $index => $column) {
      if (!in_array($column, $column_order) && isset($options['info'][$column]) && $options['info'][$column]['default_visible']) {
        $original_position = $fields[$column]->position;

        if ($original_position >= count($column_order)) {
          array_push($column_order, $column);
        }
        else {
          array_unshift($column_order, $column);
        }
      }
    }

    uasort($columns, function ($a, $b) use ($column_order) {
      if (array_search($a, $column_order) > array_search($b, $column_order)) {
        return 1;
      }
      if (array_search($a, $column_order) < array_search($b, $column_order)) {
        return -1;
      }

      return 0;
    });
  }

  // A boolean to store whether the table's header has any labels.
  $has_header_labels = FALSE;
  foreach ($columns as $field => $column) {
    // Create a second variable so we can easily find what fields we have and
    // what the CSS classes should be.
    $variables['fields'][$field] = Html::cleanCssIdentifier($field);
    if ($active == $field) {
      $variables['fields'][$field] .= ' is-active';
    }

    // We don't want to give the possibility to hide the 'node_bulk_form' or the
    // 'operations' columns.
    $always_visible = in_array($column, [
      'node_bulk_form',
      'views_bulk_operations_bulk_form',
      'operations',
    ]);

    $field_flexible_logic_is_visible = isset($column_settings)
      ? in_array($field, Json::decode($column_settings)) || $always_visible
      : ($options['info'][$field]['default_visible'] ?? $always_visible) || $always_visible;

    // Render the header labels.
    if ($field == $column && empty($fields[$field]->options['exclude']) && $field_flexible_logic_is_visible) {
      $label = !empty($fields[$field]) ? $fields[$field]->label() : '';
      if (empty($options['info'][$field]['sortable']) || !$fields[$field]->clickSortable()) {
        $variables['header'][$field]['content'] = $label;
      }
      else {
        $initial = !empty($options['info'][$field]['default_sort_order']) ? $options['info'][$field]['default_sort_order'] : 'asc';

        if ($active == $field) {
          $initial = ($order == 'asc') ? 'desc' : 'asc';
        }

        $title = t('sort by @s', ['@s' => $label]);
        if ($active == $field) {
          $variables['header'][$field]['sort_indicator'] = [
            '#theme' => 'tablesort_indicator',
            '#style' => $initial,
          ];
        }

        $query['order'] = $field;
        $query['sort'] = $initial;

        $query_for_sort_link = $query;

        // Unset not used filters in query.
        foreach ($query as $parameter => $value) {
          if (strpos($parameter, '_check_deactivate') !== FALSE && !$value) {
            $filter_name = str_replace('_check_deactivate', '', $parameter);

            // Unset Filter.
            unset($query_for_sort_link[$filter_name]);

            // Unset Operator.
            unset($query_for_sort_link[$view->storage->getExecutable()->filter[$filter_name]->options["expose"]["operator_id"]]);
          }
        }

        $link_options = [
          'query' => $query_for_sort_link,
        ];
        $url = new Url($route_name, [], $link_options);
        $variables['header'][$field]['url'] = $url->toString();
        $variables['header'][$field]['content'] = $label;
        $variables['header'][$field]['title'] = $title;
      }

      $variables['header'][$field]['default_classes'] = $fields[$field]->options['element_default_classes'];
      // Set up the header label class.
      $variables['header'][$field]['attributes'] = [];
      $class = $fields[$field]->elementLabelClasses(0);
      if ($class) {
        $variables['header'][$field]['attributes']['class'][] = $class;
      }
      // Add responsive header classes.
      if (!empty($options['info'][$field]['responsive'])) {
        $variables['header'][$field]['attributes']['class'][] = $options['info'][$field]['responsive'];
        $responsive = TRUE;
      }
      // Add a CSS align class to each field if one was set.
      if (!empty($options['info'][$field]['align'])) {
        $variables['header'][$field]['attributes']['class'][] = Html::cleanCssIdentifier($options['info'][$field]['align']);
      }
      // Add a header label wrapper if one was selected.
      if ($variables['header'][$field]['content']) {
        $element_label_type = $fields[$field]->elementLabelType(TRUE, TRUE);
        if ($element_label_type) {
          $variables['header'][$field]['wrapper_element'] = $element_label_type;
        }
        // Improves accessibility of complex tables.
        $variables['header'][$field]['attributes']['id'] = Html::getUniqueId('view-' . $field . '-table-column');
      }
      // Check if header label is not empty.
      if (!empty($variables['header'][$field]['content'])) {
        $has_header_labels = TRUE;
      }

      $variables['header'][$field]['attributes'] = new Attribute($variables['header'][$field]['attributes']);
    }

    // Add a CSS align class to each field if one was set.
    if (!empty($options['info'][$field]['align'])) {
      $variables['fields'][$field] .= ' ' . Html::cleanCssIdentifier($options['info'][$field]['align']);
    }

    // Render each field into its appropriate column.
    foreach ($result as $num => $row) {

      // Skip building the attributes and content if the field is to be excluded
      // from the display.
      if (!empty($fields[$field]->options['exclude'])) {
        continue;
      }

      // Skip building the attributes and content if the field is to be excluded
      // from the display by the flexible table columns logic.
      if ($field !== 'operations' && !$field_flexible_logic_is_visible) {
        continue;
      }

      // Reference to the column in the loop to make the code easier to read.
      $column_reference =& $variables['rows'][$num]['columns'][$column];

      $column_reference['default_classes'] = $fields[$field]->options['element_default_classes'];

      // Set the field key to the column so it can be used for adding classes
      // in a template.
      $column_reference['fields'][] = $variables['fields'][$field];

      // Add field classes.
      if (!isset($column_reference['attributes'])) {
        $column_reference['attributes'] = [];
      }

      if ($classes = $fields[$field]->elementClasses($num)) {
        $column_reference['attributes']['class'][] = $classes;
      }

      // Add responsive header classes.
      if (!empty($options['info'][$field]['responsive'])) {
        $column_reference['attributes']['class'][] = $options['info'][$field]['responsive'];
      }

      // Improves accessibility of complex tables.
      if (isset($variables['header'][$field]['attributes']['id'])) {
        $column_reference['attributes']['headers'] = [$variables['header'][$field]['attributes']['id']];
      }

      if (!empty($fields[$field])) {
        $field_output = $handler->getField($num, $field);
        $column_reference['wrapper_element'] = $fields[$field]->elementType(TRUE, TRUE);
        if (!isset($column_reference['content'])) {
          $column_reference['content'] = [];
        }

        // Only bother with separators and stuff if the field shows up.
        // Place the field into the column, along with an optional separator.
        if (trim($field_output) != '') {
          if (!empty($column_reference['content']) && !empty($options['info'][$column]['separator'])) {
            $column_reference['content'][] = [
              'separator' => ['#markup' => $options['info'][$column]['separator']],
              'field_output' => ['#markup' => $field_output],
            ];
          }
          else {
            $column_reference['content'][] = [
              'field_output' => ['#markup' => $field_output],
            ];
          }
        }
      }
      $column_reference['attributes'] = new Attribute($column_reference['attributes']);
    }

    // Remove columns if the "empty_column" option is checked and the
    // field is empty.
    if (!empty($options['info'][$field]['empty_column'])) {
      $empty = TRUE;
      foreach ($variables['rows'] as $columns) {
        $empty &= empty($columns['columns'][$column]['content']);
      }
      if ($empty) {
        foreach ($variables['rows'] as &$column_items) {
          unset($column_items['columns'][$column]);
        }
        unset($variables['header'][$column]);
      }
    }
  }

  // Hide table header if all labels are empty.
  if (!$has_header_labels) {
    $variables['header'] = [];
  }

  foreach ($variables['rows'] as $num => $row) {
    $variables['rows'][$num]['attributes'] = [];
    if ($row_class = $handler->getRowClass($num)) {
      $variables['rows'][$num]['attributes']['class'][] = $row_class;
    }
    $variables['rows'][$num]['attributes'] = new Attribute($variables['rows'][$num]['attributes']);
  }

  if (empty($variables['rows']) && !empty($options['empty_table'])) {
    $build = $view->display_handler->renderArea('empty');
    $variables['rows'][0]['columns'][0]['content'][0]['field_output'] = $build;
    $variables['rows'][0]['attributes'] = new Attribute(['class' => ['odd']]);
    // Calculate the amounts of rows with output.
    $variables['rows'][0]['columns'][0]['attributes'] = new Attribute([
      'colspan' => count($variables['header']),
      'class' => ['views-empty'],
    ]);
  }

  $variables['sticky'] = FALSE;
  if (!empty($options['sticky'])) {
    $variables['view']->element['#attached']['library'][] = 'core/drupal.tableheader';
    $variables['sticky'] = TRUE;
  }

  // Add the caption to the list if set.
  if (!empty($handler->options['caption'])) {
    $variables['caption'] = ['#markup' => $handler->options['caption']];
    $variables['caption_needed'] = TRUE;
  }
  elseif (!empty($variables['title'])) {
    $variables['caption'] = ['#markup' => $variables['title']];
    $variables['caption_needed'] = TRUE;
  }
  else {
    $variables['caption'] = '';
    $variables['caption_needed'] = FALSE;
  }

  $variables['summary'] = $handler->options['summary'];
  $variables['description'] = $handler->options['description'];
  $variables['caption_needed'] |= !empty($variables['summary']) || !empty($variables['description']);

  $variables['responsive'] = FALSE;
  // If the table has headers and it should react responsively to columns hidden
  // with the classes represented by the constants RESPONSIVE_PRIORITY_MEDIUM
  // and RESPONSIVE_PRIORITY_LOW, add the tableresponsive behaviors.
  if (isset($variables['header']) && $responsive) {
    $variables['view']->element['#attached']['library'][] = 'core/drupal.tableresponsive';
    // Add 'responsive-enabled' class to the table to identify it for JS.
    // This is needed to target tables constructed by this function.
    $variables['responsive'] = TRUE;
  }
}
