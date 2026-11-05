<a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="{{ config('app.name') }} home">
    <img src="{{ asset('images/tarc-logo.png') }}" srcset="{{ asset('images/tarc-logo.png') }} 1x, {{ asset('images/tarc-logo@2x.png') }} 2x"
         width="768" height="246" alt="{{ config('app.name') }}" class="{{ $class ?? 'h-9 w-auto sm:h-10' }}">
</a>
