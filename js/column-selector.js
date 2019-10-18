/**
 * @file
 * Column selector js select logic.
 */

(function ($, Drupal) {

  Drupal.flexible_views_column_selector = {};

  Drupal.flexible_views_column_selector.populateSortOrder = function ($selectedColumnsSelect) {
    // Get the sort order of the fields and save it to the hidden order field.
    var sortOrder = [];

    $selectedColumnsSelect.find('option').each(function (i) {
      sortOrder.push($(this).val());
    });

    $('#flexible-table-selected-columns-submit-order').val(JSON.stringify(sortOrder));
  };

  Drupal.flexible_views_column_selector.moveRight = function ($availableColumnsSelect, $selectedColumnsSelect) {
    var selected = $availableColumnsSelect.val();

    // Move and show/hide options in the visible selects.
    selected.forEach(function (value) {
      // Add option in selected select.
      $selectedColumnsSelect.prepend($('<option>', {
        value: value,
        text: $availableColumnsSelect.find('option[value="' + value + '"]').text()
      }));

      // Remove option in available select.
      $availableColumnsSelect.find('option[value="' + value + '"]').remove();
    });

    // Populate the value in the order textfield.
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.flexible_views_column_selector.moveLeft = function ($availableColumnsSelect, $selectedColumnsSelect) {
    var selected = $selectedColumnsSelect.val();

    selected.forEach(function (value) {
      // Add option to selected select.
      $availableColumnsSelect.prepend($('<option>', {
        value: value,
        text: $selectedColumnsSelect.find('option[value="' + value + '"]').text()
      }));

      // Remove option in available select.
      $selectedColumnsSelect.find('option[value="' + value + '"]').remove();
    });

    // Populate the value in the order textfield.
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.flexible_views_column_selector.moveTop = function ($availableColumnsSelect, $selectedColumnsSelect) {
    // Get the selected option(s) and move them up.
    $selectedColumnsSelect.find('option:selected').each(function () {
      $(this).prev(':not(:selected)').detach().insertAfter($(this));
    });

    // Populate the value in the order textfield.
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.flexible_views_column_selector.moveDown = function ($availableColumnsSelect, $selectedColumnsSelect) {
    $($selectedColumnsSelect.find('option:selected').get().reverse()).each(function () {
      $(this).next(':not(:selected)').detach().insertBefore($(this));
    });

    // Populate the value in the order textfield.
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.behaviors.flexible_views_column_selector = {};

  Drupal.behaviors.flexible_views_column_selector.attach = function (context, settings) {

    var $availableColumnsSelect = $('select#flexible-table-available-columns'),
      $selectedColumnsSelect = $('select#flexible-table-selected-columns'),
      columnOrder = $('#flexible-table-selected-columns-submit-order').val() ? JSON.parse($('#flexible-table-selected-columns-submit-order').val()) : [];

    // Add the correct sort order.
    var sortOrderFromInput = columnOrder,
      sortedOptions = sortOrderFromInput.length > 0 ? $selectedColumnsSelect.find('option').sort(function (a, b) {
        if (sortOrderFromInput.indexOf($(a).val()) > sortOrderFromInput.indexOf($(b).val())) {
          return 1;
        }
        else {
          return -1;
        }
      }) : $selectedColumnsSelect.find('option');

    $selectedColumnsSelect.html('').append(sortedOptions);

    // Click event handler for moving columns.
    $('.form-item.move-buttons .move-right').once().on('click', function () {
      if ($availableColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveRight($availableColumnsSelect, $selectedColumnsSelect);
      }
    });

    $('.form-item.move-buttons .move-left').once().on('click', function () {
      if ($selectedColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveLeft($availableColumnsSelect, $selectedColumnsSelect);
        $availableColumnsSelect.val('');
      }
    });

    $('.form-item.move-buttons .move-top').once().on('click', function () {
      if ($selectedColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveTop($availableColumnsSelect, $selectedColumnsSelect);
      }
    });

    $('.form-item.move-buttons .move-down').once().on('click', function () {
      if ($selectedColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveDown($availableColumnsSelect, $selectedColumnsSelect);
      }
    });

  };

})(jQuery, Drupal);
