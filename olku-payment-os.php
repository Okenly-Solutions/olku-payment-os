<?php
/**
 * Plugin Name: Olku Payment OS
 * Plugin URI: https://okenlysolutions.com/olku-payment-os
 * Description: Extensible payment gateway plugin for WooCommerce supporting TaraMoney and other payment providers
 * Version: 1.0.0
 * Author: Okenly Solutions
 * Author URI: https://okenlysolutions.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: olku-payment-os
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package OlkuPaymentOS
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('OLKU_PAYMENT_OS_VERSION', '1.0.0');
define('OLKU_PAYMENT_OS_PLUGIN_FILE', __FILE__);
define('OLKU_PAYMENT_OS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OLKU_PAYMENT_OS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OLKU_PAYMENT_OS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Olku_Payment_OS {
    /**
     * The single instance of the class
     *
     * @var Olku_Payment_OS
     */
    protected static $_instance = null;

    /**
     * Main Olku Payment OS Instance
     *
     * @static
     * @return Olku_Payment_OS - Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'), 0);
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
        add_filter('plugin_action_links_' . OLKU_PAYMENT_OS_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin textdomain
        load_plugin_textdomain('olku-payment-os', false, dirname(OLKU_PAYMENT_OS_PLUGIN_BASENAME) . '/languages');

        // Include required files
        $this->includes();

        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin_includes();
        }

        do_action('olku_payment_os_init');
    }

    /**
     * Include required core files
     */
    private function includes() {
        // Core abstracts and interfaces
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/abstracts/abstract-olku-payment-gateway.php';
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/interfaces/interface-olku-payment-gateway.php';

        // Gateway factory
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/class-olku-payment-gateway-factory.php';

        // Utilities
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/class-olku-payment-logger.php';
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/class-olku-payment-api-client.php';

        // Gateway implementations
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/gateways/class-olku-gateway-taramoney.php';

        // Webhook handler
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/class-olku-payment-webhook-handler.php';
    }

    /**
     * Include required admin files
     */
    private function admin_includes() {
        require_once OLKU_PAYMENT_OS_PLUGIN_DIR . 'includes/admin/class-olku-payment-admin.php';
    }

    /**
     * Add payment gateways to WooCommerce
     *
     * @param array $gateways
     * @return array
     */
    public function add_gateways($gateways) {
        $olku_gateways = apply_filters('olku_payment_os_gateways', array(
            'Olku_Gateway_TaraMoney',
        ));

        return array_merge($gateways, $olku_gateways);
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p>
                <?php
                echo sprintf(
                    /* translators: %s: WooCommerce plugin link */
                    esc_html__('Olku Payment OS requires WooCommerce to be installed and active. You can download %s here.', 'olku-payment-os'),
                    '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show action links on the plugin screen
     *
     * @param array $links
     * @return array
     */
    public function plugin_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'olku-payment-os') . '</a>',
            'docs' => '<a href="https://okenlysolutions.com/docs/olku-payment-os" target="_blank">' . __('Documentation', 'olku-payment-os') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Get the plugin url
     *
     * @return string
     */
    public function plugin_url() {
        return OLKU_PAYMENT_OS_PLUGIN_URL;
    }

    /**
     * Get the plugin path
     *
     * @return string
     */
    public function plugin_path() {
        return OLKU_PAYMENT_OS_PLUGIN_DIR;
    }
}

/**
 * Main instance of Olku Payment OS
 *
 * @return Olku_Payment_OS
 */
function Olku_Payment_OS() {
    return Olku_Payment_OS::instance();
}

// Initialize the plugin
Olku_Payment_OS();
