<x-layouts.guest title="Login">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <h2 class="text-lg font-semibold text-zinc-100">Masuk ke Backup Manager</h2>
            <p class="mt-1 text-sm text-zinc-500">Kelola backup MySQL dari satu dashboard.</p>
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-zinc-300">Email</label>
            <input
                type="email"
                name="email"
                id="email"
                value="{{ old('email') }}"
                autocomplete="email"
                class="input-field @error('email') border-red-500/50 @enderror"
                autofocus
                required
            />
            @error('email')
                <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-zinc-300">Password</label>
            <input
                type="password"
                name="password"
                id="password"
                autocomplete="current-password"
                class="input-field @error('password') border-red-500/50 @enderror"
                required
            />
            @error('password')
                <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-zinc-400">
            <input
                type="checkbox"
                name="remember"
                value="1"
                class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                @checked(old('remember'))
            >
            Ingat saya
        </label>

        <button type="submit" class="btn-primary w-full">
            Masuk
        </button>
    </form>
</x-layouts.guest>
