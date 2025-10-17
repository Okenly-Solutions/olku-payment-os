# Olku Payment OS - Quick Start Guide

Get up and running with Olku Payment OS in 5 minutes!

## 📦 Installation

### Step 1: Install Plugin

Upload `olku-payment-os` folder to `/wp-content/plugins/` and activate in WordPress.

### Step 2: Get TaraMoney Credentials

1. Sign up at [TaraMoney Dashboard](https://www.dklo.co/dashboard)
2. Get your:
   - API Key
   - Business ID
   - Webhook Secret

### Step 3: Configure Gateway

1. Go to **WooCommerce → Settings → Payments**
2. Enable **TaraMoney**
3. Click **Manage**
4. Enter API credentials
5. Copy webhook URL
6. Add webhook URL to TaraMoney dashboard
7. Save changes

## 🎯 Test Payment

1. Add product to cart
2. Go to checkout
3. Select **TaraMoney** payment
4. Complete payment
5. Check order status updates

## 🔧 Configuration Options

### Test Mode
- Enable for sandbox testing
- Use test API credentials
- No real money charged

### Payment Methods
- **Order Links**: WhatsApp, Telegram, SMS
- **Mobile Money**: Orange, MTN (Cameroon)

## 📝 Webhook URL

Your webhook URL will be:
```
https://yourdomain.com/?wc-api=olku_webhook_taramoney
```

Add this to TaraMoney dashboard for automatic payment confirmations.

## 🐛 Troubleshooting

**Gateway not showing?**
- Verify WooCommerce is active
- Check API credentials are configured
- Enable the gateway in settings

**Webhook not working?**
- Ensure HTTPS is enabled
- Verify webhook URL is correct
- Check webhook secret matches

**Need help?**
- 📖 Read [full documentation](README.md)
- 📧 Email: support@okenlysolutions.com

## 🚀 What's Next?

- Add more products
- Test all payment flows
- Enable logging for debugging
- Review order management
- Go live!

---

**Happy selling with Olku Payment OS! 💰**
