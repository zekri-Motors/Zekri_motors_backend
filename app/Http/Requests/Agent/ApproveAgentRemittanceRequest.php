<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAgentRemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agent_transactions.approve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
