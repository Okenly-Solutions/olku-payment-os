<?php
/**
 * TaraMoney Payment Gateway
 *
 * Implements TaraMoney payment gateway integration for WooCommerce
 * Supports Order Links (WhatsApp, Telegram, SMS) and Mobile Money payments
 *
 * @package OlkuPaymentOS
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Class Olku_Gateway_TaraMoney
 */
class Olku_Gateway_TaraMoney extends Abstract_Olku_Payment_Gateway {
    /**
     * TaraMoney API base URL
     */
    const API_BASE_URL = 'https://www.dklo.co/api/tara';

    /**
     * Business ID
     *
     * @var string
     */
    private $business_id;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'taramoney';
        $this->icon = apply_filters('olku_taramoney_icon', OLKU_PAYMENT_OS_PLUGIN_URL . 'assets/images/taramoney-logo.png');
        $this->has_fields = false;
        $this->method_title = __('TaraMoney', 'olku-payment-os');
        $this->method_description = __('Accept payments via TaraMoney (WhatsApp, Telegram, Mobile Money)', 'olku-payment-os');
        $this->supports = array(
            'products',
            'refunds',
        );

        parent::__construct();

        // Get setting values
        $this->api_key = $this->test_mode
            ? $this->get_option('test_api_key')
            : $this->get_option('api_key');

        $this->business_id = $this->test_mode
            ? $this->get_option('test_business_id')
            : $this->get_option('business_id');

        $this->webhook_secret = $this->get_option('webhook_secret');

