<?php

namespace App\Http\Requests\Automation;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TelegramConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bot_token' => $this->trimmedInput('bot_token'),
            'bot_username' => $this->trimmedInput('bot_username'),
            'chat_id' => $this->trimmedInput('chat_id'),
            'webhook_secret' => $this->trimmedInput('webhook_secret'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $hasTelegramConfiguration = $team instanceof Team
            ? $team->telegramConfiguration()->exists()
            : false;

        return [
            'bot_token' => [
                $hasTelegramConfiguration ? 'nullable' : 'required',
                'string',
                'max:255',
            ],
            'bot_username' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Normalize a string input by trimming whitespace while preserving nulls.
     */
    private function trimmedInput(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
