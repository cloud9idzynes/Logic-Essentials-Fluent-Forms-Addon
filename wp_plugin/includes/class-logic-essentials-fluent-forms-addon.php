<?php
/**
 * Core plugin class.
 *
 * @package Logic_Essentials_Fluent_Forms_Addon
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main Logic_Essentials_Fluent_Forms_Addon Class.
 *
 * @class Logic_Essentials_Fluent_Forms_Addon
 */
final class Logic_Essentials_Fluent_Forms_Addon
{

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = '1.0.0';

    /**
     * The single instance of the class.
     *
     * @var Logic_Essentials_Fluent_Forms_Addon|null
     */
    protected static $_instance = null;

    /**
     * Main Instance.
     *
     * Ensures only one instance of Logic_Essentials_Fluent_Forms_Addon is loaded or can be loaded.
     *
     * @return Logic_Essentials_Fluent_Forms_Addon Main instance.
     */
    public static function get_instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->define_constants();
        $this->includes();
    }

    /**
     * Initialize the plugin hooks.
     */
    public function init()
    {
        // Hooks and setup functionality
        $this->init_hooks();
    }

    /**
     * Define constants.
     */
    private function define_constants()
    {
        // Add specific constants here if needed.
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    private function includes()
    {
        require_once LE_FFA_PLUGIN_DIR . 'includes/class-le-ffa-integration.php';
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks()
    {
        LE_FFA_Integration::get_instance();
    }

}
