<?php
/**
 * Core Integration Logic for Logic Essentials Fluent Forms Addon.
 *
 * @package Logic_Essentials_Fluent_Forms_Addon
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class LE_FFA_Integration
 */
class LE_FFA_Integration
{

    /**
     * Instance of this class.
     *
     * @var LE_FFA_Integration
     */
    private static $instance;

    /**
     * Get instance.
     *
     * @return LE_FFA_Integration
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        // Check if Fluent Forms Pro is active. If so, we operate in compatibility mode.
        $is_pro_active = defined('FLUENTFORMPRO') || has_action('fluentform_loaded_pro');

        if (!$is_pro_active) {
            // Hook into form submission before it is saved to the database to strip conditionally hidden fields.
            add_filter('fluentform/before_insert_submission', array($this, 'before_insert_submission'), 10, 3);

            // Hook into form validation to ignore hidden fields
            add_filter('fluentform_validate_submission', array($this, 'validate_submission'), 10, 4);

            // Enqueue frontend script and pass localized config for the form
            add_action('fluentform/rendering_form', array($this, 'enqueue_frontend_scripts'), 10, 2);
        }

        // Add a custom settings tab to Fluent Forms (Global Settings)
        add_filter('fluentform_global_settings_components', array($this, 'add_global_settings_tab'), 10, 1);

        // Add to Form Settings (Rules Tab)
        add_filter('fluentform_form_settings_menu', array($this, 'add_form_settings_menu'), 10, 2);

        // Add Conditional Logic panel to field settings
        add_action('fluentform_editor_field_settings_panel', array($this, 'add_field_settings_panel'), 10, 1);

        // Register REST Endpoints
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }

    /**
     * Enqueue frontend script and localize rules.
     */
    public function enqueue_frontend_scripts($form, $atts)
    {
        $form_id = $form->id;
        $rules = $this->get_form_rules($form_id);

        if (empty($rules)) {
            return;
        }

        wp_enqueue_script(
            'logic-essentials-ff-addon',
            LE_FFA_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            LE_FFA_VERSION,
            true
        );

        $client_config = array(
            'rules' => $rules,
        );
        $client_config = apply_filters('ff_conditional_client_config', $client_config, $form_id);

        $script = "window.le_ffa_forms = window.le_ffa_forms || {}; window.le_ffa_forms['{$form_id}'] = " . wp_json_encode($client_config) . ";";
        wp_add_inline_script('logic-essentials-ff-addon', $script, 'before');
    }

    /**
     * Retrieve rules from form meta.
     */
    private function get_form_rules($form_id)
    {
        // Implement form meta retrieval logic. For MVP, we assume it's stored under a meta key.
        // Fluentform uses wp_fluentform_form_meta table usually.
        if (function_exists('wpFluent')) {
            $meta_model = wpFluent()->table('fluentform_form_meta');
            $rule_meta = $meta_model->where('form_id', $form_id)->where('meta_key', 'le_ffa_conditional_rules')->first();

            if ($rule_meta && !empty($rule_meta->value)) {
                $decoded = json_decode($rule_meta->value, true);
                return isset($decoded['rules']) ? $decoded['rules'] : array();
            }
        }
        return array();
    }

    /**
     * Evaluate rules server-side.
     * Returns an array of hidden field names.
     */
    public function ff_conditional_evaluate($form_id, $entry_values)
    {
        $rules = $this->get_form_rules($form_id);
        if (empty($rules)) {
            return array();
        }

        $hidden_fields = array();

        foreach ($rules as $rule) {
            if (isset($rule['enabled']) && $rule['enabled'] === false) {
                continue;
            }

            if (empty($rule['conditions'])) {
                continue;
            }

            $is_and = strtoupper($rule['logic'] ?? 'AND') === 'AND';
            $conditions_met = $is_and ? true : false;

            foreach ($rule['conditions'] as $cond) {
                $actual = isset($entry_values[$cond['field']]) ? $entry_values[$cond['field']] : '';
                $cond_result = $this->evaluate_condition($actual, $cond['operator'], $cond['value']);

                if ($is_and) {
                    $conditions_met = $conditions_met && $cond_result;
                    if (!$conditions_met)
                        break;
                } else {
                    $conditions_met = $conditions_met || $cond_result;
                    if ($conditions_met)
                        break;
                }
            }

            $should_show = ($rule['action'] === 'show') ? $conditions_met : !$conditions_met;
            if (!$should_show) {
                $hidden_fields[] = $rule['target_field'];
            }
        }

        return $hidden_fields;
    }

