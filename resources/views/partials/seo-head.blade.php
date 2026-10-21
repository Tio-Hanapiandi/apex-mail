<!-- SEO Meta Tags -->
<meta name="description" content="@yield('meta_description', 'ApexMail - Platform Webmail Perusahaan, Hosting Email Aman & Kolaborasi Terpadu untuk Organisasi Modern.')">
<meta name="keywords" content="ApexMail, webmail, corporate email, email hosting, kalender perusahaan, direktori kontak, webmail aman, groupware">
<meta name="author" content="ApexMail">
<meta name="robots" content="index, follow">
<meta name="application-name" content="ApexMail">
<meta name="theme-color" content="#2563eb">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="ApexMail">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="@yield('title', 'ApexMail - Corporate Webmail & Collaboration Suite')">
<meta property="og:description" content="@yield('meta_description', 'Platform Webmail Perusahaan, Hosting Email Aman & Kolaborasi Terpadu untuk Organisasi Modern.')">
<meta property="og:image" content="{{ asset('apple-touch-icon.png') }}">
<meta property="og:site_name" content="ApexMail">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary">
<meta name="twitter:url" content="{{ url()->current() }}">
<meta name="twitter:title" content="@yield('title', 'ApexMail - Corporate Webmail & Collaboration Suite')">
<meta name="twitter:description" content="@yield('meta_description', 'Platform Webmail Perusahaan, Hosting Email Aman & Kolaborasi Terpadu untuk Organisasi Modern.')">
<meta name="twitter:image" content="{{ asset('apple-touch-icon.png') }}">

<!-- Favicon & Touch Icons (Uniform ApexMail Branding) -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
