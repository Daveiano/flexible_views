/**
 * @file
 * Column selector js select logic.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Get the sort order of the fields and save it to the hidden order field.
   *
   * @param {Object} $selectedColumnsSelect - Selected columns <select>.
   */
  var populateSortOrder = function ($selectedColumnsSelect) {
    var sortOrder = [];

    $selectedColumnsSelect.find('option').each(function () {
      sortOrder.push($(this).val());
    });

    $('#flexible-table-selected-columns-submit-order').val(JSON.stringify(sortOrder));
  };

  /**
   * Move field from the available to the selected columns.
   *
   * @param {Object} $availableColumnsSelect - Available columns <select>.
   * @param {Object} $selectedColumnsSelect - Selected columns <select>.
   */
  var moveRight = function ($availableColumnsSelect, $selectedColumnsSelect) {
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
    populateSortOrder($selectedColumnsSelect);
  };

  /**
   * Move field from the selected to the available columns.
   *
   * @param {Object} $availableColumnsSelect - Available columns <select>.
   * @param {Object} $selectedColumnsSelect - Selected columns <select>.
   */
  var moveLeft = function ($availableColumnsSelect, $selectedColumnsSelect) {
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
    populateSortOrder($selectedColumnsSelect);
  };

  /**
   * Move field up in the selected columns.
   *
   * @param {Object} $availableColumnsSelect - Available columns <select>.
   * @param {Object} $selectedColumnsSelect - Selected columns <select>.
   */
  var moveTop = function ($availableColumnsSelect, $selectedColumnsSelect) {
    // Get the selected option(s) and move them up.
    $selectedColumnsSelect.find('option:selected').each(function () {
      $(this).prev(':not(:selected)').detach().insertAfter($(this));
    });

    // Populate the value in the order textfield.
    populateSortOrder($selectedColumnsSelect);
  };

  /**
   * Move field down in the selected columns.
   *
   * @param {Object} $availableColumnsSelect - Available columns <select>.
   * @param {Object} $selectedColumnsSelect - Selected columns <select>.
   */
  var moveDown = function ($availableColumnsSelect, $selectedColumnsSelect) {
    $($selectedColumnsSelect.find('option:selected').get().reverse()).each(function () {
      $(this).next(':not(:selected)').detach().insertBefore($(this));
    });

    // Populate the value in the order textfield.
    populateSortOrder($selectedColumnsSelect);
  };

  Drupal.behaviors.flexible_views_column_selector = {
    attach: function (context, settings) {

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
          moveRight($availableColumnsSelect, $selectedColumnsSelect);
        }
      });

      $('.form-item.move-buttons .move-left').once().on('click', function () {
        if ($selectedColumnsSelect.val().length > 0) {
          moveLeft($availableColumnsSelect, $selectedColumnsSelect);
          $availableColumnsSelect.val('');
        }
      });

      $('.form-item.move-buttons .move-top').once().on('click', function () {
        if ($selectedColumnsSelect.val().length > 0) {
          moveTop($availableColumnsSelect, $selectedColumnsSelect);
        }
      });

      $('.form-item.move-buttons .move-down').once().on('click', function () {
        if ($selectedColumnsSelect.val().length > 0) {
          moveDown($availableColumnsSelect, $selectedColumnsSelect);
        }
      });

    }
  };

})(jQuery, Drupal);
