@extends('layouts.app')

@section('content')
    @include('components.head-banner')

    <div class="wishlist-area pt-100 pb-100" data-feedback-id="wishlist.page" data-component="WishlistPage">
        <div class="container">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="section-title-2 mb-45">
                <h2>My Wishlist</h2>
            </div>

            @if ($products->isEmpty())
                <div class="empty-cart text-center">
                    <img src="{{ asset('images/default/empty-cart.webp') }}" alt="Empty wishlist">
                    <p>Your wishlist is currently empty.</p>
                    <a href="{{ route('shop') }}" class="btn">Continue Shopping</a>
                </div>
            @else
                <div class="row">
                    @foreach ($products as $product)
                        @php
                            $presentation = $product->presentation;
                            $detail = $presentation->cart_detail;
                            $image = $presentation->image ?: asset('images/default/empty-cart.webp');
                            $priceLabel = $presentation->min_price == $presentation->max_price
                                ? number_format($presentation->min_price, 0, '.', ',') . 'đ'
                                : number_format($presentation->min_price, 0, '.', ',') . 'đ - ' . number_format($presentation->max_price, 0, '.', ',') . 'đ';
                        @endphp
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                            <div class="wishlist-card product-wrap mb-35" data-product-id="{{ $product->product_id }}">
                                <div class="custom-product-img product-img img-zoom mb-25">
                                    <a href="/products/{{ $product->product_id }}">
                                        <img src="{{ $image }}" alt="{{ $product->name }}">
                                    </a>
                                </div>
                                <div class="product-content">
                                    <h3><a href="/products/{{ $product->product_id }}">{{ $product->name }}</a></h3>
                                    <div class="product-price">
                                        <span class="new-price">{{ $priceLabel }}</span>
                                    </div>
                                    <p class="wishlist-meta">
                                        {{ $product->category?->name ?? 'Uncategorized' }} ·
                                        {{ $presentation->stock_total > 0 ? $presentation->stock_total . ' in stock' : 'Out of stock' }}
                                    </p>
                                    <div class="wishlist-actions">
                                        @if ($detail && $presentation->stock_total > 0)
                                            <button type="button" class="js-wishlist-move-to-cart btn"
                                                data-product-id="{{ $product->product_id }}"
                                                data-sku="{{ $detail->sku }}"
                                                data-color-name="{{ $detail->color?->name }}"
                                                data-color-code="{{ $detail->color?->code }}"
                                                data-image="{{ $image }}">
                                                Move to cart
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('wishlist.destroy', $product->product_id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="js-wishlist-remove btn"
                                                data-product-id="{{ $product->product_id }}">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
