<head>
    @php
        $seo = $seo ?? [];
        $seoTitle = $seo['title'] ?? config('shop.seo.default_title', 'Furemir Furniture');
        $seoDescription = $seo['description'] ?? config('shop.seo.default_description', 'Curated furniture for modern homes');
        $seoCanonical = $seo['canonical'] ?? url()->current();
        $seoRobots = $seo['robots'] ?? 'index, follow';
        $seoImage = $seo['og_image'] ?? url(config('shop.seo.default_image', '/images/logo/logo.png'));
        $structuredData = collect($structuredData ?? [])->filter();
    @endphp
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>{{ $seoTitle }}</title>
    <meta name="robots" content="{{ $seoRobots }}" />
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <link rel="canonical" href="{{ $seoCanonical }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}" />
    <meta property="og:title" content="{{ $seo['og_title'] ?? $seoTitle }}" />
    <meta property="og:url" content="{{ $seoCanonical }}" />
    <meta property="og:site_name" content="{{ $seo['site_name'] ?? config('shop.seo.site_name', 'Furemir') }}" />
    <meta property="og:image" content="{{ $seoImage }}" />
    <meta property="og:description" content="{{ $seo['og_description'] ?? $seoDescription }}" />
    <meta name="twitter:card" content="{{ $seo['twitter_card'] ?? 'summary_large_image' }}">
    <meta name="twitter:title" content="{{ $seo['twitter_title'] ?? $seoTitle }}">
    <meta name="twitter:description" content="{{ $seo['twitter_description'] ?? $seoDescription }}">
    <meta name="twitter:image" content="{{ $seo['twitter_image'] ?? $seoImage }}">

    <link rel="shortcut icon" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/pe-icon-7-stroke.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/themify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/font-awesome.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/aos.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/magnific-popup.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/swiper.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/jquery-ui.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/nice-select.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/easyzoom.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/plugins/slinky.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/visual_feedback.css') }}" />
    {{-- Toastify --}}
    <link rel="stylesheet" href="{{ asset('css/vendor/toastify.min.css') }}" />

    {{-- Fontaw --}}
    <script src="{{ asset('js/vendor/fontawesome.js') }}" crossorigin="anonymous"></script>
    @foreach ($structuredData as $schema)
        <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endforeach
    @stack('head')
</head>
