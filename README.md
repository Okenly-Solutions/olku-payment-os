# Olku Payment OS

**Extensible WooCommerce payment gateway plugin supporting TaraMoney and other payment providers**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/Okenly-Solutions/olku-payment-os)
[![WordPress](https://img.shields.io/badge/wordpress-5.8+-green.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/woocommerce-5.0+-purple.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)](LICENSE)

---

## ☕ Support This Project

If you find this plugin useful, consider buying me a coffee! Your support helps maintain and improve this project.

**[☕ Buy Me a Coffee via TaraMoney](https://www.taramoney.com/pay/53857)**

---

## 📚 Documentation

- 📘 **[README](README.md)** - This file (complete overview)
- 🚀 **[Quick Start Guide](QUICK_START.md)** - Get started in 5 minutes
- 🔧 **[Installation Guide](INSTALLATION.md)** - Detailed setup instructions
- 👨‍💻 **[Developer Guide](EXTENDING.md)** - How to add custom payment gateways
- 📝 **[Changelog](CHANGELOG.md)** - Version history and updates
- 📦 **[Release Guide](RELEASE.md)** - How to create releases
- 🐙 **[GitHub Release Guide](GITHUB_RELEASE_GUIDE.md)** - Publishing on GitHub

---

## Overview

Olku Payment OS is a robust, extensible payment gateway plugin for WooCommerce that provides a unified framework for integrating multiple payment providers. The plugin features a clean architecture with abstract classes and interfaces, making it easy to add support for new payment gateways.

### Key Features

- **Extensible Architecture**: Built with OOP principles, interfaces, and abstract classes
- **TaraMoney Integration**: Full support for TaraMoney payment gateway
  - Order Links (WhatsApp, Telegram, SMS, Dikalo)
  - Mobile Money (Orange Money, MTN Mobile Money)
  - Automatic webhook handling
- **Secure Webhook Processing**: Built-in webhook signature validation
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **Developer Friendly**: Easy to extend with new payment gateways
- **WooCommerce Native**: Follows WooCommerce payment gateway API standards

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- HTTPS enabled (required for production webhooks)

## Installation

### From GitHub

1. Download the plugin from the releases page
2. Extract the zip file to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to WooCommerce → Settings → Payments to configure

### Manual Installation

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/Okenly-Solutions/olku-payment-os.git
```

Then activate the plugin in WordPress admin.

## Configuration

### TaraMoney Gateway Setup

1. Navigate to **WooCommerce → Settings → Payments**
2. Enable **TaraMoney** gateway
3. Click **Manage** to configure settings

#### API Credentials

**Production:**
- **Live API Key**: Your TaraMoney production API key
- **Live Business ID**: Your TaraMoney production business ID

**Testing:**
- **Test API Key**: Your TaraMoney sandbox API key
- **Test Business ID**: Your TaraMoney sandbox business ID
- **Test Mode**: Enable to use sandbox credentials

Get your API credentials from [TaraMoney Dashboard](https://www.dklo.co/dashboard)

#### Webhook Configuration

Copy the webhook URL displayed in the settings and add it to your TaraMoney dashboard:

```
https://yourdomain.com/?wc-api=olku_webhook_taramoney
```

**Important**: Webhooks require HTTPS in production.

#### Payment Options

- **Enable Order Links**: Allow payments via WhatsApp, Telegram, SMS
- **Enable Mobile Money**: Allow direct Mobile Money payments

## Usage

### For Store Administrators

Once configured, TaraMoney will appear as a payment option during checkout. Customers can choose to:

1. **Pay via Order Links**: Receive payment links through WhatsApp, Telegram, or SMS
2. **Pay via Mobile Money**: Enter phone number and receive USSD code to dial

### For Developers

#### Adding a New Payment Gateway

1. Create a new gateway class extending `Abstract_Olku_Payment_Gateway`:

```php
<?php
class Olku_Gateway_YourGateway extends Abstract_Olku_Payment_Gateway {

    public function __construct() {
        $this->id = 'your_gateway';
        $this->method_title = __('Your Gateway', 'olku-payment-os');
        $this->method_description = __('Description here', 'olku-payment-os');

        parent::__construct();
    }

    protected function get_gateway_form_fields() {
        return array(
            'api_key' => array(
                'title' => __('API Key', 'olku-payment-os'),
                'type' => 'text',
                'default' => '',
            ),
            // Add more fields...
        );
    }

    public function process_payment($order_id) {
        // Implement payment processing
    }

    public function process_webhook($data) {
        // Implement webhook processing
    }
}
```

2. Register the gateway in the factory:

```php
Olku_Payment_Gateway_Factory::register_gateway('your_gateway', 'Olku_Gateway_YourGateway');
```

3. Add the gateway to the plugin's gateway list:

```php
add_filter('olku_payment_os_gateways', function($gateways) {
    $gateways[] = 'Olku_Gateway_YourGateway';
    return $gateways;
});
```

#### API Client Usage

The plugin includes a built-in HTTP client for API requests:

```php
$api_client = new Olku_Payment_API_Client(
    'https://api.example.com',
    array('Authorization' => 'Bearer ' . $api_key),
    $logger
);

// Make POST request
$response = $api_client->post('payments', array(
    'amount' => 1000,
    'currency' => 'XAF',
));

if (!is_wp_error($response)) {
    $data = $response['data'];
    // Process response
}
```

#### Logging

Use the logger instance for debugging:

```php
$this->log_info('Payment initiated', array(
    'order_id' => $order_id,
    'amount' => $amount,
));

$this->log_error('Payment failed', array(
    'error' => $error_message,
));
```

View logs in **WooCommerce → Status → Logs**.

## Architecture

### Plugin Structure

```
olku-payment-os/
├── includes/
│   ├── abstracts/
│   │   └── abstract-olku-payment-gateway.php
│   ├── interfaces/
│   │   └── interface-olku-payment-gateway.php
│   ├── gateways/
│   │   └── class-olku-gateway-taramoney.php
│   ├── admin/
│   │   └── class-olku-payment-admin.php
│   ├── class-olku-payment-logger.php
│   ├── class-olku-payment-api-client.php
│   ├── class-olku-payment-gateway-factory.php
│   └── class-olku-payment-webhook-handler.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── languages/
├── README.md
├── LICENSE
└── olku-payment-os.php
```

### Design Patterns

- **Factory Pattern**: Gateway instantiation
- **Strategy Pattern**: Different payment methods
- **Template Method**: Abstract gateway class
- **Dependency Injection**: Logger and API client

## TaraMoney Integration

### Supported Features

#### Order Link Payments
- WhatsApp payment links
- Telegram payment links
- SMS payment links
- Dikalo web payment

#### Mobile Money Payments
- Orange Money (Cameroon)
- MTN Mobile Money (Cameroon)
- USSD code generation

#### Webhook Events
- Payment success notifications
- Payment failure notifications
- Transaction status updates

### API Endpoints Used

- `POST /api/tara/order` - Create order payment links
- `POST /api/tara/cmmobile` - Initiate mobile money payment

### Currency Support

TaraMoney primarily works with XAF (Central African CFA Franc). The plugin automatically converts amounts if needed.

## Troubleshooting

### Common Issues

#### Gateway Not Showing at Checkout

**Solution:**
1. Ensure the gateway is enabled in WooCommerce → Settings → Payments
2. Check that API credentials are properly configured
3. Verify WooCommerce is active and up to date

#### Webhook Not Working

**Solution:**
1. Ensure your site uses HTTPS (required for production)
2. Verify webhook URL is correctly configured in payment provider dashboard
3. Check webhook secret matches in both locations
4. Review logs in WooCommerce → Status → Logs

#### Payment Status Not Updating

**Solution:**
1. Check webhook is receiving requests (review logs)
2. Verify webhook signature validation (if enabled)
3. Ensure order ID can be found from webhook payload
4. Check for PHP errors in WordPress debug log

### Debug Mode

Enable debug logging:

1. Go to **WooCommerce → Settings → Payments**
2. Scroll to **Olku Payment OS Settings**
3. Enable **Enable Logging**
4. Set **Log Level** to **Debug**

View logs at: **WooCommerce → Status → Logs**

## Security

- All API credentials are stored securely in WordPress options
- Webhook signature validation prevents unauthorized requests
- Sensitive data is sanitized before logging
- HTTPS required for production webhooks
- Follows WordPress and WooCommerce security best practices

## Extending the Plugin

### Custom Gateway Example

See [EXTENDING.md](docs/EXTENDING.md) for detailed examples of creating custom payment gateways.

### Hooks and Filters

**Actions:**
- `olku_payment_os_init` - Fires after plugin initialization
- `olku_payment_os_register_webhooks` - Register custom webhook handlers

**Filters:**
- `olku_payment_os_gateways` - Modify registered gateway classes
- `olku_taramoney_icon` - Customize TaraMoney gateway icon

## Changelog

### 1.0.0 - 2025-01-17

- Initial release
- TaraMoney gateway integration
- Order link payments support
- Mobile money payments support
- Webhook handling
- Extensible architecture
- Comprehensive logging

## Support

- **Documentation**: [https://github.com/Okenly-Solutions/olku-payment-os](https://github.com/Okenly-Solutions/olku-payment-os)
- **Issues**: [GitHub Issues](https://github.com/Okenly-Solutions/olku-payment-os/issues)
- **Email**: support@okenlysolutions.com

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [Okenly Solutions](https://okenlysolutions.com)

## Roadmap

- [ ] Support for additional payment gateways (Stripe, PayPal, etc.)
- [ ] Recurring payments support
- [ ] Payment analytics dashboard
- [ ] Multi-currency support
- [ ] Subscription billing integration
- [ ] Mobile app SDK

---

**Made with ❤️ by Okenly Solutions**