    /**
     * Evaluate a single condition in PHP.
     */
    private function evaluate_condition($actual, $operator, $expected)
    {
        $is_array = is_array($actual);
        $actual_str = $is_array ? strtolower(implode(',', $actual)) : strtolower(strval($actual));
        $expect_str = strtolower(strval($expected));

        switch ($operator) {
            case 'is':
            case '==':
                if ($is_array) {
                    $actual_lower = array_map('strtolower', $actual);
                    return in_array($expect_str, $actual_lower);
                }
                return $actual_str === $expect_str;
            case 'is not':
            case '!=':
                if ($is_array) {
                    $actual_lower = array_map('strtolower', $actual);
                    return !in_array($expect_str, $actual_lower);
                }
                return $actual_str !== $expect_str;
            case 'contains':
                return strpos($actual_str, $expect_str) !== false;
            case 'does not contain':
                return strpos($actual_str, $expect_str) === false;
            case 'is empty':
                return $actual_str === '';
            case 'is not empty':
                return $actual_str !== '';
            case 'greater than':
            case '>':
                return floatval($actual) > floatval($expected);
            case 'less than':
            case '<':
                return floatval($actual) < floatval($expected);
            default:
                return false;
        }
    }

    /**
     * Filter triggered before a submission is inserted into the database.
     * Use this hook to strip conditionally hidden fields.
     *
     * @param array  $insertData The data to be inserted.
     * @param array  $data       The raw submitted data.
     * @param object $form       The form object.
     * @return array Modified insert data.
     */
    public function before_insert_submission($insertData, $data, $form)
    {
        $hidden_fields = $this->ff_conditional_evaluate($form->id, $data);

        if (empty($hidden_fields)) {
            return $insertData;
        }

        $response_data = json_decode($insertData['response'], true);

        // Strip hidden fields to prevent spoofing
        foreach ($hidden_fields as $field_name) {
            if (isset($response_data[$field_name])) {
                unset($response_data[$field_name]);
            }
            if (isset($data[$field_name])) {
                unset($data[$field_name]); // modifying raw data array reference if needed
            }
        }

        $insertData['response'] = wp_json_encode($response_data);
        return $insertData;
    }

    /**
     * Filter triggered during form validation.
     *
     * @param array $errors    Current validation errors.
     * @param array $data      Submitted data.
     * @param object $form     The form object.
     * @param array $fields    Form fields map.
     * @return array Modified errors.
     */
    public function validate_submission($errors, $data, $form, $fields)
    {
        if (empty($errors))
            return $errors;

        $hidden_fields = $this->ff_conditional_evaluate($form->id, $data);
        foreach ($hidden_fields as $field_name) {
            if (isset($errors[$field_name])) {
                unset($errors[$field_name]);
            }
        }
        return $errors;
    }

    /**
     * Add a global settings tab for the addon.
     *
     * @param array $components Existing components.
     * @return array Modified components.
     */
    public function add_global_settings_tab($components)
    {
        $components['logic_essentials_addon'] = array(
            'title' => __('Logic Essentials', 'logic-essentials-fluent-forms-addon'),
            'name' => __('Logic Essentials Addon Settings', 'logic-essentials-fluent-forms-addon'),
            'path' => '' // Path to component
        );
        return $components;
    }

    /**
     * Add a "Rules" tab to the Form Editor Settings.
     *
     * @param array $menus Existing menus.
     * @param int   $form_id The form ID.
     * @return array Modified menus.
     */
    public function add_form_settings_menu($menus, $form_id)
    {
        $menus['logic_essentials_rules'] = array(
            'title' => __('Rules', 'logic-essentials-fluent-forms-addon'),
            'slug' => 'logic_essentials_rules',
            'route' => '/logic-essentials-rules'
        );
        return $menus;
    }

    /**
     * Add a panel to the field settings in the editor.
     *
     * @param array $field
     */
    public function add_field_settings_panel($field)
    {
        // We will output or register the UI panel for "Conditional Logic (Free Addon)" here.
    }

    /**
     * Register REST API Endpoints mapping to PRD spec.
     */
    public function register_rest_endpoints()
    {
        // Read rules
        register_rest_route('ff-addon/v1', '/form/(?P<id>\d+)/rules', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_rules'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Evaluate rules preview
        register_rest_route('ff-addon/v1', '/form/(?P<id>\d+)/rules/preview', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_preview_rules'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
    }

    /**
     * Permissions callback for REST API.
     */
    public function check_admin_permissions()
    {
        return current_user_can('manage_options') || current_user_can('fluentform_forms_manager');
    }

    /**
     * REST handler for getting rules.
     */
    public function rest_get_rules($request)
    {
        $form_id = $request->get_param('id');
        $rules = $this->get_form_rules($form_id);
        return rest_ensure_response(array('rules' => $rules));
    }

    /**
     * REST handler for evaluating rules preview.
     */
    public function rest_preview_rules($request)
    {
        $form_id = $request->get_param('id');
        $sample_data = $request->get_param('data');
        if (!is_array($sample_data))
            $sample_data = array();

        $hidden_fields = $this->ff_conditional_evaluate($form_id, $sample_data);
        return rest_ensure_response(array('hidden' => $hidden_fields));
    }
}
