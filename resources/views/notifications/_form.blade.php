@php
    $isEdit = isset($channel);
    $config = $isEdit ? ($channel->config ?? []) : [];
    $driver = old('driver', $isEdit ? $channel->driver->value : 'email');
@endphp

<div x-data="{ driver: @js($driver) }">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nama</label>
        <input name="name" type="text" value="{{ old('name', $channel->name ?? '') }}" class="input-field" />
        @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Driver</label>
        <select name="driver" x-model="driver" class="input-field" @if($isEdit) disabled @endif>
            @foreach ($drivers as $driverOption)
                <option value="{{ $driverOption->value }}">{{ $driverOption->label() }}</option>
            @endforeach
        </select>
        @if ($isEdit)
            <input type="hidden" name="driver" value="{{ $channel->driver->value }}">
        @endif
        <p class="mt-1 text-xs text-zinc-500">
            @foreach ($drivers as $driverOption)
                <span x-show="driver === @js($driverOption->value)" x-cloak>{{ $driverOption->description() }}</span>
            @endforeach
        </p>
        @error('driver') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div x-show="driver === 'email'" x-cloak class="space-y-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Email Penerima</label>
            <input name="email_recipients" type="text" value="{{ old('email_recipients', $config['recipients'] ?? '') }}" class="input-field" placeholder="admin@example.com, ops@example.com" />
            <p class="mt-1 text-xs text-zinc-500">Pisahkan beberapa email dengan koma</p>
            @error('email_recipients') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Subject Prefix</label>
            <input name="email_subject_prefix" type="text" value="{{ old('email_subject_prefix', $config['subject_prefix'] ?? '[Backup Manager]') }}" class="input-field" placeholder="[Backup Manager]" />
            @error('email_subject_prefix') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    <div x-show="driver === 'whatsapp'" x-cloak class="space-y-4">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">API URL</label>
            <input name="whatsapp_api_url" type="url" value="{{ old('whatsapp_api_url', $config['api_url'] ?? '') }}" class="input-field" placeholder="https://api.fonnte.com/send" />
            @error('whatsapp_api_url') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">
                API Token @if($isEdit)<span class="text-zinc-500">(kosongkan jika tidak diubah)</span>@endif
            </label>
            <input name="whatsapp_api_token" type="password" class="input-field" />
            @error('whatsapp_api_token') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nomor / Target</label>
            <input name="whatsapp_recipient" type="text" value="{{ old('whatsapp_recipient', $config['recipient'] ?? '') }}" class="input-field" placeholder="6281234567890" />
            @error('whatsapp_recipient') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="space-y-2 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Kirim Notifikasi Saat</p>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="notify_on_success" value="0">
            <input
                name="notify_on_success"
                type="checkbox"
                value="1"
                @checked(old('notify_on_success', $channel->notify_on_success ?? true))
                class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
            >
            Backup berhasil
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="notify_on_failure" value="0">
            <input
                name="notify_on_failure"
                type="checkbox"
                value="1"
                @checked(old('notify_on_failure', $channel->notify_on_failure ?? true))
                class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
            >
            Backup gagal
        </label>
    </div>

    <label class="flex items-center gap-2 text-sm text-zinc-300">
        <input type="hidden" name="is_active" value="0">
        <input
            name="is_active"
            type="checkbox"
            value="1"
            @checked(old('is_active', $channel->is_active ?? true))
            class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
        >
        Channel aktif
    </label>
</div>
