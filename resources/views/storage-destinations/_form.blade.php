@php
    $isEdit = isset($destination);
    $config = $isEdit ? ($destination->config ?? []) : [];
    $driver = old('driver', $isEdit ? $destination->driver->value : 'local');
    $sftpAuthMethod = old('sftp_auth_method', $config['auth_method'] ?? 'password');
@endphp

<div x-data="{ driver: @js($driver), sftpAuth: @js($sftpAuthMethod) }">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nama</label>
        <input name="name" type="text" value="{{ old('name', $destination->name ?? '') }}" class="input-field" />
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
            <input type="hidden" name="driver" value="{{ $destination->driver->value }}">
        @endif
        <p class="mt-1 text-xs text-zinc-500">
            @foreach ($drivers as $driverOption)
                <span x-show="driver === @js($driverOption->value)" x-cloak>{{ $driverOption->description() }}</span>
            @endforeach
        </p>
        @error('driver') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div x-show="driver === 'local'" x-cloak>
        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Path</label>
        <input name="local_path" type="text" value="{{ old('local_path', $config['path'] ?? 'default') }}" class="input-field" placeholder="default atau backups/production" />
        <p class="mt-1 text-xs text-zinc-500">Relatif ke storage/app/backups/ atau path absolut di dalam storage/</p>
        @error('local_path') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div x-show="driver === 'sftp'" x-cloak class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Host</label>
                <input name="sftp_host" type="text" value="{{ old('sftp_host', $config['host'] ?? '') }}" class="input-field" />
                @error('sftp_host') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Port</label>
                <input name="sftp_port" type="number" value="{{ old('sftp_port', $config['port'] ?? 22) }}" class="input-field" />
                @error('sftp_port') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-zinc-300">Authentication Method</label>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input
                        type="radio"
                        name="sftp_auth_method"
                        value="password"
                        x-model="sftpAuth"
                        class="border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                    >
                    Password
                </label>
                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input
                        type="radio"
                        name="sftp_auth_method"
                        value="private_key"
                        x-model="sftpAuth"
                        class="border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                    >
                    Private Key
                </label>
            </div>
            @error('sftp_auth_method') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Username</label>
            <input name="sftp_username" type="text" value="{{ old('sftp_username', $config['username'] ?? '') }}" class="input-field" />
            @error('sftp_username') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div x-show="sftpAuth === 'password'" x-cloak>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">
                Password @if($isEdit)<span class="text-zinc-500">(kosongkan jika tidak diubah)</span>@endif
            </label>
            <input name="sftp_password" type="password" class="input-field" autocomplete="new-password" :disabled="sftpAuth !== 'password'" />
            @error('sftp_password') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div x-show="sftpAuth === 'private_key'" x-cloak class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">
                    Private Key @if($isEdit)<span class="text-zinc-500">(kosongkan jika tidak diubah)</span>@endif
                </label>
                <textarea name="sftp_private_key" rows="6" class="input-field font-mono text-xs" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----" :disabled="sftpAuth !== 'private_key'">{{ old('sftp_private_key') }}</textarea>
                <p class="mt-1 text-xs text-zinc-500">Mendukung format PEM RSA dan OpenSSH</p>
                @error('sftp_private_key') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">
                    Passphrase @if($isEdit)<span class="text-zinc-500">(opsional, kosongkan jika tidak diubah)</span>@endif
                </label>
                <input name="sftp_passphrase" type="password" class="input-field" autocomplete="new-password" :disabled="sftpAuth !== 'private_key'" />
                @error('sftp_passphrase') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Root Path</label>
            <input name="sftp_root" type="text" value="{{ old('sftp_root', $config['root'] ?? '/') }}" class="input-field" placeholder="/backups" />
            @error('sftp_root') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    <div x-show="driver === 's3'" x-cloak class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Access Key</label>
                <input name="s3_key" type="text" value="{{ old('s3_key', $config['key'] ?? '') }}" class="input-field" />
                @error('s3_key') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">
                    Secret Key @if($isEdit)<span class="text-zinc-500">(kosongkan jika tidak diubah)</span>@endif
                </label>
                <input name="s3_secret" type="password" class="input-field" />
                @error('s3_secret') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Region</label>
                <input name="s3_region" type="text" value="{{ old('s3_region', $config['region'] ?? 'us-east-1') }}" class="input-field" />
                @error('s3_region') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Bucket</label>
                <input name="s3_bucket" type="text" value="{{ old('s3_bucket', $config['bucket'] ?? '') }}" class="input-field" />
                @error('s3_bucket') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Endpoint (opsional)</label>
            <input name="s3_endpoint" type="text" value="{{ old('s3_endpoint', $config['endpoint'] ?? '') }}" class="input-field" placeholder="https://xxx.r2.cloudflarestorage.com" />
            <p class="mt-1 text-xs text-zinc-500">Diperlukan untuk R2, Wasabi, MinIO, dan provider S3-compatible lainnya</p>
            @error('s3_endpoint') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Prefix (opsional)</label>
            <input name="s3_prefix" type="text" value="{{ old('s3_prefix', $config['prefix'] ?? '') }}" class="input-field" placeholder="backups/mysql" />
            @error('s3_prefix') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="s3_use_path_style" value="0">
            <input
                name="s3_use_path_style"
                type="checkbox"
                value="1"
                @checked(old('s3_use_path_style', $config['use_path_style_endpoint'] ?? false))
                class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
            >
            Gunakan path-style endpoint (MinIO, dll.)
        </label>
    </div>

    <label class="flex items-center gap-2 text-sm text-zinc-300">
        <input type="hidden" name="is_active" value="0">
        <input
            name="is_active"
            type="checkbox"
            value="1"
            @checked(old('is_active', $destination->is_active ?? true))
            class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
        >
        Destination aktif
    </label>
</div>
