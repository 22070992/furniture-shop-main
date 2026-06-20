@extends('layouts.app')
@section('content')
    {{-- Head banner --}}
    @include('components.head-banner')
    <div class="checkout-main-area pb-100 pt-100">
        <div class="container">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="row checkout-container">
                @if (isset($order->order_id))
                    <div class="col-8 checkout-left order-padding">
                        <div class="top">
                            <img class="checkout-icon" src="{{ asset('svg/checkout-success.svg') }}" />
                            <div class="checkout-message">
                                <h2>Your order is {{ $order->get_status }}</h2>
                                <p>We will be sending you an email confirmation to <span>{{ Auth::user()->email }}</span>
                                    shortly
                                </p>
                            </div>
                            <div class="checkout-status">
                                <p class="title">Order <a href="">#{{ $order->order_id }}</a> was placed on
                                    <span>{{ $order->created_at }}</span>
                                    and is currently
                                    in progress
                                </p>
                                <div class="status-progress">
                                    @if ($order->status == 4)
                                        <div class="progress-item active "><i class="fa-solid fa-clock"></i><span>Await
                                                confirm</span></div>
                                        <div class="progress-line cancel "></div>
                                        <div class="progress-item cancel "><i
                                                class="fa-solid fa-xmark"></i><span>Canceled</span></div>
                                    @else
                                        <div class="progress-item @if ($order->status >= 0) active @endif"><i
                                                class="fa-solid fa-clock"></i><span>Await confirm</span></div>
                                        <div class="progress-line @if ($order->status >= 1) active @endif"></div>
                                        <div class="progress-item @if ($order->status >= 1) active @endif"><i
                                                class="fa-solid fa-check"></i><span>Comfirmed</span></div>
                                        <div class="progress-line @if ($order->status >= 2) active @endif"></div>
                                        <div class="progress-item @if ($order->status >= 2) active @endif"><i
                                                class="fa-solid fa-truck"></i><span>In transit</span></div>
                                        <div class="progress-line @if ($order->status >= 3) active @endif"></div>
                                        <div class="progress-item @if ($order->status >= 3) active @endif"><i
                                                class="fa-solid fa-thumbs-up"></i><span>Deliverd</span></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="checkout-footer justify-between">
                            <a class="btn" href="/myorders">Back to orders</a>
                            @if ($order->status < 2)
                                <a class="btn" data-bs-toggle="modal" data-bs-target="#cancel-order-modal">Cancel
                                    order</a>
                            @endif
                        </div>
                    </div>
                    <div class="col-4 checkout-right">
                        @if ($order->is_paid)
                            <div class="paid-tag">Paid</div>
                        @else
                            <div class="paid-tag unpaid">Unpaid</div>
                        @endif
                        <div class="header">
                            <h3>Order detail</h3>
                            <h2 class="js-order-id" data-order-id="{{ $order->order_id }}">#{{ $order->order_id }}</h2>
                        </div>
                        <div class="content">
                            <div class="delivery-address">
                                <h3>Delivery address</h3>
                                <p>{{ $order->receiver_name }} {{ $order->phone_number }}</p>
                                <p>{{ $order->address }}</p>
                            </div>
                            <div class="order-summary">
                                <h3>order summary</h3>
                                @foreach ($order->order_details as $detailed_order)
                                    @php
                                        $review = $detailed_order->getRelationValue('review');
                                        $reviewFormId = 'review-form-' . md5($detailed_order->order_id . $detailed_order->sku);
                                        $productId = $detailed_order->detailed_product->product_id;
                                    @endphp
                                    <div class="item">
                                        <p>x{{ $detailed_order->quantities }}
                                            <a
                                                href="/products/{{ $detailed_order->detailed_product->product_id }}">{{ $detailed_order->detailed_product->name }}</a>

                                        </p>
                                        <p>{{ number_format($detailed_order->unit_price * $detailed_order->quantities, 0, '.', ',') }}đ
                                        </p>
                                    </div>
                                    <div class="order-review-box" data-feedback-id="myorders.review.{{ $detailed_order->sku }}">
                                        @if ($order->status == 3)
                                            @if (!$review || $review->status === \App\Models\Review::STATUS_REJECTED)
                                                @if ($review && $review->status === \App\Models\Review::STATUS_REJECTED)
                                                    <div class="review-status rejected">Review rejected</div>
                                                    @if ($review->admin_note)
                                                        <p class="review-admin-note">{{ $review->admin_note }}</p>
                                                    @endif
                                                @endif
                                                <button class="review-toggle" type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $reviewFormId }}"
                                                    aria-expanded="{{ $review && $review->status === \App\Models\Review::STATUS_REJECTED ? 'true' : 'false' }}">
                                                    Write a review
                                                </button>
                                                <form id="{{ $reviewFormId }}"
                                                    class="collapse review-inline-form {{ $review && $review->status === \App\Models\Review::STATUS_REJECTED ? 'show' : '' }}"
                                                    method="POST" action="{{ route('reviews.store') }}"
                                                    enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="order_id" value="{{ $detailed_order->order_id }}">
                                                    <input type="hidden" name="sku" value="{{ $detailed_order->sku }}">
                                                    <input type="hidden" name="product_id" value="{{ $productId }}">
                                                    <label>
                                                        Rating
                                                        <select name="rating" required>
                                                            @for ($rating = 5; $rating >= 1; $rating--)
                                                                <option value="{{ $rating }}">{{ $rating }} stars</option>
                                                            @endfor
                                                        </select>
                                                    </label>
                                                    <label>
                                                        Your review
                                                        <textarea name="content" rows="3" maxlength="2000" required>{{ old('content') }}</textarea>
                                                    </label>
                                                    <label>
                                                        Photos
                                                        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp"
                                                            multiple>
                                                    </label>
                                                    <button class="btn" type="submit">Submit review</button>
                                                </form>
                                            @elseif ($review->status === \App\Models\Review::STATUS_PENDING)
                                                <div class="review-status pending">Review pending</div>
                                            @elseif ($review->status === \App\Models\Review::STATUS_APPROVED)
                                                <div class="review-status approved">Reviewed</div>
                                            @endif
                                        @else
                                            <div class="review-status muted">Review available after delivery</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if (isset($order->note))
                                <div class="order-note">
                                    <h3>Order Note</h3>
                                    <p>{!! $order->note !!}</p>
                                </div>
                            @endif

                        </div>
                        <div class="footer">
                            <h3>Total</h3>
                            <span>{{ number_format(
                                $order->order_details->reduce(function ($carry, $detail) {
                                    return $carry + $detail->unit_price * $detail->quantities;
                                }, 0),
                                0,
                                '.',
                                ',',
                            ) }}đ</span>
                        </div>
                    </div>
                @else
                    <p>Can not find this order</p>
                @endif
            </div>

        </div>
    </div>

    @include('pages.myorders.cancel-order-modal')
@endsection
