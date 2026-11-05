<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' – ' : '' }}{{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:ital,wght@0,400..900;1,400..700&display=swap" rel="stylesheet">
<link rel="icon" href="{{ asset('images/tarc-icon-32.png') }}" sizes="32x32">
<link rel="icon" href="{{ asset('images/tarc-icon-192.png') }}" sizes="192x192">
<link rel="apple-touch-icon" href="{{ asset('images/tarc-icon-180.png') }}">
<meta name="theme-color" content="#003c54">
@vite(['resources/css/app.css', 'resources/js/app.js'])
