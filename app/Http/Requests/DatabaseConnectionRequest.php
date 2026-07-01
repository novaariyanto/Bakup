<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatabaseConnectionRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];

        if ($this->isUpdate()) {
            $rules['password'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['password'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $validated['port'] = (int) $validated['port'];

        return $validated;
    }

    public function isUpdate(): bool
    {
        return $this->route('database_connection') !== null;
    }
}
