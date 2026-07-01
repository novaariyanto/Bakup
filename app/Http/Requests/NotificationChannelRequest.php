<?php

namespace App\Http\Requests;

use App\Enums\NotificationDriver;
use Illuminate\Foundation\Http\FormRequest;

class NotificationChannelRequest extends FormRequest
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
            'driver' => ['required', 'in:email,whatsapp'],
            'is_active' => ['boolean'],
            'notify_on_success' => ['boolean'],
            'notify_on_failure' => ['boolean'],
        ];

        if ($driver === NotificationDriver::Email->value) {
            $rules['email_recipients'] = ['required', 'string', 'max:1000'];
            $rules['email_subject_prefix'] = ['nullable', 'string', 'max:100'];
        }

        if ($driver === NotificationDriver::WhatsApp->value) {
            $rules['whatsapp_api_url'] = ['required', 'url', 'max:500'];
            $rules['whatsapp_api_token'] = [! $this->isUpdate() ? 'required' : 'nullable', 'string', 'max:500'];
            $rules['whatsapp_recipient'] = ['required', 'string', 'max:50'];
        }

        return $rules;
    }

    public function isUpdate(): bool
    {
        return $this->route('notification_channel') !== null;
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
            'notify_on_success' => $validated['notify_on_success'] ?? true,
            'notify_on_failure' => $validated['notify_on_failure'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConfig(): array
    {
        $driver = $this->string('driver')->toString();

        return match ($driver) {
            NotificationDriver::Email->value => array_filter([
                'recipients' => $this->string('email_recipients')->toString(),
                'subject_prefix' => $this->filled('email_subject_prefix')
                    ? $this->string('email_subject_prefix')->toString()
                    : null,
            ], fn ($value) => $value !== null),
            NotificationDriver::WhatsApp->value => array_filter([
                'api_url' => $this->string('whatsapp_api_url')->toString(),
                'api_token' => $this->filled('whatsapp_api_token')
                    ? $this->string('whatsapp_api_token')->toString()
                    : null,
                'recipient' => $this->string('whatsapp_recipient')->toString(),
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
        $driver = NotificationDriver::from($this->string('driver')->toString());
        $incoming = $this->buildConfig();

        if ($driver === NotificationDriver::WhatsApp) {
            if (! array_key_exists('api_token', $incoming) || trim((string) ($incoming['api_token'] ?? '')) === '') {
                $incoming['api_token'] = $existing['api_token'] ?? null;
            }
        }

        return array_merge($existing, $incoming);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function formDefaultsFromConfig(array $config, bool $clearSecrets = false): array
    {
        return [
            'email_recipients' => $config['recipients'] ?? '',
            'email_subject_prefix' => $config['subject_prefix'] ?? '[Backup Manager]',
            'whatsapp_api_url' => $config['api_url'] ?? '',
            'whatsapp_api_token' => $clearSecrets ? '' : ($config['api_token'] ?? ''),
            'whatsapp_recipient' => $config['recipient'] ?? '',
        ];
    }
}
