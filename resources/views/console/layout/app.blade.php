<!doctype html>

<html lang="en" class="layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr" data-skin="default"
    data-assets-path="{{ asset('themes/console/assets') }}/" data-template="vertical-menu-template" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title', 'Cutting Machine')</title>

    @include('console.layout.partials.styles')
    @include('console.layout.partials.scripts-in-head')

    @include('console.layout.partials.toastr')
    @stack('styles')
</head>

<body>
    @include('console.layout.partials.loader')

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            @include('console.layout.partials.sidebar')

            <div class="layout-page">

                @include('console.layout.partials.navbar')

                <div class="content-wrapper">

                    @yield('content')

                    @include('console.layout.partials.footer')

                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>

        <div class="drag-target"></div>
    </div>

    @stack('partials')

    @include('console.layout.partials.settings-access-modal')
    @include('console.layout.partials.access-password-gate')

    @include('console.layout.partials.scripts')

    @stack('scripts')

</body>

</html>
