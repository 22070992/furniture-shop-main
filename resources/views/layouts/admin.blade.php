<!doctype html>
<html lang="en">
@include('admin.components.head')

<body data-bs-theme="dark">
    <script src="{{ asset('vendor/theme/theme.js') }}"></script>
    <script src="{{ asset('js/jquery.min.js') }}" defer></script>
    <script src="{{ asset('vendor/tabler/tabler.min.js') }}" defer></script>
    <script src="{{ asset('js/visual_feedback.js') }}" defer></script>
    <div id="asset" data-asset="{{ asset('') }}"></div>
    <div class="page">
        @include('admin.components.navbar')
        <div class="page-wrapper">
            @include('admin.components.header')
            @yield('content')
            @include('admin.components.footer')
        </div>
    </div>
    @include('components.visual-feedback-widget')
</body>

</html>
