<?php

namespace App\Http\Requests\Automation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DolibarrConfigurationRequest extends FormRequest
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
        return [
            'api_login' => [
                'required',
                'string',
                'max:255',
            ],
            'api_password' => [
                'required',
                'string',
                'max:255',
            ],
            'api_url' => [
                'required',
                'url',
                'max:255',
            ],
        ];
    }
}
