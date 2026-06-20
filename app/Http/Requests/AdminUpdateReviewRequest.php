<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->is_staff;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Review::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
