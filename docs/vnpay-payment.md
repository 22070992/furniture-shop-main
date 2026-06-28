# VNPay Payment Setup

## Local sandbox

1. Fill these variables in `.env`:

```env
VNPAY_ENV=sandbox
VNPAY_TMN_CODE=your_sandbox_tmn_code
VNPAY_HASH_SECRET=your_sandbox_hash_secret
VNPAY_PAYMENT_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://127.0.0.1:8000/payment/vnpay/return
VNPAY_IPN_URL=https://your-public-tunnel/payment/vnpay/ipn
VNPAY_VERSION=2.1.0
VNPAY_LOCALE=vn
VNPAY_CURRENCY=VND
VNPAY_ORDER_TYPE=other
VNPAY_PAYMENT_TIMEOUT=15
```

2. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan serve
```

3. Use a tunnel for IPN:

```bash
ngrok http 8000
```

Then update:

```env
APP_URL=https://your-ngrok-url
VNPAY_RETURN_URL=https://your-ngrok-url/payment/vnpay/return
VNPAY_IPN_URL=https://your-ngrok-url/payment/vnpay/ipn
```

4. Run `php artisan optimize:clear` after changing env.

## Checkout flow

- COD keeps the existing flow and creates an unpaid order.
- VNPay creates an order, order details, and a pending payment.
- Stock is still reserved when the order is created, matching the current project behavior.
- Return URL only shows the customer-facing result.
- IPN is the official callback that marks `payments.status` and `orders.payment_status` as paid, failed, or cancelled.
- Repeated paid IPN callbacks return `RspCode=02` and do not update the order again.

## Laravel Cloud

Add the same `VNPAY_*` variables in Laravel Cloud environment settings.

Use the deployed HTTPS domain:

```env
APP_URL=https://your-domain
VNPAY_RETURN_URL=https://your-domain/payment/vnpay/return
VNPAY_IPN_URL=https://your-domain/payment/vnpay/ipn
```

Run migrations during deployment:

```bash
php artisan migrate --force
```

After changing env values, redeploy or clear config cache.

## Production

Switch only environment variables:

```env
VNPAY_ENV=production
VNPAY_TMN_CODE=production_tmn_code
VNPAY_HASH_SECRET=production_hash_secret
VNPAY_PAYMENT_URL=production_vnpay_payment_url
VNPAY_RETURN_URL=https://your-domain/payment/vnpay/return
VNPAY_IPN_URL=https://your-domain/payment/vnpay/ipn
```

Do not commit secrets to GitHub. Keep `APP_DEBUG=false` in production.
