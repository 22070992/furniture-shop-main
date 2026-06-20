@extends('layouts.app')

@section('content')
    <div class="room-collection-page" data-feedback-id="collections.show.{{ $collection->slug }}"
        data-component="RoomCollectionLanding">
        <section class="room-collection-hero"
            style="background-image: linear-gradient(90deg, rgba(18, 24, 31, .78), rgba(18, 24, 31, .26)), url('{{ $heroImage }}');">
            <div class="container">
                <div class="breadcrumb-content room-collection-breadcrumb">
                    <ul>
                        <li><a href="{{ route('user') }}">Home</a></li>
                        <li><a href="{{ route('collections.index') }}">Collections</a></li>
                        <li>{{ $collection->title }}</li>
                    </ul>
                </div>
                <div class="room-collection-hero-content">
                    <h1>{{ $collection->title }}</h1>
                    <p>{{ $collection->description }}</p>
                    <div class="room-collection-actions">
                        <a href="#collection-products" class="btn btn-primary">Shop products</a>
                        <a href="{{ route('shop') }}" class="btn">All furniture</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="room-collection-band pt-70 pb-50">
            <div class="container">
                <div class="section-title-tab-wrap mb-35">
                    <div class="section-title-2">
                        <h2>Featured Categories</h2>
                    </div>
                    <div class="tab-style-1 nav">
                        @foreach ($categories->take(5) as $category)
                            <a href="{{ route('shop', ['category' => $category->category_id]) }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="room-collection-category-grid">
                    @forelse ($categories->take(6) as $category)
                        <a href="{{ route('shop', ['category' => $category->category_id]) }}"
                            data-feedback-id="collections.category.{{ $category->category_id }}">
                            <strong>{{ $category->name }}</strong>
                            <span>{{ \Illuminate\Support\Str::limit($category->description, 90) }}</span>
                        </a>
                    @empty
                        <p class="text-muted">No matching categories yet.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="product-area pb-55" id="collection-products" data-feedback-id="collections.products">
            <div class="container">
                <div class="section-title-tab-wrap mb-45">
                    <div class="section-title-2">
                        <h2>Featured Products</h2>
                    </div>
                    <div class="tab-style-1 nav">
                        <a href="{{ route('shop') }}">Open shop filters</a>
                    </div>
                </div>
                <div class="row">
                    @forelse ($featuredProducts->take(8) as $product)
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                            @include('components.recommendation-product-card', ['product' => $product])
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-muted">No active products for this collection yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($completeRoomProducts->isNotEmpty())
            <section class="complete-room-area pb-60" data-feedback-id="collections.complete-room">
                <div class="container">
                    <div class="section-title-tab-wrap mb-35">
                        <div class="section-title-2">
                            <h2>Complete the Room</h2>
                        </div>
                    </div>
                    <div class="complete-room-summary">
                        <span>
                            Combo estimate:
                            {{ number_format($completeRoomProducts->sum(fn ($item) => $item->presentation->min_price), 0, '.', ',') }}đ
                        </span>
                        <em>Each item is validated by stock before checkout.</em>
                    </div>
                    <div class="row">
                        @foreach ($completeRoomProducts as $product)
                            <div class="col-lg-3 col-md-4 col-sm-6 col-12">
                                @include('components.recommendation-product-card', ['product' => $product])
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="room-collection-band pb-90">
            <div class="container">
                <div class="section-title-tab-wrap mb-35">
                    <div class="section-title-2">
                        <h2>Related Collections</h2>
                    </div>
                </div>
                <div class="row">
                    @foreach ($relatedCollections as $related)
                        <div class="col-lg-4 col-md-6 col-12">
                            <a class="room-collection-card" href="{{ $related->publicUrl() }}">
                                <span>{{ $related->title }}</span>
                                <small>{{ \Illuminate\Support\Str::limit($related->description, 120) }}</small>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
