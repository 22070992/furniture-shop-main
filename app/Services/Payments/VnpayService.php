<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class VnpayService
{
    public function createPaymentUrl(Payment $payment, Request $request): string
    {
        $this->ensureConfigured();

        $order = $payment->order;
        $now = CarbonImmutable::now($this->timezone());
        $amount = $this->toVnpayAmount((int) round((float) $payment->amount));
        $returnUrl = config('services.vnpay.return_url') ?: route('vnpay.return');

        $params = [
            'vnp_Version' => (string) config('services.vnpay.version', '2.1.0'),
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => (string) config('services.vnpay.tmn_code'),
            'vnp_Amount' => (string) $amount,
            'vnp_CurrCode' => (string) config('services.vnpay.currency', 'VND'),
            'vnp_TxnRef' => $payment->transaction_reference,
            'vnp_OrderInfo' => $payment->order_info ?: 'Thanh toan don hang #' . $order?->order_id,
            'vnp_OrderType' => (string) config('services.vnpay.order_type', 'other'),
            'vnp_Locale' => (string) config('services.vnpay.locale', 'vn'),
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => $request->ip() ?: '127.0.0.1',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $now->addMinutes($this->paymentTimeout())->format('YmdHis'),
        ];

        $ipnUrl = config('services.vnpay.ipn_url');
        if (filled($ipnUrl)) {
            $params['vnp_IpnUrl'] = $ipnUrl;
        }

        $params['vnp_SecureHash'] = $this->makeSecureHash($params);

        return rtrim((string) config('services.vnpay.payment_url'), '?') . '?' . $this->queryString($params);
    }

    public function verifyCallback(array $payload): bool
    {
        $secureHash = (string) ($payload['vnp_SecureHash'] ?? '');

        if ($secureHash === '') {
            return false;
        }

        return hash_equals($secureHash, $this->makeSecureHash($payload));
    }

    public function makeSecureHash(array $params): string
    {
        $this->ensureConfigured();

        return hash_hmac('sha512', $this->hashData($params), (string) config('services.vnpay.hash_secret'));
    }

    public function hashData(array $params): string
    {
        return $this->queryString($this->signableParams($params));
    }

    public function signableParams(array $params): array
    {
        $params = Arr::except($params, ['vnp_SecureHash', 'vnp_SecureHashType']);
        $params = array_filter($params, static fn ($value) => $value !== null && $value !== '');
        ksort($params);

        return $params;
    }

    public function queryString(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function toVnpayAmount(int $amount): int
    {
        return $amount * 100;
    }

    public function fromVnpayAmount(string|int|null $amount): ?int
    {
        if ($amount === null || !is_numeric($amount)) {
            return null;
        }

        return intdiv((int) $amount, 100);
    }

    public function transactionReference(int $orderId): string
    {
        return sprintf('VNP-%d-%s-%s', $orderId, now($this->timezone())->format('YmdHis'), Str::upper(Str::random(8)));
    }

    public function successResponse(array $payload): bool
    {
        return ($payload['vnp_ResponseCode'] ?? null) === '00'
            && ($payload['vnp_TransactionStatus'] ?? null) === '00';
    }

    public function paymentStatusFor(array $payload): string
    {
        if ($this->successResponse($payload)) {
            return Payment::STATUS_PAID;
        }

        if (($payload['vnp_ResponseCode'] ?? null) === '24') {
            return Payment::STATUS_CANCELLED;
        }

        return Payment::STATUS_FAILED;
    }

    public function ensureConfigured(): void
    {
        foreach (['tmn_code', 'hash_secret', 'payment_url'] as $key) {
            if (!filled(config('services.vnpay.' . $key))) {
                throw new RuntimeException('VNPay is not configured. Missing services.vnpay.' . $key . '.');
            }
        }
    }

    private function paymentTimeout(): int
    {
        return max(1, (int) config('services.vnpay.payment_timeout', 15));
    }

    private function timezone(): string
    {
        return (string) config('services.vnpay.timezone', 'Asia/Ho_Chi_Minh');
    }
}