        // Initialize API client
        $this->api_client = new Olku_Payment_API_Client(
            self::API_BASE_URL,
            array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            $this->logger
        );
    }

    /**
     * Get gateway-specific form fields
     *
     * @return array
     */
    protected function get_gateway_form_fields() {
        return array(
            'api_credentials_title' => array(
                'title' => __('API Credentials', 'olku-payment-os'),
                'type' => 'title',
                'description' => __('Enter your TaraMoney API credentials. Get them from your TaraMoney dashboard.', 'olku-payment-os'),
            ),
            'api_key' => array(
                'title' => __('Live API Key', 'olku-payment-os'),
                'type' => 'text',
                'description' => __('Your TaraMoney live API key', 'olku-payment-os'),
                'default' => '',
                'desc_tip' => true,
            ),
            'business_id' => array(
                'title' => __('Live Business ID', 'olku-payment-os'),
                'type' => 'text',
                'description' => __('Your TaraMoney live business ID', 'olku-payment-os'),
                'default' => '',
                'desc_tip' => true,
            ),
            'test_api_key' => array(
                'title' => __('Test API Key', 'olku-payment-os'),
                'type' => 'text',
                'description' => __('Your TaraMoney sandbox API key', 'olku-payment-os'),
                'default' => '',
                'desc_tip' => true,
            ),
            'test_business_id' => array(
                'title' => __('Test Business ID', 'olku-payment-os'),
                'type' => 'text',
                'description' => __('Your TaraMoney sandbox business ID', 'olku-payment-os'),
                'default' => '',
                'desc_tip' => true,
            ),
            'webhook_secret' => array(
                'title' => __('Webhook Secret', 'olku-payment-os'),
                'type' => 'text',
                'description' => __('Your TaraMoney webhook secret for signature verification', 'olku-payment-os'),
                'default' => '',
                'desc_tip' => true,
            ),
            'payment_options_title' => array(
                'title' => __('Payment Options', 'olku-payment-os'),
                'type' => 'title',
                'description' => __('Configure payment methods available to customers', 'olku-payment-os'),
            ),
            'enable_order_links' => array(
                'title' => __('Enable Order Links', 'olku-payment-os'),
                'type' => 'checkbox',
                'label' => __('Allow payments via WhatsApp, Telegram, and SMS', 'olku-payment-os'),
                'default' => 'yes',
            ),
            'enable_mobile_money' => array(
                'title' => __('Enable Mobile Money', 'olku-payment-os'),
                'type' => 'checkbox',
                'label' => __('Allow direct Mobile Money payments (Orange Money, MTN Mobile Money)', 'olku-payment-os'),
                'default' => 'yes',
            ),
            'webhook_info' => array(
                'title' => __('Webhook URL', 'olku-payment-os'),
                'type' => 'title',
                'description' => sprintf(
                    __('Configure this webhook URL in your TaraMoney dashboard: %s', 'olku-payment-os'),
                    '<br><code>' . $this->get_webhook_url() . '</code>'
                ),
            ),
        );
    }

    /**
     * Process payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'result' => 'error',
                'message' => __('Invalid order', 'olku-payment-os'),
            );
        }

        // Check if API credentials are configured
        if (empty($this->api_key) || empty($this->business_id)) {
            wc_add_notice(__('Payment gateway is not properly configured. Please contact the store administrator.', 'olku-payment-os'), 'error');
            return array(
                'result' => 'error',
                'message' => __('Gateway not configured', 'olku-payment-os'),
            );
        }

        $this->log_info('Processing payment', array('order_id' => $order_id));

        // Determine payment method (order links or mobile money)
        $enable_mobile_money = 'yes' === $this->get_option('enable_mobile_money', 'yes');
        $phone_number = isset($_POST['taramoney_phone_number']) ? sanitize_text_field($_POST['taramoney_phone_number']) : '';

        // If phone number is provided and mobile money is enabled, use mobile money
        if ($enable_mobile_money && !empty($phone_number)) {
            return $this->process_mobile_money_payment($order, $phone_number);
        }

        // Otherwise, use order links
        return $this->process_order_link_payment($order);
    }

    /**
     * Process order link payment
     *
     * @param WC_Order $order
     * @return array
     */
    private function process_order_link_payment($order) {
        $this->log_info('Creating TaraMoney order link', array('order_id' => $order->get_id()));

        $product_name = $this->get_order_product_name($order);
        $product_description = $this->get_order_description($order);

        $request_data = array(
            'apiKey' => $this->api_key,
            'businessId' => $this->business_id,
            'productId' => (string) $order->get_id(),
            'productName' => $product_name,
            'productPrice' => (int) $order->get_total(),
            'productDescription' => $product_description,
            'productPictureUrl' => $this->get_order_product_image($order),
            'returnUrl' => $this->get_return_url($order),
            'webHookUrl' => $this->get_webhook_url(),
        );

        $response = $this->api_client->post('order', $request_data);

        if (is_wp_error($response)) {
            $this->log_error('Order link creation failed', array(
                'error' => $response->get_error_message(),
            ));

            wc_add_notice(__('Payment initialization failed. Please try again.', 'olku-payment-os'), 'error');

            return array(
                'result' => 'error',
                'message' => $response->get_error_message(),
            );
        }

        $data = $response['data'];

        // Check if order was successful
        $is_success = (isset($data['status']) && $data['status'] === 'SUCCESS') ||
                      (isset($data['error']) && in_array($data['error'], array('API_ORDER_SUCESSFULL', 'API_ORDER_SUCCESSFUL')));

        if (!$is_success) {
            $error_message = isset($data['message']) ? $data['message'] : __('Failed to create payment link', 'olku-payment-os');
            $this->log_error('Order link creation failed', array('response' => $data));

            wc_add_notice($error_message, 'error');

            return array(
                'result' => 'error',
                'message' => $error_message,
            );
        }

        // Save payment links to order meta
        $order->update_meta_data('_taramoney_whatsapp_link', $data['whatsappLink'] ?? '');
        $order->update_meta_data('_taramoney_telegram_link', $data['telegramLink'] ?? '');
        $order->update_meta_data('_taramoney_dikalo_link', $data['dikaloLink'] ?? '');
        $order->update_meta_data('_taramoney_sms_link', $data['smsLink'] ?? '');
        $order->update_meta_data('_taramoney_payment_type', 'order_link');
        $order->save();

        // Mark order as pending payment
        $order->update_status('pending', __('Awaiting TaraMoney payment', 'olku-payment-os'));

        $this->log_info('Order link created successfully', array(
            'order_id' => $order->get_id(),
            'links' => array_keys(array_filter(array(
                'whatsapp' => $data['whatsappLink'] ?? '',
                'telegram' => $data['telegramLink'] ?? '',
                'dikalo' => $data['dikaloLink'] ?? '',
                'sms' => $data['smsLink'] ?? '',
            ))),
        ));

        // Use the first available link for redirect or Dikalo link
        $redirect_url = $data['dikaloLink'] ?? $data['whatsappLink'] ?? $data['telegramLink'] ?? $data['smsLink'] ?? $this->get_return_url($order);

        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
        );
    }

    /**
     * Process mobile money payment
     *
     * @param WC_Order $order
     * @param string $phone_number
     * @return array
     */
    private function process_mobile_money_payment($order, $phone_number) {
        $this->log_info('Creating TaraMoney mobile money payment', array(
            'order_id' => $order->get_id(),
            'phone_number' => $phone_number,
        ));

        $product_name = $this->get_order_product_name($order);

        $request_data = array(
            'apiKey' => $this->api_key,
            'businessId' => $this->business_id,
            'productId' => (string) $order->get_id(),
            'productName' => $product_name,
            'productPrice' => (int) $order->get_total(),
            'phoneNumber' => $phone_number,
            'webHookUrl' => $this->get_webhook_url(),
        );

        $response = $this->api_client->post('cmmobile', $request_data);

        if (is_wp_error($response)) {
            $this->log_error('Mobile money payment failed', array(
                'error' => $response->get_error_message(),
            ));

            wc_add_notice(__('Mobile money payment initialization failed. Please try again.', 'olku-payment-os'), 'error');

            return array(
                'result' => 'error',
                'message' => $response->get_error_message(),
            );
        }

        $data = $response['data'];

        if (!isset($data['status']) || $data['status'] !== 'SUCCESS') {
            $error_message = isset($data['message']) ? $data['message'] : __('Failed to initiate mobile money payment', 'olku-payment-os');
            $this->log_error('Mobile money payment failed', array('response' => $data));

            wc_add_notice($error_message, 'error');

            return array(
                'result' => 'error',
                'message' => $error_message,
            );
        }

        // Save mobile money details to order meta
        $order->update_meta_data('_taramoney_ussd_code', $data['ussdCode'] ?? '');
        $order->update_meta_data('_taramoney_vendor', $data['vendor'] ?? '');
        $order->update_meta_data('_taramoney_phone_number', $phone_number);
        $order->update_meta_data('_taramoney_payment_type', 'mobile_money');
        $order->update_meta_data('_taramoney_payment_id', $data['paymentId'] ?? '');
        $order->save();

        // Mark order as pending payment
        $ussd_code = $data['ussdCode'] ?? '';
        $vendor = $data['vendor'] ?? '';

        $order->update_status(
            'pending',
            sprintf(
                __('Awaiting mobile money payment. Dial %s on your %s phone.', 'olku-payment-os'),
                $ussd_code,
                $vendor
            )
        );

        $this->log_info('Mobile money payment initiated', array(
            'order_id' => $order->get_id(),
            'ussd_code' => $ussd_code,
            'vendor' => $vendor,
        ));

        // Add notice for customer
        wc_add_notice(
            sprintf(
                __('Please dial %s on your mobile phone to complete payment.', 'olku-payment-os'),
                '<strong>' . $ussd_code . '</strong>'
            ),
            'success'
        );

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Process webhook
     *
     * @param array $data
     * @return bool|WP_Error
     */
    public function process_webhook($data) {
        $this->log_info('Processing webhook', array('data' => $data));

        // Extract payment information
        $payment_id = $data['paymentId'] ?? '';
        $status = $data['status'] ?? '';
        $business_id = $data['businessId'] ?? '';

        // Verify business ID
        if ($business_id !== $this->business_id) {
            return new WP_Error('invalid_business', 'Invalid business ID in webhook');
        }

        // Find order by payment ID or collection ID
        $order_id = $this->find_order_by_payment_id($payment_id, $data);

        if (!$order_id) {
            return new WP_Error('order_not_found', 'Order not found for payment ID: ' . $payment_id);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order ID: ' . $order_id);
        }

        // Process payment based on status
        if ($status === 'SUCCESS') {
            $this->process_successful_payment($order, $data);
        } else {
            $this->process_failed_payment($order, $data);
        }

        return true;
    }

    /**
     * Find order by payment ID
     *
     * @param string $payment_id
     * @param array $data Webhook data
     * @return int|null Order ID or null if not found
     */
    private function find_order_by_payment_id($payment_id, $data) {
        // First try to find by collection ID (for mobile money)
        if (isset($data['collectionId'])) {
            $orders = wc_get_orders(array(
                'meta_key' => '_taramoney_payment_id',
                'meta_value' => $payment_id,
                'limit' => 1,
            ));

            if (!empty($orders)) {
                return $orders[0]->get_id();
            }
        }

        // Try to extract order ID from productId
        if (isset($data['productId'])) {
            $order_id = (int) $data['productId'];
            if ($order_id > 0 && wc_get_order($order_id)) {
                return $order_id;
            }
        }

        return null;
    }

    /**
     * Process successful payment
     *
     * @param WC_Order $order
     * @param array $data
     */
    private function process_successful_payment($order, $data) {
        if ($order->has_status(array('processing', 'completed'))) {
            $this->log_info('Order already processed', array('order_id' => $order->get_id()));
            return;
        }

        $payment_id = $data['paymentId'] ?? '';
        $transaction_code = $data['transactionCode'] ?? $payment_id;

        $order->add_order_note(
            sprintf(
                __('TaraMoney payment completed. Transaction ID: %s', 'olku-payment-os'),
                $transaction_code
            )
        );

        $this->mark_order_as_processing($order, $transaction_code);

        $this->log_info('Payment completed successfully', array(
            'order_id' => $order->get_id(),
            'transaction_id' => $transaction_code,
        ));
    }

    /**
     * Process failed payment
     *
     * @param WC_Order $order
     * @param array $data
     */
    private function process_failed_payment($order, $data) {
        $status = $data['status'] ?? 'FAILED';
        $message = sprintf(
            __('TaraMoney payment failed. Status: %s', 'olku-payment-os'),
            $status
        );

        $this->mark_order_as_failed($order, $message);

        $this->log_error('Payment failed', array(
            'order_id' => $order->get_id(),
            'status' => $status,
        ));
    }

    /**
     * Get order product name
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_order_product_name($order) {
        $items = $order->get_items();

        if (count($items) === 1) {
            $item = reset($items);
            return $item->get_name();
        }

        return sprintf(__('Order #%s', 'olku-payment-os'), $order->get_order_number());
    }

    /**
     * Get order description
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_order_description($order) {
        $items = $order->get_items();
        $item_names = array();

        foreach ($items as $item) {
            $item_names[] = $item->get_name() . ' x ' . $item->get_quantity();
        }

        return implode(', ', $item_names);
    }

    /**
     * Get order product image
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_order_product_image($order) {
        $items = $order->get_items();

        if (empty($items)) {
            return '';
        }

        $item = reset($items);
        $product = $item->get_product();

        if (!$product) {
            return '';
        }

        $image_id = $product->get_image_id();

        if (!$image_id) {
            return '';
        }

        $image_url = wp_get_attachment_image_url($image_id, 'medium');

        return $image_url ? $image_url : '';
    }
}
