<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' – ' : '' }}{{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:ital,wght@0,400..900;1,400..700&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%231f6f5c'/><path d='M23 4.8a4.4 4.4 0 0 1 4.4 4.4c0 3.2-4.4 7.6-4.4 7.6s-4.4-4.4-4.4-7.6A4.4 4.4 0 0 1 23 4.8z' fill='%23d9971a'/><circle cx='7.5' cy='24' r='2.6' fill='%23fff'/><path d='M7.5 24c3-7 7.5 1 10.5-5.5' stroke='%23d9971a' stroke-width='2.6' fill='none' stroke-linecap='round'/></svg>">
@vite(['resources/css/app.css', 'resources/js/app.js'])
