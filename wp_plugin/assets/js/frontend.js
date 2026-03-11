/**
 * Logic Essentials Fluent Forms Addon - Frontend Runtime
 * Handles client-side evaluation of conditional logic rules.
 */

(function () {
    'use strict';

    // Store initialized forms to avoid double initialization
    var initializedForms = {};

    /**
     * Debounce helper for input events
     */
    function debounce(func, wait) {
        var timeout;
        return function () {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                timeout = null;
                func.apply(context, args);
            }, wait);
        };
    }

    /**
     * Get the current value of a form field by its name attribute mapping.
     */
    function getFieldValue(formEl, fieldName) {
        // Elements can be inputs, selects, textareas, radios, checkboxes.
        // Fluent Form field names usually look like: `input_text_1`, `radio_1`, `checkbox_1[]`

        var elements = formEl.querySelectorAll('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');
        if (!elements || elements.length === 0) {
            return null; // Field not found or not rendered
        }

        var type = elements[0].type;
        var value = [];

        if (type === 'radio' || type === 'checkbox') {
            for (var i = 0; i < elements.length; i++) {
                if (elements[i].checked) {
                    value.push(elements[i].value);
                }
            }
            if (type === 'radio') {
                return value.length > 0 ? value[0] : '';
            }
            return value; // Array for checkboxes
        } else if (elements[0].tagName.toLowerCase() === 'select' && elements[0].multiple) {
            var options = elements[0].options;
            for (var j = 0; j < options.length; j++) {
                if (options[j].selected) {
                    value.push(options[j].value);
                }
            }
            return value;
        } else {
            return elements[0].value;
        }
    }

    /**
     * Evaluate a single condition against the actual field value.
     */
    function evaluateCondition(actualValue, operator, expectedValue) {
        // Normalize actual value to string or array of strings for comparison
        var isArray = Array.isArray(actualValue);
        var actualStr = isArray ? actualValue.join(',').toLowerCase() : String(actualValue || '').toLowerCase();
        var expectStr = String(expectedValue || '').toLowerCase();

        switch (operator) {
            case 'is':
            case '==':
                if (isArray && actualValue.length > 0) {
                    // For checkboxes/multiselect, 'is' might mean exact match or "contains" depending on implementation.
                    // Usually 'is' means array contains the value for multi-selects in form logic.
                    return actualValue.map(function (v) { return String(v).toLowerCase(); }).indexOf(expectStr) !== -1;
                }
                return actualStr === expectStr;

            case 'is not':
            case '!=':
                if (isArray && actualValue.length > 0) {
                    return actualValue.map(function (v) { return String(v).toLowerCase(); }).indexOf(expectStr) === -1;
                }
                return actualStr !== expectStr;

            case 'contains':
                return actualStr.indexOf(expectStr) !== -1;

            case 'does not contain':
                return actualStr.indexOf(expectStr) === -1;

            case 'is empty':
                return actualStr === '';

            case 'is not empty':
                return actualStr !== '';

            case 'greater than':
            case '>':
                return parseFloat(actualValue) > parseFloat(expectedValue);

            case 'less than':
            case '<':
                return parseFloat(actualValue) < parseFloat(expectedValue);

            default:
                return false;
        }
    }

    /**
     * Evaluate an entire rule object.
     */
    function evaluateRule(rule, formEl) {
        if (!rule.conditions || rule.conditions.length === 0) return true;

        var isAnd = (rule.logic || 'AND').toUpperCase() === 'AND';
        var result = isAnd ? true : false;

        for (var i = 0; i < rule.conditions.length; i++) {
            var cond = rule.conditions[i];
            var actualValue = getFieldValue(formEl, cond.field);
            var condResult = evaluateCondition(actualValue, cond.operator, cond.value);

            if (isAnd) {
                result = result && condResult;
                if (!result) break; // Short circuit
            } else {
                result = result || condResult;
                if (result) break; // Short circuit
            }
        }

        return result;
    }

    /**
     * Apply visibility state to a field's container wrapper.
     */
    function applyVisibility(formEl, targetField, isVisible) {
        // Find the wrapper for the field. Usually '.ff-el-group' spanning the input.
        // We look for the input first to find its closest wrapper.
        var els = formEl.querySelectorAll('[name="' + targetField + '"], [name="' + targetField + '[]"]');
        var wrapper = null;

        if (els && els.length > 0) {
            // Find closest parent with .ff-el-group or fallback to the input's parent container
            var current = els[0];
            while (current && current !== formEl) {
                if (current.classList && current.classList.contains('ff-el-group')) {
                    wrapper = current;
                    break;
                }
                current = current.parentNode;
            }
        }

        // If it's a structural element without a name (like a section), we might need to find it by ID or class.
        // Fluent Forms usually puts a class matching the field name on the wrapper: .ff-field_name
        if (!wrapper) {
            wrapper = formEl.querySelector('.ff-field_' + targetField) || formEl.querySelector('.ff-el-' + targetField);
        }

        if (wrapper) {
            /* Dispatch before custom event */
            var beforeEvent = new CustomEvent('ff:rule:beforeEvaluate', { detail: { fieldId: targetField, wrapper: wrapper, visible: isVisible }, cancelable: true });
            if (!formEl.dispatchEvent(beforeEvent)) {
                return; // Cancelled
            }

            if (isVisible) {
                wrapper.style.display = '';
                wrapper.removeAttribute('aria-hidden');
                // Restore required attr if needed (advanced feature, keeping simple for MVP)
            } else {
                wrapper.style.display = 'none';
                wrapper.setAttribute('aria-hidden', 'true');
            }

            /* Dispatch after custom event */
            var afterEvent = new CustomEvent('ff:field:visibilityChange', { detail: { fieldId: targetField, wrapper: wrapper, visible: isVisible } });
            formEl.dispatchEvent(afterEvent);
        }
    }

    /**
     * Re-evaluate all rules that depend on a changed field.
     */
    function handleFieldChange(triggerFieldName, formEl, config) {
        if (!config.dependency_map[triggerFieldName]) return;

        var dependentTargetFields = config.dependency_map[triggerFieldName];

        // Loop through dependent target fields
        for (var i = 0; i < dependentTargetFields.length; i++) {
            var targetField = dependentTargetFields[i];

            // Find the rule for this target field
            var rule = null;
            for (var r = 0; r < config.rules.length; r++) {
                if (config.rules[r].target_field === targetField) {
                    rule = config.rules[r];
                    break;
                }
            }

            if (rule && rule.enabled !== false) {
                var conditionsMet = evaluateRule(rule, formEl);
                var shouldShow = rule.action === 'show' ? conditionsMet : !conditionsMet;
                applyVisibility(formEl, rule.target_field, shouldShow);
            }
        }
    }

    /**
     * Initialize logic for a specific form.
     */
    function initForm(formId, config) {
        if (!config || !config.rules || config.rules.length === 0) return;

        // Find form element
        var formSelector = 'form[data-form_id="' + formId + '"]';
        var formEls = document.querySelectorAll(formSelector);

        formEls.forEach(function (formEl) {
            // Avoid double init on same physical DOM node
            if (formEl.dataset.leFfaInit) return;
            formEl.dataset.leFfaInit = 'true';

            // Build dependency map: trigger_field -> [target_fields...]
            config.dependency_map = {};
            config.rules.forEach(function (rule) {
                if (!rule.conditions || rule.enabled === false) return;

                rule.conditions.forEach(function (cond) {
                    if (!config.dependency_map[cond.field]) {
                        config.dependency_map[cond.field] = [];
                    }
                    if (config.dependency_map[cond.field].indexOf(rule.target_field) === -1) {
                        config.dependency_map[cond.field].push(rule.target_field);
                    }
                });
            });

            // Initial evaluation of all rules on load
            config.rules.forEach(function (rule) {
                if (rule.enabled !== false) {
                    var conditionsMet = evaluateRule(rule, formEl);
                    var shouldShow = rule.action === 'show' ? conditionsMet : !conditionsMet;
                    applyVisibility(formEl, rule.target_field, shouldShow);
                }
            });

            // Listeners for changes using event delegation
            var debouncedInputHandler = debounce(function (e) {
                var target = e.target;
                if (!target.name) return;
                var fieldName = target.name.replace('[]', '');
                handleFieldChange(fieldName, formEl, config);
            }, 300);

            formEl.addEventListener('input', function (e) {
                // Only debounce text-like inputs
                var type = e.target.type;
                if (type === 'text' || type === 'textarea' || type === 'number' || type === 'email') {
                    debouncedInputHandler(e);
                }
            });

            formEl.addEventListener('change', function (e) {
                // Immediate reaction for selects, radios, checkboxes, etc.
                var target = e.target;
                if (!target.name) return;
                var fieldName = target.name.replace('[]', '');

                var type = target.type;
                if (type !== 'text' && type !== 'textarea' && type !== 'number' && type !== 'email') {
                    handleFieldChange(fieldName, formEl, config);
                }
            });
        });
    }

    /**
     * Start the engine.
     */
    function boot() {
        if (typeof window.le_ffa_forms !== 'object') return;

        for (var formId in window.le_ffa_forms) {
            if (window.le_ffa_forms.hasOwnProperty(formId)) {
                initForm(formId, window.le_ffa_forms[formId]);
            }
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
