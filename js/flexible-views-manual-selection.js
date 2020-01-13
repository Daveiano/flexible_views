/**
 * @file
 * Manual exposed filter js select logic.
 */

(function ($, Drupal) {

  'use strict';

  var initialized;

  function init() {
    if (!initialized) {
      initialized = true;

      // Set the enabled filters on page load to disabled in the filter select.
      var selectedFilters = $('input[name="selected_filters"]').val().split(',');

      for (var i = 0; i < selectedFilters.length; i++) {
        $("select[name='manual_select_filter']").find('option[value="' + selectedFilters[i] + '"]').prop("disabled", true);
      }
    }
  }

  /**
   * Helper fn to populate the hidden field which stores the active filters.
   *
   * @param {string} action - The action to perform: add or remove.
   * @param {string} values - The current values.
   * @param {Object} $element - The form element as jQuery element.
   * @param {string} newValue - The newValue to add or remove.
   */
  var populateHiddenField = function (action, values, $element, newValue) {
    var valuesArray = values.split(',');

    if (action === 'add') {
      $element.val(valuesArray.concat([newValue]).join(','));
    }

    if (action === 'remove') {
      var newArrayValues = valuesArray.filter(function (item) {
        return item !== newValue
      });

      $element.val(newArrayValues.join(','));
    }

    $element.trigger('change');
  };

  /**
   * Activate the given filters.
   *
   * @param {string} selectedFilters - Comma seperated string of filter names.
   */
  var activateFilters = function (selectedFilters) {
    for (var i = 0; i < selectedFilters.length; i++) {
      var $selectedFilterCheckbox = $('input[name="' + selectedFilters[i] + '_check_deactivate"]');
      $selectedFilterCheckbox.prop('checked', true).trigger('change');

      // Add active class to filter-wrap.
      $selectedFilterCheckbox.parents('.filter-wrap').addClass('active');
    }
  };

  Drupal.behaviors.flexible_views_manual_selection = {
    attach: function (context, settings) {
      init();

      // Write the selected filters from the select element to a hidden field.
      $("select[name='manual_select_filter']").once().change(function () {
        // Copy the selected value to the hidden field.
        var $selectedFilters = $('input[name="selected_filters"]');
        populateHiddenField('add', $selectedFilters.val(), $selectedFilters, $(this).val());

        // Disable the chosen option.
        $(this).find('option[value="' + $(this).val() + '"]').prop("disabled", true);

        // Set the select element back to the default value.
        $(this).val('');
      });

      // Activate the filters.
      $('input[name="selected_filters"]').once().change(function () {
        var selectedFilters = $(this).val().split(',');

        activateFilters(selectedFilters);
      });

      // Deactivate the filters.
      $('input[name$="_check_deactivate"]').each(function () {
        $(this).once().change(function () {
          if (!this.checked) {
            var $selectedFilters = $('input[name="selected_filters"]'),
              valueToRemove = $(this).attr('name').replace('_check_deactivate', '');

            // Remove active class from wrapper element.
            $(this).parents('.filter-wrap').removeClass('active');

            populateHiddenField('remove', $selectedFilters.val(), $selectedFilters, valueToRemove);

            $("select[name='manual_select_filter']").find('option[value="' + valueToRemove + '"]').prop("disabled", false);
          }
        });
      });
    }
  };
})(jQuery, Drupal);
