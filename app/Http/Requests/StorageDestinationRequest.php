<?php

namespace App\Http\Requests;

use App\Enums\SftpAuthenticationMethod;
use App\Enums\StorageDriver;
use App\Services\Storage\Sftp\SftpAuthenticationResolver;
use App\Services\Storage\Sftp\SftpConfigurationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorageDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|int>>
     */
    public function rules(): array
    {
        $driver = $this->string('driver')->toString();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:local,sftp,s3'],
            'is_active' => ['boolean'],
        ];

        if ($driver === StorageDriver::Local->value) {
            $rules['local_path'] = ['required', 'string', 'max:500'];
        }

        if ($driver === StorageDriver::Sftp->value) {
            $rules['sftp_host'] = ['required', 'string', 'max:255'];
            $rules['sftp_port'] = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['sftp_username'] = ['required', 'string', 'max:255'];
            $rules['sftp_root'] = ['nullable', 'string', 'max:500'];
            $rules['sftp_auth_method'] = ['required', 'in:password,private_key'];

            $authMethod = $this->string('sftp_auth_method')->toString();

            if ($authMethod === SftpAuthenticationMethod::Password->value) {
                $rules['sftp_password'] = [
                    ! $this->isUpdate() ? 'required' : 'nullable',
                    'string',
                    'max:5000',
                ];
                $rules['sftp_private_key'] = ['prohibited'];
                $rules['sftp_passphrase'] = ['prohibited'];
            }

            if ($authMethod === SftpAuthenticationMethod::PrivateKey->value) {
                $rules['sftp_private_key'] = [
                    ! $this->isUpdate() ? 'required' : 'nullable',
                    'string',
                    'max:10000',
                ];
                $rules['sftp_passphrase'] = ['nullable', 'string', 'max:500'];
                $rules['sftp_password'] = ['prohibited'];
            }
        }

        if ($driver === StorageDriver::S3->value) {
            $rules['s3_key'] = ['required', 'string', 'max:255'];
            $rules['s3_secret'] = [! $this->isUpdate() ? 'required' : 'nullable', 'string', 'max:255'];
            $rules['s3_region'] = ['required', 'string', 'max:100'];
            $rules['s3_bucket'] = ['required', 'string', 'max:255'];
            $rules['s3_endpoint'] = ['nullable', 'string', 'max:500'];
            $rules['s3_prefix'] = ['nullable', 'string', 'max:500'];
            $rules['s3_use_path_style'] = ['boolean'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->string('driver')->toString() !== StorageDriver::Sftp->value) {
                return;
            }

            if ($this->string('sftp_auth_method')->toString() !== SftpAuthenticationMethod::PrivateKey->value) {
                return;
            }

            $privateKey = trim($this->string('sftp_private_key')->toString());
            if ($privateKey === '') {
                return;
            }

            try {
                app(SftpConfigurationValidator::class)->validatePrivateKeyFormat($privateKey);
            } catch (\App\Exceptions\StorageDestinationException $exception) {
                $validator->errors()->add('sftp_private_key', $exception->userMessage());
            }
        });
    }

    public function isUpdate(): bool
    {
        return $this->route('backup_destination') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toServicePayload(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'config' => $this->buildConfig(),
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConfig(): array
    {
        $driver = $this->string('driver')->toString();

        if ($driver === StorageDriver::Sftp->value) {
            return app(SftpAuthenticationResolver::class)->normalizeForStorage([
                'host' => $this->string('sftp_host')->toString(),
                'port' => (int) $this->input('sftp_port', 22),
                'username' => $this->string('sftp_username')->toString(),
                'auth_method' => $this->string('sftp_auth_method')->toString(),
                'root' => $this->string('sftp_root')->toString() ?: '/',
                'password' => $this->filled('sftp_password') ? $this->string('sftp_password')->toString() : null,
                'private_key' => $this->filled('sftp_private_key') ? $this->string('sftp_private_key')->toString() : null,
                'passphrase' => $this->filled('sftp_passphrase') ? $this->string('sftp_passphrase')->toString() : null,
            ]);
        }

        return match ($driver) {
            StorageDriver::Local->value => [
                'path' => $this->string('local_path')->toString(),
            ],
            StorageDriver::S3->value => array_filter([
                'key' => $this->string('s3_key')->toString(),
                'secret' => $this->filled('s3_secret') ? $this->string('s3_secret')->toString() : null,
                'region' => $this->string('s3_region')->toString(),
                'bucket' => $this->string('s3_bucket')->toString(),
                'endpoint' => $this->filled('s3_endpoint') ? $this->string('s3_endpoint')->toString() : null,
                'prefix' => $this->filled('s3_prefix') ? $this->string('s3_prefix')->toString() : null,
                'use_path_style_endpoint' => $this->boolean('s3_use_path_style'),
            ], fn ($value) => $value !== null),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function mergeSecretsForTest(array $existing): array
    {
        $driver = StorageDriver::from($this->string('driver')->toString());

        if ($driver !== StorageDriver::Sftp) {
            $incoming = $this->buildConfig();

            $secretKeys = match ($driver) {
                StorageDriver::Local => [],
                StorageDriver::S3 => ['secret'],
                default => [],
            };

            foreach ($secretKeys as $key) {
                if (! array_key_exists($key, $incoming) || trim((string) ($incoming[$key] ?? '')) === '') {
                    $incoming[$key] = $existing[$key] ?? null;
                }
            }

            return array_merge($existing, $incoming);
        }

        return app(SftpAuthenticationResolver::class)->mergeConfig(
            $existing,
            $this->buildConfig(),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function formDefaultsFromConfig(array $config, bool $clearSecrets = false): array
    {
        $authMethod = $config['auth_method'] ?? (
            ! empty($config['private_key']) && empty($config['password'])
                ? SftpAuthenticationMethod::PrivateKey->value
                : SftpAuthenticationMethod::Password->value
        );

        return [
            'local_path' => $config['path'] ?? 'default',
            'sftp_host' => $config['host'] ?? '',
            'sftp_port' => (int) ($config['port'] ?? 22),
            'sftp_username' => $config['username'] ?? '',
            'sftp_auth_method' => $authMethod,
            'sftp_password' => $clearSecrets ? '' : ($config['password'] ?? ''),
            'sftp_private_key' => $clearSecrets ? '' : ($config['private_key'] ?? ''),
            'sftp_passphrase' => $clearSecrets ? '' : ($config['passphrase'] ?? ''),
            'sftp_root' => $config['root'] ?? '/',
            's3_key' => $config['key'] ?? '',
            's3_secret' => $clearSecrets ? '' : ($config['secret'] ?? ''),
            's3_region' => $config['region'] ?? 'us-east-1',
            's3_bucket' => $config['bucket'] ?? '',
            's3_endpoint' => $config['endpoint'] ?? '',
            's3_prefix' => $config['prefix'] ?? '',
            's3_use_path_style' => (bool) ($config['use_path_style_endpoint'] ?? false),
        ];
    }
}
