/**
 * AI Assistant - Config Page Script
 * Manages visibility of fields based on API provider selection
 *
 * NO-NO-NO
 */

(function ($) {
  'use strict'
  // Wait for document ready
  $(document).ready(function () {

    // Helper function to find field by label text
    function findFieldByLabel(labelText) {
      var $field = null
      $('.form-field').each(function() {
        var $div = $(this)
        if ($div.text().indexOf(labelText) !== -1) {
          // Found the div containing the label, now find the field inside it
          $field = $div.find('select, input[type="text"]').first()
          return false // break
        }
      })
      return $field
    }

    // Find fields by their label text (osTicket hashes field names per session)
    var $provider = findFieldByLabel('API Provider')
    var $apiUrl = findFieldByLabel('API URL')
    var $modelValue = $('#model_value')
    var $modelSelect = $('#model_select')
    var $modelText = $('#model_text')
    var $reasoning = $('.reasoning-effort-select')
    var $visionModel = $('.vision-model-text-input')

    if (!$provider || !$provider.length) {
      // Field not found - probably not on the config page
      return
    }

    var $reasoningValue = $('<input type="hidden">')
      .attr('name', $reasoning.attr('name'))
      .prop('disabled', true)
      .insertAfter($reasoning)
    var $reasoningUnavailable = $('<div class="reasoning-unavailable-hint">')
      .text($reasoning.attr('data-unavailable-hint'))
      .insertAfter($reasoningValue)
    var reasoningRules = JSON.parse($reasoning.attr('data-reasoning-rules') || '[]')

    function modelReasoningEfforts (model) {
      for (var index = 0; index < reasoningRules.length; index++) {
        var rule = reasoningRules[index]
        if (new RegExp(rule.pattern).test(model)) {
          return rule.efforts
        }
      }
      return []
    }

    function updateReasoningAvailability () {
      if (!$reasoning.length) {
        return
      }
      var model = ($modelValue.val() || '').trim()
      var visionModel = ($visionModel.val() || '').trim()
      var analysisEfforts = modelReasoningEfforts(model)
      var visionEfforts = modelReasoningEfforts(visionModel)
      var efforts = analysisEfforts.length ? analysisEfforts : visionEfforts
      if (analysisEfforts.length && visionEfforts.length) {
        efforts = analysisEfforts.filter(function (effort) {
          return visionEfforts.indexOf(effort) !== -1
        })
      }
      var supported = efforts.length > 0
      $reasoning.find('option').each(function () {
        var allowed = !supported || efforts.indexOf(this.value) !== -1
        $(this).prop('disabled', !allowed).prop('hidden', !allowed)
      })
      if (supported && efforts.indexOf($reasoning.val()) === -1) {
        $reasoning.val(efforts[0])
      }
      $reasoning.prop('disabled', !supported)
      $reasoningValue.val($reasoning.val()).prop('disabled', !!supported)
      $reasoningUnavailable.toggle(!supported)
    }

    function updateFieldVisibility () {
      var provider = $provider.val()
      // ChoicesWidget returns array, get first element
      if (Array.isArray(provider)) {
        provider = provider[0]
      }
      var isCustom = (provider === 'custom')

      if (isCustom) {
        // Custom mode
        // Show API URL field
        if ($apiUrl && $apiUrl.length) {
          $apiUrl.closest('.form-field').show()
        }

        // Show text input, hide dropdown
        $modelSelect.hide()
        $modelText.show()

        // Sync hidden field with text input value
        $modelValue.val($modelText.val())
      } else {
        // OpenAI mode
        // Hide API URL field and set default value
        if ($apiUrl && $apiUrl.length) {
          $apiUrl.closest('.form-field').hide()
          $apiUrl.val('https://api.openai.com/v1/chat/completions')
        }

        // Show dropdown, hide text input
        $modelSelect.show()
        $modelText.hide()

        // Ensure hidden field has the dropdown value
        var selectedValue = $modelSelect.val()
        if (!selectedValue) {
          // If no value selected, select first option
          selectedValue = $modelSelect.find('option:first').val()
          $modelSelect.val(selectedValue)
        }
        $modelValue.val(selectedValue)
      }
      updateReasoningAvailability()
    }

    // Sync select dropdown with hidden field
    $modelSelect.on('change', function () {
      var value = $(this).val()
      $modelValue.val(value)
      updateReasoningAvailability()
    })

    // Sync text input with hidden field
    $modelText.on('input change', function () {
      var value = $(this).val()
      $modelValue.val(value)
      updateReasoningAvailability()
    })

    $visionModel.on('input change', updateReasoningAvailability)

    // Listen to provider changes
    $provider.on('change', updateFieldVisibility)

    // Initial setup
    updateFieldVisibility()
  });
})(jQuery)
