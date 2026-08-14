<?php

namespace App\Http\Requests\Automation;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduledAutomationRequest extends FormRequest
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

        $agentRule = ['nullable', 'integer'];
        $messageRule = ['nullable', 'integer'];

        if ($team instanceof Team) {
            $agentRule[] = Rule::exists('automation_agents', 'id')
                ->where('team_id', $team->id);
            $messageRule[] = Rule::exists('telegram_inbound_messages', 'id')
                ->where('team_id', $team->id);
        } else {
            $agentRule[] = Rule::exists('automation_agents', 'id');
            $messageRule[] = Rule::exists('telegram_inbound_messages', 'id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'source_message_id' => $messageRule,
            'parent_agent_id' => $agentRule,
            'child_agent_id' => $agentRule,
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'paused', 'completed', 'failed'])],
            'trigger_type' => ['required', 'string', Rule::in(['manual', 'interval', 'cron'])],
            'cron_expression' => ['nullable', 'string', 'max:255', 'required_if:trigger_type,cron'],
            'interval_value' => ['nullable', 'integer', 'min:1', 'required_if:trigger_type,interval'],
            'interval_unit' => [
                'nullable',
                'string',
                Rule::in(['minutes', 'hours', 'days', 'weeks', 'months']),
                'required_if:trigger_type,interval',
            ],
            'timezone' => ['required', 'string', 'max:100'],
            'next_run_at' => ['nullable', 'date'],
            'context' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
