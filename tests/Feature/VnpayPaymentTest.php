<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductDetail;
use App\Models\User;
use App\Services\Payments\VnpayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VnpayPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        $this->configureVnpay();
    }

    private function createTestSchema(): void
    {
        foreach ([
            'payments',
            'order_details',
            'orders',
            'product_discounts',
            'discounts',
            'product_details',
            'products',
            'colors',
            'brands',
            'categories',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('avatar')->default('/images/default_avatar.jpg');
            $table->date('birth_date')->nullable();
            $table->boolean('gender')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('name');
            $table->text('description');
            $table->integer('index')->default(0);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->increments('brand_id');
            $table->string('name');
            $table->text('description');
            $table->integer('index')->default(0);
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->increments('color_id');
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('product_id');
            $table->string('name');
            $table->text('description');
            $table->string('short_description')->nullable();
            $table->string('material')->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('quantities')->default(0);
            $table->unsignedInteger('amount_sold')->default(0);
            $table->unsignedInteger('brand_id');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('product_details', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->text('description');
            $table->unsignedInteger('color_id');
            $table->string('size');
            $table->decimal('original_price', 10, 2);
            $table->unsignedInteger('warranty_month');
            $table->unsignedInteger('quantities');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->increments('discount_id');
            $table->string('title');
            $table->text('description');
            $table->decimal('percentage', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_discounts', function (Blueprint $table) {
            $table->string('sku');
            $table->unsignedInteger('discount_id');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->decimal('total_price', 14, 2);
            $table->boolean('is_paid');
            $table->string('payment_method', 40)->default('cod');
            $table->string('payment_status', 40)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('status');
            $table->string('receiver_name');
            $table->string('address');
            $table->string('phone_number');
            $table->text('note')->nullable();
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->unsignedInteger('order_id');
            $table->string('sku');
            $table->unsignedInteger('quantities');
            $table->decimal('unit_price', 14, 2);
            $table->timestamps();
            $table->primary(['sku', 'order_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_id');
            $table->string('provider', 40);
            $table->string('transaction_reference', 120)->unique();
            $table->string('provider_transaction_id', 120)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 40)->default('pending');
            $table->string('response_code', 20)->nullable();
            $table->string('transaction_status', 20)->nullable();
            $table->string('bank_code', 60)->nullable();
            $table->string('card_type', 60)->nullable();
            $table->string('order_info')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::table('categories')->insert(['category_id' => 1, 'name' => 'Office Chair', 'description' => 'Office chairs']);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Furniture brand']);
        DB::table('colors')->insert(['color_id' => 1, 'name' => 'Black', 'code' => '#111111']);
        DB::table('products')->insert([
            'product_id' => 1,
            'name' => 'Ergo Chair',
            'description' => 'Chair',
            'category_id' => 1,
            'brand_id' => 1,
            'quantities' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_details')->insert([
            'sku' => 'SKU-1',
            'product_id' => 1,
            'name' => 'Ergo Chair Black',
            'description' => 'Chair',
            'color_id' => 1,
            'size' => '60x60x100',
            'original_price' => 1000000,
            'warranty_month' => 12,
            'quantities' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureVnpay(): void
    {
        config([
            'app.url' => 'http://localhost',
            'services.vnpay.tmn_code' => 'TESTTMN',
            'services.vnpay.hash_secret' => 'test-secret',
            'services.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'services.vnpay.return_url' => 'http://localhost/payment/vnpay/return',
            'services.vnpay.ipn_url' => 'http://localhost/payment/vnpay/ipn',
            'services.vnpay.version' => '2.1.0',
            'services.vnpay.locale' => 'vn',
            'services.vnpay.currency' => 'VND',
            'services.vnpay.order_type' => 'other',
            'services.vnpay.payment_timeout' => 15,
            'services.vnpay.timezone' => 'Asia/Ho_Chi_Minh',
        ]);
    }

    private function user(): User
    {
        return User::create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => 'Customer',
            'last_name' => 'One',
            'gender' => 1,
            'is_staff' => false,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    private function checkoutPayload(string $paymentMethod = 'vnpay'): array
    {
        return [
            'receiver_name' => 'Customer One',
            'address' => '123 Test Street',
            'phone_number' => '0901234567',
            'payment_method' => $paymentMethod,
            'checkout' => json_encode([
                ['sku' => 'SKU-1', 'quantities' => 2, 'unit_price' => 1],
            ]),
        ];
    }

    private function createPendingPayment(int $amount = 1000000): Payment
    {
        $user = $this->user();
        $order = Order::create([
            'total_price' => $amount,
            'is_paid' => false,
            'payment_method' => Order::PAYMENT_METHOD_VNPAY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => 0,
            'receiver_name' => 'Customer One',
            'address' => '123 Test Street',
            'phone_number' => '0901234567',
            'customer_id' => $user->user_id,
        ]);

        return Payment::create([
            'order_id' => $order->order_id,
            'provider' => Payment::PROVIDER_VNPAY,
            'transaction_reference' => 'VNP-' . $order->order_id . '-TEST',
            'amount' => $amount,
            'status' => Payment::STATUS_PENDING,
            'order_info' => 'Thanh toan don hang #' . $order->order_id,
        ]);
    }

    private function signedPayload(Payment $payment, array $overrides = []): array
    {
        $forcedHash = $overrides['vnp_SecureHash'] ?? null;
        unset($overrides['vnp_SecureHash']);

        $payload = array_merge([
            'vnp_Amount' => (string) ((int) round((float) $payment->amount) * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_CardType' => 'ATM',
            'vnp_OrderInfo' => $payment->order_info,
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTTMN',
            'vnp_TransactionNo' => '14123456',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $payment->transaction_reference,
        ], $overrides);

        $payload['vnp_SecureHash'] = app(VnpayService::class)->makeSecureHash($payload);
        if ($forcedHash !== null) {
            $payload['vnp_SecureHash'] = $forcedHash;
        }

        return $payload;
    }

    public function test_user_can_create_vnpay_payment_for_valid_order(): void
    {
        $response = $this->actingAs($this->user())->post('/checkout', $this->checkoutPayload('vnpay'));

        $response->assertOk();
        $this->assertStringContainsString('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html', $response->getContent());
        $this->assertDatabaseHas('orders', [
            'payment_method' => Order::PAYMENT_METHOD_VNPAY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'total_price' => 2000000,
        ]);
        $this->assertDatabaseHas('payments', [
            'provider' => Payment::PROVIDER_VNPAY,
            'amount' => 2000000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $this->assertSame(8, ProductDetail::find('SKU-1')->quantities);
    }

    public function test_ajax_vnpay_checkout_returns_redirect_url(): void
    {
        $response = $this->actingAs($this->user())
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->post('/checkout/vnpay', $this->checkoutPayload('vnpay'));

        $response->assertOk()
            ->assertJsonPath('payment_method', Order::PAYMENT_METHOD_VNPAY);

        $this->assertStringContainsString(
            'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            $response->json('redirect_url')
        );

        $this->assertDatabaseHas('orders', [
            'payment_method' => Order::PAYMENT_METHOD_VNPAY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'total_price' => 2000000,
        ]);
    }

    public function test_cod_checkout_still_creates_unpaid_order(): void
    {
        $response = $this->actingAs($this->user())->post('/checkout', $this->checkoutPayload('cash-on-delivery'));

        $response->assertOk();
        $this->assertStringContainsString('/checkout/', $response->getContent());
        $this->assertDatabaseHas('orders', [
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'is_paid' => false,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_url_contains_required_vnpay_fields(): void
    {
        $response = $this->actingAs($this->user())->post('/checkout', $this->checkoutPayload('vnpay'));
        parse_str(parse_url($response->getContent(), PHP_URL_QUERY), $params);

        foreach ([
            'vnp_Version',
            'vnp_Command',
            'vnp_TmnCode',
            'vnp_Amount',
            'vnp_CurrCode',
            'vnp_TxnRef',
            'vnp_OrderInfo',
            'vnp_OrderType',
            'vnp_Locale',
            'vnp_ReturnUrl',
            'vnp_IpAddr',
            'vnp_CreateDate',
            'vnp_ExpireDate',
            'vnp_SecureHash',
        ] as $field) {
            $this->assertArrayHasKey($field, $params);
        }

        $this->assertArrayNotHasKey('vnp_IpnUrl', $params);
    }

    public function test_signature_generation_is_stable(): void
    {
        $service = app(VnpayService::class);
        $params = ['vnp_TxnRef' => 'ABC', 'vnp_Amount' => '100000', 'vnp_ResponseCode' => '00'];

        $this->assertSame($service->makeSecureHash($params), $service->makeSecureHash(array_reverse($params, true)));
    }

    public function test_signature_matches_vnpay_urlencode_style_for_spaced_values(): void
    {
        $service = app(VnpayService::class);
        $params = [
            'vnp_Amount' => '10000000',
            'vnp_OrderInfo' => 'Thanh toan don hang #999',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTTMN',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => 'VNP-DIAG-999',
        ];

        $vnpayStyleHashData = http_build_query($service->signableParams($params), '', '&', PHP_QUERY_RFC1738);
        $vnpayStyleHash = hash_hmac('sha512', $vnpayStyleHashData, (string) config('services.vnpay.hash_secret'));

        $this->assertStringContainsString('+', $vnpayStyleHashData);
        $this->assertSame($vnpayStyleHash, $service->makeSecureHash($params));
        $this->assertTrue($service->verifyCallback($params + ['vnp_SecureHash' => $vnpayStyleHash]));
    }

    public function test_callback_with_invalid_signature_is_rejected(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment, ['vnp_SecureHash' => 'bad']);

        $response = $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload));

        $response->assertOk()->assertJson(['RspCode' => '97']);
        $this->assertDatabaseMissing('orders', ['payment_status' => Order::PAYMENT_STATUS_PAID]);
    }

    public function test_callback_for_missing_order_is_rejected(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment, ['vnp_TxnRef' => 'VNP-MISSING']);

        $response = $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload));

        $response->assertOk()->assertJson(['RspCode' => '01']);
    }

    public function test_callback_with_wrong_amount_does_not_mark_paid(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment, ['vnp_Amount' => '99900']);

        $response = $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload));

        $response->assertOk()->assertJson(['RspCode' => '04']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_FAILED]);
        $this->assertDatabaseHas('orders', ['order_id' => $payment->order_id, 'payment_status' => Order::PAYMENT_STATUS_PENDING]);
    }

    public function test_response_code_other_than_success_does_not_mark_paid(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment, ['vnp_ResponseCode' => '24']);

        $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload))->assertJson(['RspCode' => '00']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_CANCELLED]);
        $this->assertDatabaseHas('orders', ['order_id' => $payment->order_id, 'payment_status' => Order::PAYMENT_STATUS_CANCELLED]);
    }

    public function test_transaction_status_other_than_success_does_not_mark_paid(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment, ['vnp_TransactionStatus' => '02']);

        $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload))->assertJson(['RspCode' => '00']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_FAILED]);
        $this->assertDatabaseHas('orders', ['order_id' => $payment->order_id, 'payment_status' => Order::PAYMENT_STATUS_FAILED]);
    }

    public function test_successful_ipn_updates_payment_and_order(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment);

        $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload))->assertJson(['RspCode' => '00']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'provider_transaction_id' => '14123456',
            'response_code' => '00',
            'transaction_status' => '00',
        ]);
        $this->assertDatabaseHas('orders', [
            'order_id' => $payment->order_id,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'is_paid' => true,
        ]);
    }

    public function test_repeated_ipn_does_not_update_paid_order_again(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment);

        $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload))->assertJson(['RspCode' => '00']);
        $paidAt = Payment::find($payment->id)->paid_at?->toDateTimeString();

        $this->getJson('/payment/vnpay/ipn?' . http_build_query($payload))->assertJson(['RspCode' => '02']);

        $this->assertSame($paidAt, Payment::find($payment->id)->paid_at?->toDateTimeString());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_return_url_does_not_mark_payment_paid(): void
    {
        $payment = $this->createPendingPayment();
        $payload = $this->signedPayload($payment);

        $response = $this->actingAs($payment->order->customer)->get('/payment/vnpay/return?' . http_build_query($payload));

        $response->assertOk();
        $this->assertDatabaseHas('orders', [
            'order_id' => $payment->order_id,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'is_paid' => false,
        ]);
    }

    public function test_transaction_reference_is_unique(): void
    {
        $service = app(VnpayService::class);

        $this->assertNotSame($service->transactionReference(1), $service->transactionReference(1));
    }
}
