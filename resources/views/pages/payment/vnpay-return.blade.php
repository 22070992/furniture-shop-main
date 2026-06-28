@extends('layouts.app')

@section('content')
    @include('components.head-banner')

    @php
        $messages = [
            'success' => [
                'title' => 'Thanh toán thành công',
                'body' => 'VNPay đã xác nhận thanh toán và đơn hàng đã được cập nhật.',
                'class' => 'success',
            ],
            'pending' => [
                'title' => 'Đang xác nhận thanh toán',
                'body' => 'Giao dịch đã được VNPay phản hồi. Hệ thống đang chờ IPN chính thức để cập nhật đơn hàng.',
                'class' => 'warning',
            ],
            'failed' => [
                'title' => 'Thanh toán không thành công',
                'body' => 'Giao dịch chưa được ghi nhận là đã thanh toán. Bạn có thể kiểm tra lại đơn hàng hoặc thử thanh toán lại sau.',
                'class' => 'danger',
            ],
            'cancelled' => [
                'title' => 'Bạn đã hủy thanh toán',
                'body' => 'Đơn hàng vẫn được lưu nhưng chưa được thanh toán.',
                'class' => 'warning',
            ],
            'not_found' => [
                'title' => 'Không tìm thấy giao dịch',
                'body' => 'Mã giao dịch trả về không khớp với bản ghi thanh toán trong hệ thống.',
                'class' => 'danger',
            ],
            'invalid' => [
                'title' => 'Dữ liệu thanh toán không hợp lệ',
                'body' => 'Chữ ký hoặc dữ liệu trả về không hợp lệ. Vui lòng kiểm tra lại đơn hàng trong tài khoản.',
                'class' => 'danger',
            ],
        ];
        $message = $messages[$status] ?? $messages['invalid'];
    @endphp

    <div class="checkout-main-area pb-100 pt-100" data-feedback-id="payment.vnpay-return">
        <div class="container">
            <div class="row checkout-container justify-content-center">
                <div class="col-lg-8">
                    <div class="vnpay-result-card vnpay-result-{{ $message['class'] }}">
                        <div class="vnpay-result-icon">
                            @if ($status === 'success')
                                <i class="fa-solid fa-check"></i>
                            @elseif ($status === 'cancelled')
                                <i class="fa-solid fa-ban"></i>
                            @else
                                <i class="fa-solid fa-clock"></i>
                            @endif
                        </div>
                        <h2>{{ $message['title'] }}</h2>
                        <p>{{ $message['body'] }}</p>

                        <div class="vnpay-result-details">
                            @if ($order)
                                <div>
                                    <span>Mã đơn hàng</span>
                                    <strong>#{{ $order->order_id }}</strong>
                                </div>
                                <div>
                                    <span>Trạng thái thanh toán</span>
                                    <strong>{{ $order->get_is_paid() }}</strong>
                                </div>
                            @endif

                            @if ($payment)
                                <div>
                                    <span>Mã giao dịch</span>
                                    <strong>{{ $payment->transaction_reference }}</strong>
                                </div>
                                @if ($payment->provider_transaction_id)
                                    <div>
                                        <span>Mã VNPay</span>
                                        <strong>{{ $payment->provider_transaction_id }}</strong>
                                    </div>
                                @endif
                                <div>
                                    <span>Số tiền</span>
                                    <strong>{{ number_format((float) $payment->amount, 0, '.', ',') }}đ</strong>
                                </div>
                            @endif

                            @if (isset($payload['vnp_ResponseCode']))
                                <div>
                                    <span>Mã phản hồi</span>
                                    <strong>{{ e($payload['vnp_ResponseCode']) }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="checkout-footer justify-between mt-4">
                            <a class="btn" href="{{ route('shop') }}">Back to shop</a>
                            @if ($order)
                                <a class="btn" href="{{ route('my_detailed_order', $order->order_id) }}">Track your order</a>
                            @else
                                <a class="btn" href="{{ route('my_orders') }}">My orders</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
