@extends('layouts.app')
@section('content')
    @php
        $selectedDetail = $product->detailed_products->first();
        $selectedImage = $selectedDetail?->images?->first()?->url ?? asset('images/default/empty-cart.webp');
        $selectedDiscount = $selectedDetail
            ? $selectedDetail->product_discounts
                ->where('discount.start_date', '<=', now())
                ->where('discount.end_date', '>=', now())
                ->sum('discount.percentage')
            : 0;
        $reviewAverage = $reviewSummary['average'] ?? 0;
        $reviewCount = $reviewSummary['count'] ?? 0;
        $isWishlisted = ($wishlistProductIds ?? collect())->contains((int) $product->product_id);
        $isCompared = ($compareProductIds ?? collect())->contains((int) $product->product_id);
    @endphp
    {{-- Head banner --}}
    @include('components.head-banner')

    <div class="product-details-area pb-100 pt-100" data-feedback-id="product-detail.page"
        data-component="ProductDetailPage" data-product-id="{{ $product->product_id }}">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="product-details-img-wrap product-details-vertical-wrap" data-aos="fade-up"
                        data-feedback-id="product-detail.gallery"
                        data-aos-delay="200">
                        <div class="product-details-small-img-wrap">
                            <div class="swiper-container product-details-small-img-slider-1 pd-small-img-style">
                                <div class="js-images-list swiper-wrapper">
                                    <div class="swiper-slide">
                                        <div class="product-details-small-img">
                                                <img class="js-main-product-thumb" src="{{ $selectedImage }}"
                                                    data-feedback-id="product-detail.thumbnail"
                                                    alt="{{ $selectedDetail?->name }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pd-prev pd-nav-style"> <i class="ti-angle-up"></i></div>
                            <div class="pd-next pd-nav-style"> <i class="ti-angle-down"></i></div>
                        </div>
                        <div class="swiper-container product-details-big-img-slider-1 pd-big-img-style">
                            <div class="js-images-preview swiper-wrapper">
                                <div class="swiper-slide">
                                    <div class="easyzoom-style">
                                        <div class="easyzoom easyzoom--overlay">
                                            <a class="js-main-product-link" href="{{ $selectedImage }}">
                                                <img class="js-main-product-image" src="{{ $selectedImage }}"
                                                    data-feedback-id="product-detail.main-image"
                                                    alt="{{ $selectedDetail?->name }}">
                                            </a>
                                        </div>
                                        <a class="easyzoom-pop-up img-popup js-main-product-popup"
                                            href="{{ $selectedImage }}">
                                            <i class="pe-7s-search"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="product-details-content" data-aos="fade-up" data-aos-delay="400"
                        data-feedback-id="product-detail.info">
                        @foreach ($product->detailed_products as $detailed_product)
                            <div
                                class="js-product-name-price {{ $detailed_product->sku }} @if (!$loop->first) d-none @endif">
                                <h2 class="js-product-name" data-feedback-id="product-detail.title">{{ $product->name }} </h2>

                                @php
                                    $today = now();
                                    $discount_percentage = $detailed_product->product_discounts
                                        ->where('discount.start_date', '<=', $today)
                                        ->where('discount.end_date', '>=', $today)
                                        ->sum('discount.percentage');
                                @endphp
                                <div class="product-details-price" data-feedback-id="product-detail.price">
                                    @if ($discount_percentage > 0)
                                        <span class="old-price">
                                            {{ number_format($detailed_product->original_price, 0, '.', ',') }}đ</span>
                                    @endif
                                    <span class="js-unit-price new-price"
                                        data-unit-price="{{ $detailed_product->original_price - ($detailed_product->original_price * $discount_percentage) / 100 }}">{{ number_format($detailed_product->original_price - ($detailed_product->original_price * $discount_percentage) / 100, 0, '.', ',') }}đ</span>
                                </div>
                            </div>
                        @endforeach
                        <div class="product-rating-summary">
                            @if ($reviewCount > 0)
                                <span class="stars">{!! str_repeat('&#9733;', (int) round($reviewAverage)) !!}{!! str_repeat('&#9734;', 5 - (int) round($reviewAverage)) !!}</span>
                                <span>{{ number_format($reviewAverage, 1) }} ({{ $reviewCount }} verified reviews)</span>
                            @else
                                <span class="stars">&#9734;&#9734;&#9734;&#9734;&#9734;</span>
                                <span>No verified reviews yet</span>
                            @endif
                        </div>
                        <p class="product-short-description">{{ $product->short_description ?? $product->description }}</p>
                        <div class="product-color-picker my-4" data-feedback-id="product-detail.color-picker">
                            <div class="product-color-heading">
                                Color: <span class="js-selected-color-name">{{ $selectedDetail?->color?->name }}</span>
                            </div>
                            <div class="product-color-options">
                            @foreach ($product->detailed_products as $dt_product)
                                @php
                                    $today = now();
                                    $discount_percentage = $dt_product->product_discounts
                                        ->where('discount.start_date', '<=', $today)
                                        ->where('discount.end_date', '>=', $today)
                                        ->sum('discount.percentage');
                                @endphp
                                <button type="button"
                                    class="detailed-product-tag color-swatch-option @if ($loop->first) active @endif
                                @if ($dt_product->quantities == 0) disable @endif"
                                    data-sku="{{ $dt_product->sku }}" data-id="{{ $product->product_id }}"
                                    data-image="{{ $dt_product->images->first()?->url }}"
                                    data-color-name="{{ $dt_product->color->name }}"
                                    data-color-code="{{ $dt_product->color->code }}" data-size="{{ $dt_product->size }}"
                                    data-stock="{{ $dt_product->quantities }}"
                                    data-feedback-id="product-detail.color.{{ $dt_product->sku }}"
                                    data-description="{{ strip_tags($dt_product->description) }}">
                                    <span class="swatch"
                                        style="background-color: {{ $dt_product->color->code }}"></span>
                                    <span>{{ $dt_product->color->name }}</span>
                                    @if ($discount_percentage > 0)
                                        <span class="text-danger">-{{ $discount_percentage }}%</span>
                                    @endif

                                </button>
                            @endforeach
                            </div>
                        </div>
                        <div class="product-details-quantities">
                            <div class="select-quantities-wrapper">
                                <button class="js-quantity-minus"><i class="ti-minus"></i></button>
                                <input class="js-quantity-input quantity-input" type="number" value="0">
                                <button class="js-quantity-add"><i class="ti-plus"></i></button>
                            </div>

                            @foreach ($product->detailed_products as $detailed_product)
                                <p class="js-product-quantities m-0 {{ $detailed_product->sku }} @if (!$loop->first) d-none @endif"
                                    data-quantities="{{ $detailed_product->quantities }}">
                                    (<span
                                        class="js-product-stock-number">{{ $detailed_product->quantities }}</span>
                                    available products)
                                </p>
                            @endforeach

                        </div>
                        <div class="product-details-action-wrap">
                            <button class="js-add-to-cart add-to-cart disable" disabled
                                data-feedback-id="product-detail.add-to-cart">Add to cart</button>
                            <button class="js-buy-now buy-now disable " disabled
                                data-feedback-id="product-detail.buy-now">Buy now</button>
                        </div>
                        <div class="product-secondary-actions" data-feedback-id="product-detail.secondary-actions">
                            <form method="POST" action="{{ route('wishlist.store') }}" class="wishlist-inline-form">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                                <button type="submit"
                                    class="js-wishlist-toggle product-secondary-btn @if ($isWishlisted) active @endif"
                                    data-product-id="{{ $product->product_id }}">
                                    <i class="pe-7s-like"></i>
                                    <span>{{ $isWishlisted ? 'In wishlist' : 'Add to wishlist' }}</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('compare.store') }}" class="compare-inline-form">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                                <button type="submit"
                                    class="js-compare-toggle product-secondary-btn @if ($isCompared) active @endif"
                                    data-product-id="{{ $product->product_id }}">
                                    <i class="pe-7s-shuffle"></i>
                                    <span>{{ $isCompared ? 'In compare' : 'Compare' }}</span>
                                </button>
                            </form>
                        </div>
                        <div class="product-details-meta">
                            <ul>
                                <li><span class="title ">SKU:</span>
                                    <span class="js-product-sku">{{ $selectedDetail?->sku }}</span>
                                </li>

                                <li><span class="title">Category:</span>
                                    <ul>
                                        <li><a>{{ $product->category->name }}</a></li>
                                    </ul>
                                </li>
                                <li><span class="title">Material:</span>
                                    <ul>
                                        <li><a>{{ $product->material }}</a></li>
                                    </ul>
                                </li>
                                <li><span class="title">Size:</span>
                                    <ul>
                                        <li><a class="js-product-size">{{ $selectedDetail?->size }}</a></li>
                                    </ul>
                                </li>
                                <li><span class="title">Tags:</span>
                                    <ul>
                                        @foreach ($product->product_tags as $product_tag)
                                            <li class="me-2"><a>#{{ $product_tag->tag->name }} </a></li>
                                        @endforeach
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="description-review-area pb-85">
        <div class="container">
            <div class="description-review-topbar nav" data-aos="fade-up" data-aos-delay="200">
                <a class="active" data-bs-toggle="tab" href="#des-details1"> Description </a>
                <a data-bs-toggle="tab" href="#des-details2" class=""> Information </a>
                <a data-bs-toggle="tab" href="#des-details3" class=""> Reviews </a>
            </div>
            <div class="tab-content">
                <div id="des-details1" class="tab-pane active">
                    <div class="product-description-content text-center">
                        <div data-aos="fade-up" data-aos-delay="400">
                            <div class="js-product-description">{!! $selectedDetail?->description !!}</div>
                        </div>
                    </div>
                </div>
                <div id="des-details2" class="tab-pane">
                    <div class="specification-wrap table-responsive">
                        <table>
                            <tbody>
                                <tr>
                                    <td class="width1">Brands</td>
                                    <td>{{ $product->brand->name }}</td>
                                </tr>
                                <tr>
                                    <td class="width1">Material</td>
                                    <td>{{ $product->material }}</td>
                                </tr>
                                <tr>
                                    <td class="width1">Color</td>
                                    <td class="js-info-color">{{ $selectedDetail?->color?->name }}</td>
                                </tr>
                                <tr>
                                    <td class="width1">Size</td>
                                    <td class="js-info-size">{{ $selectedDetail?->size }}</td>
                                </tr>
                                <tr>
                                    <td class="width1">Stock</td>
                                    <td class="js-info-stock">{{ $selectedDetail?->quantities }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="des-details3" class="tab-pane">
                    <div class="verified-reviews-wrap" data-feedback-id="product-detail.reviews">
                        <div class="review-summary-panel">
                            <div class="review-score">
                                <strong>{{ number_format($reviewAverage, 1) }}</strong>
                                <span>{{ $reviewCount }} verified {{ \Illuminate\Support\Str::plural('review', $reviewCount) }}</span>
                            </div>
                            <div class="review-bars">
                                @foreach ($reviewSummary['distribution'] as $rating => $row)
                                    <div class="review-bar-row">
                                        <span>{{ $rating }} star</span>
                                        <div class="review-bar">
                                            <i style="width: {{ $row['percent'] }}%"></i>
                                        </div>
                                        <em>{{ $row['total'] }}</em>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @forelse ($reviews as $review)
                            <article class="verified-review-card">
                                <div class="review-card-head">
                                    <div>
                                        <strong>{{ $review->user?->full_name() ?? 'Customer' }}</strong>
                                        <span class="verified-badge">Verified purchase</span>
                                    </div>
                                    <div class="review-stars">{!! str_repeat('&#9733;', $review->rating) !!}{!! str_repeat('&#9734;', 5 - $review->rating) !!}</div>
                                </div>
                                <p>{{ $review->content }}</p>
                                @if ($review->images->isNotEmpty())
                                    <div class="review-images">
                                        @foreach ($review->images as $image)
                                            <a href="{{ $image->url() }}" class="img-popup">
                                                <img src="{{ $image->url() }}" alt="Review image for {{ $product->name }}">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                <time>{{ $review->approved_at?->format('d/m/Y') ?? $review->created_at->format('d/m/Y') }}</time>
                            </article>
                        @empty
                            <div class="no-reviews-state">
                                <h3>No verified reviews yet</h3>
                                <p>Only customers with delivered orders can review this product.</p>
                            </div>
                        @endforelse

                        @if ($reviews->hasPages())
                            <div class="d-flex justify-content-center mt-4">
                                {{ $reviews->links('common.pagination') }}
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
    @if (($relatedProducts ?? collect())->isNotEmpty())
        <div class="product-area pb-60" data-feedback-id="product-detail.related-products"
            data-component="RelatedProducts">
            <div class="container">
                <div class="section-title-tab-wrap mb-45">
                    <div class="section-title-2">
                        <h2>Related Products</h2>
                    </div>
                </div>
                <div class="row">
                    @foreach ($relatedProducts as $relatedProduct)
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                            @include('components.recommendation-product-card', ['product' => $relatedProduct])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if (($completeRoomProducts ?? collect())->isNotEmpty())
        <div class="complete-room-area pb-60" data-feedback-id="product-detail.complete-room"
            data-component="CompleteRoom">
            <div class="container">
                <div class="section-title-tab-wrap mb-45">
                    <div class="section-title-2">
                        <h2>Complete the Room</h2>
                    </div>
                    <div class="tab-style-1 nav">
                        <button type="button" class="js-add-room-set complete-room-add"
                            data-product-ids='@json($completeRoomProducts->pluck('product_id')->values())'>
                            Add room set
                        </button>
                    </div>
                </div>
                <div class="complete-room-summary">
                    <span>
                        Combo estimate:
                        {{ number_format($completeRoomProducts->sum(fn ($item) => $item->presentation->min_price), 0, '.', ',') }}đ
                    </span>
                    <em>Each item is validated by stock before being added.</em>
                </div>
                <div class="row">
                    @foreach ($completeRoomProducts as $roomProduct)
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                            @include('components.recommendation-product-card', ['product' => $roomProduct])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="ai-interior-combo-area pb-60" data-feedback-id="product-detail.ai-interior-combo"
        data-component="AiInteriorCombo"
        data-ai-interior-combo
        data-recommend-url="{{ route('ai-interior-combo.recommend') }}"
        data-cart-url="{{ route('ai-interior-combo.cart_items') }}">
        <div class="container">
            <div class="section-title-tab-wrap mb-35">
                <div class="section-title-2">
                    <h2>AI Interior Combo</h2>
                </div>
            </div>
            <div class="ai-combo-shell">
                <form class="ai-combo-form" data-ai-combo-form>
                    <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                    <div class="ai-combo-grid">
                        <label>
                            <span>Room</span>
                            <select name="room">
                                <option value="">Any room</option>
                                <option value="living room">Living room</option>
                                <option value="bedroom">Bedroom</option>
                                <option value="dining room">Dining room</option>
                                <option value="office">Office</option>
                            </select>
                        </label>
                        <label>
                            <span>Budget</span>
                            <input type="number" name="budget" min="0" step="100000" placeholder="20000000">
                        </label>
                        <label>
                            <span>Style</span>
                            <input type="text" name="style" maxlength="120" placeholder="Modern, warm, compact">
                        </label>
                        <label>
                            <span>Color</span>
                            <input type="text" name="color" maxlength="80" placeholder="Beige, black, oak">
                        </label>
                        <label>
                            <span>Room size</span>
                            <input type="text" name="dimensions" maxlength="120" placeholder="3m x 4m">
                        </label>
                        <label class="ai-combo-prompt">
                            <span>Brief</span>
                            <textarea name="prompt" maxlength="1000" rows="3" placeholder="Small bedroom, beige tone, need storage and soft lighting"></textarea>
                        </label>
                    </div>
                    <button type="submit" class="ai-combo-submit">Generate combo</button>
                </form>
                <div class="ai-combo-result" data-ai-combo-result hidden></div>
            </div>
        </div>
    </div>

    @if (($recentlyViewedProducts ?? collect())->isNotEmpty())
        <div class="product-area pb-60" data-feedback-id="product-detail.recently-viewed"
            data-component="RecentlyViewedProducts">
            <div class="container">
                <div class="section-title-tab-wrap mb-45">
                    <div class="section-title-2">
                        <h2>Recently Viewed</h2>
                    </div>
                </div>
                <div class="row">
                    @foreach ($recentlyViewedProducts->take(4) as $recentProduct)
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                            @include('components.recommendation-product-card', ['product' => $recentProduct])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
