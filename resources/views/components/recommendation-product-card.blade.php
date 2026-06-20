@php
    $presentation = $product->presentation;
    $detail = $presentation->cart_detail;
    $image = $presentation->image ?: asset('images/default/empty-cart.webp');
    $isWishlisted = ($wishlistProductIds ?? collect())->contains((int) $product->product_id);
    $isCompared = ($compareProductIds ?? collect())->contains((int) $product->product_id);
    $productUrl = method_exists($product, 'publicUrl') ? $product->publicUrl() : url('/products/' . $product->product_id);
    $priceLabel = $presentation->min_price == $presentation->max_price
        ? number_format($presentation->min_price, 0, '.', ',') . 'đ'
        : number_format($presentation->min_price, 0, '.', ',') . 'đ - ' . number_format($presentation->max_price, 0, '.', ',') . 'đ';
@endphp

<div class="product-wrap recommendation-card mb-35" data-component="RecommendationProductCard"
    data-product-id="{{ $product->product_id }}">
    <div class="custom-product-img product-img img-zoom mb-25">
        <a href="{{ $productUrl }}">
            <img src="{{ $image }}" alt="{{ $product->name }}">
        </a>
        @if ($presentation->stock_total <= 0)
            <div class="custom-product-badge product-badge badge-top badge-left badge-pink">
                <span>Sold out</span>
            </div>
        @endif
        <div class="product-action-wrap">
            <form method="POST" action="{{ route('wishlist.store') }}" class="wishlist-inline-form">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                <button type="submit" class="js-wishlist-toggle product-action-btn-1 @if ($isWishlisted) active @endif"
                    data-product-id="{{ $product->product_id }}" title="Wishlist">
                    <i class="pe-7s-like"></i>
                </button>
            </form>
            <form method="POST" action="{{ route('compare.store') }}" class="compare-inline-form">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                <button type="submit" class="js-compare-toggle product-action-btn-1 @if ($isCompared) active @endif"
                    data-product-id="{{ $product->product_id }}" title="Compare">
                    <i class="pe-7s-shuffle"></i>
                </button>
            </form>
            <a href="{{ $productUrl }}" class="product-action-btn-1" title="View">
                <i class="pe-7s-look"></i>
            </a>
        </div>
        @if ($detail && $presentation->stock_total > 0)
            <div class="product-action-2-wrap">
                <button data-sku="{{ $detail->sku }}"
                    data-color-name="{{ $detail->color?->name }}"
                    data-color-code="{{ $detail->color?->code }}"
                    data-image="{{ $image }}"
                    class="js-add-to-cart-shop product-action-btn-2"
                    title="Add To Cart">
                    <i class="pe-7s-cart"></i> Add to cart
                </button>
            </div>
        @endif
    </div>
    <div class="product-content">
        <h3><a href="{{ $productUrl }}">{{ $product->name }}</a></h3>
        <div class="product-price">
            <span class="new-price">{{ $priceLabel }}</span>
        </div>
        <div class="recommendation-meta">
            <span>{{ $product->category?->name ?? 'Uncategorized' }}</span>
            @if ($presentation->review_count > 0)
                <span>{{ number_format($presentation->rating_average, 1) }} / 5</span>
            @else
                <span>No verified reviews</span>
            @endif
        </div>
    </div>
</div>
