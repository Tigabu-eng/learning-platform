@auth
    <details class="relative" data-menu>
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-mist">
            <span class="grid size-8 place-items-center rounded-full bg-pine text-xs font-bold text-white">{{ auth()->user()->initials() }}</span>
            <span class="hidden max-w-40 truncate whitespace-nowrap text-sm font-semibold sm:inline">{{ auth()->user()->name }}</span>
        </summary>
        <div class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
            <a class="block px-4 py-2 hover:bg-mist" href="{{ route('dashboard') }}">My learning</a>
            <a class="block px-4 py-2 hover:bg-mist" href="{{ route('orders') }}">Purchases</a>
            @if (auth()->user()->canTeach())<a class="block px-4 py-2 hover:bg-mist" href="{{ route('studio.dashboard') }}">Studio</a>@endif
            @if (auth()->user()->isAdmin())<a class="block px-4 py-2 hover:bg-mist" href="{{ route('admin.dashboard') }}">Admin</a>@endif
            <form method="POST" action="{{ route('logout') }}" class="border-t border-line">@csrf
                <button class="block w-full px-4 py-2 text-left hover:bg-mist">Log out</button>
            </form>
        </div>
    </details>
@else
    <div class="flex items-center gap-2">
        <a href="{{ route('login') }}" class="btn-ghost btn-sm">Log in</a>
        <a href="{{ route('register') }}" class="btn-primary btn-sm">Sign up</a>
    </div>
@endauth
