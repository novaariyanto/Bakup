<div class="flex items-center gap-3">
    @auth
        <div class="hidden text-right sm:block">
            <p class="text-sm font-medium text-zinc-200">{{ auth()->user()->name }}</p>
            <p class="text-xs text-zinc-500">Administrator</p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-800 hover:text-zinc-200" title="Logout">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                </svg>
            </button>
        </form>
    @endauth
</div>
