<?php

namespace Drupal\flexible_views\Plugin\views\style;

use Drupal\views\Plugin\views\style\Table;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Style plugin to render each item as a row in a table.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "flexible_table",
 *   title = @Translation("Flexible Table"),
 *   help = @Translation("Displays rows in a flexible table."),
 *   theme = "views_view_flexible_table",
 *   display_types = {"normal"}
 * )
 */
class FlexibleTable extends Table implements CacheableDependencyInterface {

  /**
   * Add the default_visible property to each column.
   *
   * @inheritDoc
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $columns = $this->sanitizeColumns($this->options['columns']);

    $form['#theme'] = 'flexible_views_style_plugin_flexible_table';

    foreach ($columns as $field => $column) {
      $column_selector = ':input[name="style_options[columns][' . $field . ']"]';

      $form['info'][$field]['default_visible'] = [
        '#title' => $this->t('Visible by default'),
        '#title_display' => 'invisible',
        '#type' => 'checkbox',
        '#default_value' => isset($this->options['info'][$field]['default_visible']) ? $this->options['info'][$field]['default_visible'] : FALSE,
        '#states' => [
          'visible' => [
            $column_selector => ['value' => $field],
          ],
        ],
      ];
    }
  }

}
