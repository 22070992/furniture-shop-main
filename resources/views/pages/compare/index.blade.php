@extends('layouts.app')

@section('content')
    @include('components.head-banner')

    <div class="compare-area pt-100 pb-100" data-feedback-id="compare.page" data-component="ComparePage">
        <div class="container">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="section-title-2 mb-35">
                <h2>Product Compare</h2>
            </div>

            @if ($products->isEmpty())
                <div class="no-reviews-state">
                    <h3>No products selected</h3>
                    <p>Add products from the shop or product detail page to compare them here.</p>
                    <a href="{{ route('shop') }}" class="btn">Browse products</a>
                </div>
            @else
                @if ($products->count() === 1)
                    <div class="alert alert-warning">
                        Add at least one more product to compare side by side.
                    </div>
                @endif

                <div class="compare-actions mb-3">
                    <form method="POST" action="{{ route('compare.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button class="js-compare-clear btn" type="submit">Clear compare</button>
                    </form>
                </div>

                <div class="compare-table-wrap">
                    <table class="compare-table">
                        <tbody>
                            <tr>
                                <th>Product</th>
                                @foreach ($products as $product)
                                    @php
                                        $presentation = $product->presentation;
                                        $image = $presentation->image ?: asset('images/default/empty-cart.webp');
                                    @endphp
                                    <td data-product-id="{{ $product->product_id }}">
                                        <img src="{{ $image }}" alt="{{ $product->name }}" class="compare-image">
                                        <h3>{{ $product->name }}</h3>
                                        <form method="POST" action="{{ route('compare.destroy', $product->product_id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="js-compare-remove compare-remove" type="submit"
                                                data-product-id="{{ $product->product_id }}">
                                                Remove
                                            </button>
                                        </form>
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Price</th>
                                @foreach ($products as $product)
                                    @php
                                        $presentation = $product->presentation;
                                        $priceLabel = $presentation->min_price == $presentation->max_price
                                            ? number_format($presentation->min_price, 0, '.', ',') . 'đ'
                                            : number_format($presentation->min_price, 0, '.', ',') . 'đ - ' . number_format($presentation->max_price, 0, '.', ',') . 'đ';
                                    @endphp
                                    <td>{{ $priceLabel }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Category</th>
                                @foreach ($products as $product)
                                    <td>{{ $product->category?->name ?? 'Not available' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Material</th>
                                @foreach ($products as $product)
                                    <td>{{ $product->material ?: 'Not available' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Sizes</th>
                                @foreach ($products as $product)
                                    <td>{{ $product->presentation->sizes->join(', ') ?: 'Not available' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Colors</th>
                                @foreach ($products as $product)
                                    <td>
                                        <div class="compare-colors">
                                            @foreach ($product->presentation->color_codes as $code)
                                                <span style="background-color: {{ $code }}"></span>
                                            @endforeach
                                        </div>
                                        {{ $product->presentation->colors->join(', ') ?: 'Not available' }}
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>SKU count</th>
                                @foreach ($products as $product)
                                    <td>{{ $product->presentation->sku_count }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Stock</th>
                                @foreach ($products as $product)
                                    <td>{{ $product->presentation->stock_total > 0 ? $product->presentation->stock_total . ' available' : 'Out of stock' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Verified rating</th>
                                @foreach ($products as $product)
                                    <td>
                                        @if ($product->presentation->review_count > 0)
                                            {{ number_format($product->presentation->rating_average, 1) }}
                                            ({{ $product->presentation->review_count }})
                                        @else
                                            No verified reviews
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th>Actions</th>
                                @foreach ($products as $product)
                                    @php
                                        $detail = $product->presentation->cart_detail;
                                        $image = $product->presentation->image ?: asset('images/default/empty-cart.webp');
                                    @endphp
                                    <td>
                                        <a href="/products/{{ $product->product_id }}" class="btn mb-2">View detail</a>
                                        @if ($detail && $product->presentation->stock_total > 0)
                                            <button type="button" class="js-add-to-cart-shop btn"
                                                data-sku="{{ $detail->sku }}"
                                                data-color-name="{{ $detail->color?->name }}"
                                                data-color-code="{{ $detail->color?->code }}"
                                                data-image="{{ $image }}">
                                                Add to cart
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
