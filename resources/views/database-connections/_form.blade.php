@php
    $isEdit = isset($connection);
@endphp

@if ($errors->any())
    <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-300">
        Periksa kembali form. Ada {{ $errors->count() }} field yang perlu diperbaiki.
    </div>
@endif

<div>
    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nama</label>
    <input name="name" type="text" value="{{ old('name', $connection->name ?? '') }}" class="input-field" />
    @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Host</label>
        <input name="host" type="text" value="{{ old('host', $connection->host ?? '127.0.0.1') }}" class="input-field" />
        @error('host') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Port</label>
        <input name="port" type="number" min="1" max="65535" value="{{ old('port', $connection->port ?? 3306) }}" class="input-field" />
        @error('port') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Database</label>
    <input name="database_name" type="text" value="{{ old('database_name', $connection->database_name ?? '') }}" class="input-field" />
    @error('database_name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Username</label>
        <input name="username" type="text" value="{{ old('username', $connection->username ?? 'root') }}" class="input-field" />
        @error('username') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">
            Password @if($isEdit)<span class="text-zinc-500">(kosongkan jika tidak diubah)</span>@endif
        </label>
        <input name="password" type="password" class="input-field" autocomplete="new-password" />
        @error('password') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<label class="flex items-center gap-2 text-sm text-zinc-300">
    <input type="hidden" name="is_active" value="0">
    <input
        name="is_active"
        type="checkbox"
        value="1"
        @checked(old('is_active', $connection->is_active ?? true))
        class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
    >
    Koneksi aktif
</label>
