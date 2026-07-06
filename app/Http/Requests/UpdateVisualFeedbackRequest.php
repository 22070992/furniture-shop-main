<?php

namespace App\Http\Requests;

use App\Models\VisualFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisualFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $feedback = $this->route('feedback');

        return $feedback instanceof VisualFeedback && $this->user()?->can('update', $feedback);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(VisualFeedback::STATUSES)],
            'type' => ['nullable', Rule::in(VisualFeedback::TYPES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,user_id'],
            'admin_note' => ['nullable', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', 'exists:visual_feedbacks,id'],
        ];
    }
}
