<?php
/**
 * Admin Class
 *
 * Handles admin functionality for the plugin
 *
 * @package OlkuPaymentOS
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Class Olku_Payment_Admin
 */
class Olku_Payment_Admin {
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_payment_gateways_settings', array($this, 'add_global_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Add global plugin settings to WooCommerce payment settings
     *
     * @param array $settings
     * @return array
     */
    public function add_global_settings($settings) {
        $olku_settings = array(
            array(
                'title' => __('Olku Payment OS Settings', 'olku-payment-os'),
                'type' => 'title',
                'desc' => __('Global settings for Olku Payment OS plugin', 'olku-payment-os'),
                'id' => 'olku_payment_os_settings',
            ),
            array(
                'title' => __('Enable Logging', 'olku-payment-os'),
                'desc' => __('Enable detailed logging for payment gateway operations', 'olku-payment-os'),
                'id' => 'olku_payment_os_enable_logging',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Log Level', 'olku-payment-os'),
                'desc' => __('Select the level of detail for logs', 'olku-payment-os'),
                'id' => 'olku_payment_os_log_level',
                'default' => 'info',
                'type' => 'select',
                'options' => array(
                    'debug' => __('Debug (Most Detailed)', 'olku-payment-os'),
                    'info' => __('Info (Recommended)', 'olku-payment-os'),
                    'warning' => __('Warning', 'olku-payment-os'),
                    'error' => __('Error (Least Detailed)', 'olku-payment-os'),
                ),
            ),
            array(
                'type' => 'sectionend',
                'id' => 'olku_payment_os_settings',
            ),
        );

        return array_merge($settings, $olku_settings);
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on WooCommerce settings pages
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'olku-payment-os-admin',
            OLKU_PAYMENT_OS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OLKU_PAYMENT_OS_VERSION
        );

        wp_enqueue_script(
            'olku-payment-os-admin',
            OLKU_PAYMENT_OS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            OLKU_PAYMENT_OS_VERSION,
            true
        );

        wp_localize_script('olku-payment-os-admin', 'olkuPaymentOS', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('olku_payment_os_admin'),
        ));
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="notice notice-error">
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
            return;
        }

        // Check if any gateway is configured
        $gateways = WC()->payment_gateways()->payment_gateways();
        $olku_gateways = array();

        foreach ($gateways as $gateway) {
            if ($gateway instanceof Abstract_Olku_Payment_Gateway) {
                $olku_gateways[] = $gateway;
            }
        }

        if (empty($olku_gateways)) {
            return;
        }

        $configured_count = 0;
        foreach ($olku_gateways as $gateway) {
            if ($gateway->enabled === 'yes') {
                $configured_count++;
            }
        }

        if ($configured_count === 0) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php
                    echo sprintf(
                        /* translators: %s: Settings page link */
                        esc_html__('Olku Payment OS is installed but no payment gateways are enabled. %s', 'olku-payment-os'),
                        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_html__('Configure payment gateways', 'olku-payment-os') . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}

// Initialize admin
new Olku_Payment_Admin();
