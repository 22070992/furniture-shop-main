@extends('layouts.app')
@section('content')
    {{-- Slider --}}
    @include('pages.dashboard.slider')

    <div class="banner-area pt-100 pb-70" data-feedback-id="home.category-banners" data-page-section="category-banners">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="banner-wrap mb-30" data-feedback-id="home.category.office-chair" data-component="CategoryBanner">
                        <a href="/shop?category={{ $featured_sections->firstWhere('title', 'Office Chair')['category_id'] ?? 'all' }}"><img src="{{ asset('images/banner/banner-1.png') }}" alt=""></a>
                        <div class="banner-content-1">
                            <h5>new arrival</h5>
                            <h3>Office Chair</h3>
                            <div class="banner-btn">
                                <a href="/shop?category={{ $featured_sections->firstWhere('title', 'Office Chair')['category_id'] ?? 'all' }}">Shop Now</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="banner-wrap mb-30" data-feedback-id="home.category.hanging-chair" data-component="CategoryBanner">
                        <a href="/shop?category={{ $featured_sections->firstWhere('title', 'Hanging Chair')['category_id'] ?? 'all' }}"><img src="{{ asset('images/banner/banner-2.png') }}" alt=""></a>
                        <div class="banner-content-1">
                            <h5>new arrival</h5>
                            <h3>Hanging Chair</h3>
                            <div class="banner-btn">
                                <a href="/shop?category={{ $featured_sections->firstWhere('title', 'Hanging Chair')['category_id'] ?? 'all' }}">Shop Now</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="banner-wrap mb-30" data-feedback-id="home.category.folding-chair" data-component="CategoryBanner">
                        <a href="/shop?category={{ $featured_sections->firstWhere('title', 'Folding Chair')['category_id'] ?? 'all' }}"><img src="{{ asset('images/banner/banner-3.png') }}" alt=""></a>
                        <div class="banner-content-1">
                            <h5>new arrival</h5>
                            <h3>Folding Chair</h3>
                            <div class="banner-btn">
                                <a href="/shop?category={{ $featured_sections->firstWhere('title', 'Folding Chair')['category_id'] ?? 'all' }}">Shop Now</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('pages.dashboard.featured-categories')
    {{-- Deal of day --}}
    @include('pages.dashboard.deal-of-day')
    {{-- Banner --}}
    @include('pages.dashboard.banner')




    {{-- Hot product --}}
    @include('pages.dashboard.hot-products')
    {{-- Services --}}
    @include('pages.dashboard.services')
@endsection
