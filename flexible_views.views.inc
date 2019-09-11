<?php

/**
 * @file
 * Includes flexible_views.views.inc
 */

/**
 * Implements hook_views_data_alter().
 */
function flexible_views_views_data_alter(&$data) {
  $data['views']['column_selector'] = [
    'title' => t('Visible Column Selector'),
    'filter' => [
      'title' => t('Visible Column Selector'),
      'help' => 'Visible Column Selector',
      'id' => 'column_selector',
    ],
  ];
}
