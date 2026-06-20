@foreach ($featured_sections as $section)
    <div class="product-area pb-60" data-feedback-id="home.section.{{ \Illuminate\Support\Str::slug($section['title']) }}"
        data-page-section="featured-products" data-component="FeaturedProductSection">
        <div class="container">
            <div class="section-title-tab-wrap mb-45">
                <div class="section-title-2">
                    <h2>{{ $section['title'] }}</h2>
                </div>
                <div class="tab-style-1 nav">
                    <a href="/shop?category={{ $section['category_id'] ?? 'all' }}">View all</a>
                </div>
            </div>
            <div class="row">
                @foreach ($section['products'] as $product)
                    @php
                        $detailed_product = $product->detailed_product;
                        $isWishlisted = ($wishlistProductIds ?? collect())->contains((int) $product->product_id);
                        $isCompared = ($compareProductIds ?? collect())->contains((int) $product->product_id);
                    @endphp
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                        <div class="product-wrap mb-35" data-feedback-id="product-card.{{ $product->product_id }}"
                            data-component="ProductCard" data-product-id="{{ $product->product_id }}">
                            <div class="custom-product-img product-img img-zoom mb-25" data-feedback-id="product-card.{{ $product->product_id }}.image">
                                <a href="/products/{{ $product->product_id }}">
                                    <img src="{{ $detailed_product->image }}" alt="{{ $product->name }}"
                                        data-feedback-id="product-image.{{ $product->product_id }}">
                                </a>
                                <div class="product-action-wrap">
                                    <form method="POST" action="{{ route('wishlist.store') }}" class="wishlist-inline-form">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                                        <button type="submit"
                                            class="js-wishlist-toggle product-action-btn-1 @if ($isWishlisted) active @endif"
                                            data-product-id="{{ $product->product_id }}" title="Wishlist">
                                            <i class="pe-7s-like"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('compare.store') }}" class="compare-inline-form">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                                        <button type="submit"
                                            class="js-compare-toggle product-action-btn-1 @if ($isCompared) active @endif"
                                            data-product-id="{{ $product->product_id }}" title="Compare">
                                            <i class="pe-7s-shuffle"></i>
                                        </button>
                                    </form>
                                    <a href="/products/{{ $product->product_id }}" class="product-action-btn-1"
                                        title="View">
                                        <i class="pe-7s-look"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="product-content">
                                <h3><a href="/products/{{ $product->product_id }}">{{ $product->name }}</a></h3>
                                <div class="product-price" data-feedback-id="product-price.{{ $product->product_id }}">
                                    <span class="new-price">
                                        {{ number_format($detailed_product->original_price, 0, '.', ',') }}đ
                                    </span>
                                </div>
                                <div class="featured-card-meta">
                                    <span>{{ $detailed_product->color_name }}</span>
                                    <span>{{ $product->rating }} / 5</span>
                                </div>
                                <div class="featured-card-swatches">
                                    @foreach ($product->color_options ?? [] as $color)
                                        <span title="{{ $color['name'] }}"
                                            style="background-color: {{ $color['code'] ?? '#ddd' }}"></span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
