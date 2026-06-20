<?php

namespace App\Http\Requests;

use App\Models\VisualFeedback;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisualFeedbackCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $feedback = $this->route('feedback');

        return $feedback instanceof VisualFeedback && $this->user()?->can('comment', $feedback);
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:10000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf,txt,doc,docx',
            ],
        ];
    }
}
