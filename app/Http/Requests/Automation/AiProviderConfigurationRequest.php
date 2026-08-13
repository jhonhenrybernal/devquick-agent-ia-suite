<?php

namespace App\Http\Requests\Automation;

use App\Enums\AiProvider;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiProviderConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $existingConfiguration = $team instanceof Team
            ? $team->aiProviderConfiguration
            : null;
        $provider = AiProvider::tryFrom((string) $this->input('provider'))
            ?? AiProvider::OpenAi;
        $requiresApiKey = in_array($provider, [AiProvider::OpenAi, AiProvider::Gemini], true)
            && blank($existingConfiguration?->api_key);

        return [
            'provider' => ['required', 'string', Rule::in(array_map(
                static fn (AiProvider $provider): string => $provider->value,
                AiProvider::cases(),
            ))],
            'model' => ['nullable', 'string', 'max:255'],
            'api_key' => [
                $requiresApiKey ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'base_url' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
