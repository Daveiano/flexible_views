/**
 * @file
 * Column selector js select logic.
 */

(function ($, Drupal) {

  var initialized;

  function init() {
    if (!initialized) {
      initialized = true;

      //Drupal.flexible_views_column_selector.populateRealSelect($('select#flexible-table-selected-columns'), $('select#flexible-table-selected-columns-submit'));
      //Drupal.flexible_views_column_selector.populateSortOrder($('select#flexible-table-selected-columns'));
    }
  }

  Drupal.flexible_views_column_selector = {};

  Drupal.flexible_views_column_selector.populateRealSelect = function ($selectedColumnsSelect, $realSelectedColumnsSelect) {
    var $selectedOptionsForSubmit = $selectedColumnsSelect.find('option'),
      valueForSubmit = [];

    $selectedOptionsForSubmit.each(function () {
      valueForSubmit.push($(this).val());
    });

    $realSelectedColumnsSelect.val(valueForSubmit);
  };

  Drupal.flexible_views_column_selector.populateSortOrder = function ($selectedColumnsSelect) {
    // Get the sort order of the fields and save it to the hidden order field.
    var sortOrder = [];

    $selectedColumnsSelect.find('option').each(function (i) {
      sortOrder.push($(this).val());
    });

    $('#flexible-table-selected-columns-submit-order').val(JSON.stringify(sortOrder));
  };

  Drupal.flexible_views_column_selector.moveRight = function ($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect) {
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

    // Populate the value in fake select.
    Drupal.flexible_views_column_selector.populateRealSelect($selectedColumnsSelect, $realSelectedColumnsSelect);
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.flexible_views_column_selector.moveLeft = function ($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect) {
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

    // Populate the value in fake select.
    Drupal.flexible_views_column_selector.populateRealSelect($selectedColumnsSelect, $realSelectedColumnsSelect);
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.flexible_views_column_selector.moveTop = function ($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect) {
    // Get the selected option(s) and move them up.
    $selectedColumnsSelect.find('option:selected').each(function () {
      $(this).prev(':not(:selected)').detach().insertAfter($(this));
    });

    Drupal.flexible_views_column_selector.populateRealSelect($selectedColumnsSelect, $realSelectedColumnsSelect);
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.flexible_views_column_selector.moveDown = function ($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect) {
    $($selectedColumnsSelect.find('option:selected').get().reverse()).each(function () {
      $(this).next(':not(:selected)').detach().insertBefore($(this));
    });

    Drupal.flexible_views_column_selector.populateRealSelect($selectedColumnsSelect, $realSelectedColumnsSelect);
    Drupal.flexible_views_column_selector.populateSortOrder($selectedColumnsSelect);
  };

  Drupal.behaviors.flexible_views_column_selector = {};

  Drupal.behaviors.flexible_views_column_selector.attach = function (context, settings) {

    // Some initial work (executed only once).
    init();

    var $availableColumnsSelect = $('select#flexible-table-available-columns'),
      $selectedColumnsSelect = $('select#flexible-table-selected-columns'),
      $realSelectedColumnsSelect = $('select#flexible-table-selected-columns-submit'),
      columnOrder = $('#flexible-table-selected-columns-submit-order').val() ? JSON.parse($('#flexible-table-selected-columns-submit-order').val()) : [];

    // Add the correct sort order.
    var sortOrderFromInput = columnOrder,
      sortedOptions = $selectedColumnsSelect.find('option').sort(function (a, b) {
        if (sortOrderFromInput.indexOf($(a).val()) > sortOrderFromInput.indexOf($(b).val())) {
          return 1;
        }
        else {
          return -1;
        }
      });

    $selectedColumnsSelect.html('').append(sortedOptions);

    // Click event handler for moving columns.
    $('.form-item.move-buttons .move-right').once().on('click', function () {
      if ($availableColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveRight($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect);
      }
    });

    $('.form-item.move-buttons .move-left').once().on('click', function () {
      if ($selectedColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveLeft($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect);
        $availableColumnsSelect.val('');
      }
    });

    $('.form-item.move-buttons .move-top').once().on('click', function () {
      if ($selectedColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveTop($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect);
      }
    });

    $('.form-item.move-buttons .move-down').once().on('click', function () {
      if ($selectedColumnsSelect.val().length > 0) {
        Drupal.flexible_views_column_selector.moveDown($availableColumnsSelect, $selectedColumnsSelect, $realSelectedColumnsSelect);
      }
    });

  };

})(jQuery, Drupal);
