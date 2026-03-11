<?php
/**
 * Plugin Name: Logic Essentials Fluent Forms Addon
 * Description: An essential integration addon for Fluent Forms.
 * Version: 1.0.0
 * Author: Logic Essentials
 * Author URI: https://example.com
 * Text Domain: logic-essentials-fluent-forms-addon
 * Domain Path: /languages/
 *
 * @package Logic_Essentials_Fluent_Forms_Addon
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('LE_FFA_VERSION', '1.0.0');
define('LE_FFA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LE_FFA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The main core of the plugin.
 */
require_once LE_FFA_PLUGIN_DIR . 'includes/class-logic-essentials-fluent-forms-addon.php';

/**
 * Initialize the plugin.
 */
function run_logic_essentials_fluent_forms_addon()
{
    // Make sure Fluent Forms is active before initializing our addon
    if (did_action('fluentform_loaded') || defined('FLUENTFORM')) {
        // Detect Fluent Forms Pro to implement conflict handling
        if (defined('FLUENTFORMPRO') || has_action('fluentform_loaded_pro')) {
            // Pro is active, initialize in compatibility mode
            add_action('admin_notices', 'le_ffa_admin_notice_pro_conflict');
            // We still init the plugin, but the integration class will conditionally disable core hooks.
        }

        $plugin = Logic_Essentials_Fluent_Forms_Addon::get_instance();
        $plugin->init();
    } else {
        // Option to display admin notice that Fluent Forms is required
        add_action('admin_notices', 'le_ffa_admin_notice_missing_fluent_forms');
    }
}
// Init early but after plugins load
add_action('plugins_loaded', 'run_logic_essentials_fluent_forms_addon', 11);

/**
 * Admin notice if Fluent Forms Pro is active.
 */
function le_ffa_admin_notice_pro_conflict()
{
    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        esc_html__('Fluent Forms Pro detected — Logic Essentials Fluent Forms Addon will operate in compatibility mode.', 'logic-essentials-fluent-forms-addon')
    );
}

/**
 * Admin notice if Fluent Forms is not active.
 */
function le_ffa_admin_notice_missing_fluent_forms()
{
    if (isset($_GET['activate']))
        unset($_GET['activate']);
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html__('Logic Essentials Fluent Forms Addon requires Fluent Forms to be installed and active. Please install or activate Fluent Forms.', 'logic-essentials-fluent-forms-addon')
    );
}
