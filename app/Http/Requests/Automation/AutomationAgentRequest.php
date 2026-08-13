<?php

namespace App\Http\Requests\Automation;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutomationAgentRequest extends FormRequest
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
        /** @var Team|null $team */
        $team = $this->route('current_team');

        $parentAgentRule = ['nullable', 'integer'];

        if ($team instanceof Team) {
            $parentAgentRule[] = Rule::exists('automation_agents', 'id')
                ->where('team_id', $team->id);
        } else {
            $parentAgentRule[] = Rule::exists('automation_agents', 'id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_agent_id' => $parentAgentRule,
            'description' => ['nullable', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:5000'],
            'trigger_keyword' => ['nullable', 'string', 'max:255'],
            'target_tool' => ['required', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
