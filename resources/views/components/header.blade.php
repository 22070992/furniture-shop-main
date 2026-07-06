<header class="header-area header-responsive-padding " data-feedback-id="site.header" data-component="SiteHeader">
    <div class="header-bottom sticky-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-3 col-md-6 col-6">
                    <div class="logo">
                        <a href="/" class="furemir-logo">Furemir</a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block d-flex justify-content-center">
                    <div class="main-menu text-center">
                        <nav data-feedback-id="site.navigation" data-component="MainNavigation">
                            <ul>
                                <li><a href="/" data-feedback-id="site.nav.home">HOME</a> </li>
                                <li><a href="/shop" data-feedback-id="site.nav.shop">SHOP</a> </li>
                                <li><a href="/myorders" data-feedback-id="site.nav.orders">MY ORDERS</a></li>
                                <li><a href="/my-feedback" data-feedback-id="site.nav.feedback">MY FEEDBACK</a></li>
                                <li><a href="/about" data-feedback-id="site.nav.about">ABOUT</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-6">
                    <div class="header-action-wrap">
                        <div class="header-action-style header-search-1">
                            <a class="search-toggle" href="#">
                                <i class="pe-7s-search s-open"></i>
                                <i class="pe-7s-close s-close"></i>
                            </a>
                            <div class="search-wrap-1">
                                <form id="search-header-form" action="#" method="GET">
                                    <input placeholder="Search products…" type="text">
                                    <button type="submit" class="button-search"><i class="pe-7s-search"></i></button>
                                </form>
                            </div>
                        </div>

                        <div class="header-action-style header-action-cart">
                            <a class="cart-active" href="#"><i class="pe-7s-shopbag"></i>
                                <span class="js-total-cart product-count "></span>
                            </a>
                        </div>
                        <div class="header-action-style header-action-wishlist">
                            <a href="{{ route('wishlist.index') }}" title="Wishlist"><i class="pe-7s-like"></i>
                                <span class="js-wishlist-count product-count @if (($wishlistCount ?? 0) > 0) bg-black @endif">{{ ($wishlistCount ?? 0) > 0 ? $wishlistCount : '' }}</span>
                            </a>
                        </div>
                        <div class="header-action-style header-action-compare">
                            <a href="{{ route('compare.index') }}" title="Compare"><i class="pe-7s-shuffle"></i>
                                <span class="js-compare-count product-count @if (($compareCount ?? 0) > 0) bg-black @endif">{{ ($compareCount ?? 0) > 0 ? $compareCount : '' }}</span>
                            </a>
                        </div>
                        @if (Auth::user())
                            <div class="header-action-style header-action-account">
                                <a title="Account">
                                    <img class="avatar-img" src="{{ Auth::user()->avatar }}" alt="avatar">
                                </a>
                                <ul>
                                    <li><a href="{{ route('my_account') }}">My Account</a></li>
                                    <li><a href="{{ route('my_orders') }}">My orders</a></li>
                                    <li><a href="{{ route('wishlist.index') }}">Wishlist</a></li>
                                    <li><a href="{{ route('compare.index') }}">Compare</a></li>
                                    <li><a href="{{ route('feedback.mine') }}">My feedback</a></li>
                                    <li><a href="/logout">Logout</a></li>
                                </ul>
                            </div>
                        @else
                            <a href="/login" class="ms-2">
                                <span> Sign in</span>
                            </a>
                        @endif
                        <div class="header-action-style d-block d-lg-none">
                            <a class="mobile-menu-active-button" href="#"><i class="pe-7s-menu"></i></a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</header>


<!-- Mobile Menu start -->
<div class="off-canvas-active">
    <a class="off-canvas-close"><i class=" ti-close "></i></a>
    <div class="off-canvas-wrap">
        <div class="mobile-menu-wrap off-canvas-margin-padding-2">
            <div id="mobile-menu" class="slinky-mobile-menu text-left" data-feedback-id="site.mobile-navigation">
                <ul>
                    <li>
                        <a href="/">HOME</a>
                    </li>
                    <li>
                        <a href="/shop">SHOP</a>
                    </li>
                    <li>
                        <a href="{{ route('wishlist.index') }}">WISHLIST</a>
                    </li>
                    <li>
                        <a href="{{ route('compare.index') }}">COMPARE</a>
                    </li>
                    <li>
                        <a href="/my-feedback">MY FEEDBACK</a>
                    </li>
                    <li>
                        <a href="#">PAGES </a>
                        <ul>
                            <li><a href="#">About Us </a></li>
                            <li><a href="#">Cart Page</a></li>
                            <li><a href="#">Checkout </a></li>
                            <li><a href="/account">My Account</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="/about">ABOUT US</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
