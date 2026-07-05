<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\VnpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VnpayController extends Controller
{
    public function return(Request $request, VnpayService $vnpay)
    {
        $payload = $request->query();
        $transactionReference = (string) ($payload['vnp_TxnRef'] ?? '');
        $validSignature = false;
        $payment = null;

        if (!empty($payload)) {
            try {
                $validSignature = $vnpay->verifyCallback($payload);
            } catch (Throwable $exception) {
                report($exception);
            }

            if ($validSignature) {
                try {
                    $payment = $this->processVerifiedCallback($payload, $transactionReference, $vnpay)['payment'];
                } catch (Throwable $exception) {
                    report($exception);
                    $this->logIpn($transactionReference, null, $payload, 'return_exception');
                }
            } else {
                $payment = Payment::with('order')
                    ->where('provider', Payment::PROVIDER_VNPAY)
                    ->where('transaction_reference', $transactionReference)
                    ->first();
            }
        }

        $status = 'invalid';
        if ($validSignature && $payment) {
            if ($payment->status === Payment::STATUS_PAID || $payment->order?->payment_status === Order::PAYMENT_STATUS_PAID) {
                $status = 'success';
            } elseif ($payment->status === Payment::STATUS_CANCELLED || $payment->order?->payment_status === Order::PAYMENT_STATUS_CANCELLED) {
                $status = 'cancelled';
            } elseif ($payment->status === Payment::STATUS_FAILED || $payment->order?->payment_status === Order::PAYMENT_STATUS_FAILED) {
                $status = 'failed';
            } else {
                $status = 'pending';
            }
        } elseif (!empty($payload) && !$payment) {
            $status = 'not_found';
        }

        return view('pages.payment.vnpay-return', [
            'page' => 'VNPay Payment Result',
            'status' => $status,
            'payment' => $payment,
            'order' => $payment?->order,
            'payload' => $payload,
            'validSignature' => $validSignature,
        ]);
    }

    public function ipn(Request $request, VnpayService $vnpay): JsonResponse
    {
        $payload = $request->query();
        $transactionReference = (string) ($payload['vnp_TxnRef'] ?? '');

        try {
            if (!$vnpay->verifyCallback($payload)) {
                $this->logIpn($transactionReference, null, $payload, 'invalid_signature');

                return $this->vnpayResponse('97', 'Invalid signature');
            }

            $result = $this->processVerifiedCallback($payload, $transactionReference, $vnpay);

            return $this->vnpayResponse($result['code'], $result['message']);
        } catch (Throwable $exception) {
            report($exception);
            $this->logIpn($transactionReference, null, $payload, 'exception');

            return $this->vnpayResponse('99', 'Unknown error');
        }
    }

    private function processVerifiedCallback(array $payload, string $transactionReference, VnpayService $vnpay): array
    {
        return DB::transaction(function () use ($payload, $transactionReference, $vnpay) {
            $payment = Payment::with('order')
                ->where('provider', Payment::PROVIDER_VNPAY)
                ->where('transaction_reference', $transactionReference)
                ->lockForUpdate()
                ->first();

            if (!$payment || !$payment->order) {
                $this->logIpn($transactionReference, null, $payload, 'payment_not_found');

                return [
                    'code' => '01',
                    'message' => 'Order not found',
                    'payment' => null,
                ];
            }

            $callbackAmount = $vnpay->fromVnpayAmount($payload['vnp_Amount'] ?? null);
            $paymentAmount = (int) round((float) $payment->amount);
            $orderAmount = (int) round((float) $payment->order->total_price);

            if ($callbackAmount === null || $callbackAmount !== $paymentAmount || $callbackAmount !== $orderAmount) {
                $this->storeCallback($payment, $payload, Payment::STATUS_FAILED);
                $this->logIpn($transactionReference, $payment->order_id, $payload, 'invalid_amount');

                return [
                    'code' => '04',
                    'message' => 'Invalid amount',
                    'payment' => $payment->fresh('order'),
                ];
            }

            if ($payment->isPaid()) {
                $this->logIpn($transactionReference, $payment->order_id, $payload, 'already_paid');

                return [
                    'code' => '02',
                    'message' => 'Order already confirmed',
                    'payment' => $payment->fresh('order'),
                ];
            }

            $paymentStatus = $vnpay->paymentStatusFor($payload);
            $this->storeCallback($payment, $payload, $paymentStatus);

            if ($paymentStatus === Payment::STATUS_PAID) {
                $paidAt = now();
                $payment->forceFill([
                    'paid_at' => $paidAt,
                ])->save();

                $payment->order->update([
                    'is_paid' => true,
                    'payment_method' => Order::PAYMENT_METHOD_VNPAY,
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'paid_at' => $paidAt,
                ]);
            } else {
                $payment->order->update([
                    'is_paid' => false,
                    'payment_method' => Order::PAYMENT_METHOD_VNPAY,
                    'payment_status' => $paymentStatus === Payment::STATUS_CANCELLED
                        ? Order::PAYMENT_STATUS_CANCELLED
                        : Order::PAYMENT_STATUS_FAILED,
                ]);
            }

            $this->logIpn($transactionReference, $payment->order_id, $payload, $paymentStatus);

            return [
                'code' => '00',
                'message' => 'Confirm Success',
                'payment' => $payment->fresh('order'),
            ];
        });
    }

    private function storeCallback(Payment $payment, array $payload, string $status): void
    {
        $payment->update([
            'provider_transaction_id' => $payload['vnp_TransactionNo'] ?? $payment->provider_transaction_id,
            'status' => $status,
            'response_code' => $payload['vnp_ResponseCode'] ?? null,
            'transaction_status' => $payload['vnp_TransactionStatus'] ?? null,
            'bank_code' => $payload['vnp_BankCode'] ?? null,
            'card_type' => $payload['vnp_CardType'] ?? null,
            'callback_payload' => $payload,
        ]);
    }

    private function vnpayResponse(string $code, string $message): JsonResponse
    {
        return response()->json([
            'RspCode' => $code,
            'Message' => $message,
        ]);
    }

    private function logIpn(string $transactionReference, ?int $orderId, array $payload, string $result): void
    {
        Log::info('vnpay.ipn', [
            'transaction_reference' => $transactionReference,
            'order_id' => $orderId,
            'response_code' => $payload['vnp_ResponseCode'] ?? null,
            'transaction_status' => $payload['vnp_TransactionStatus'] ?? null,
            'result' => $result,
        ]);
    }
}
