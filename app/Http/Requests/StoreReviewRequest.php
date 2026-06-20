<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,order_id'],
            'sku' => ['required', 'string', 'exists:product_details,sku'],
            'product_id' => ['required', 'integer', 'exists:products,product_id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.between' => 'Rating must be from 1 to 5 stars.',
            'content.min' => 'Please describe your experience with at least 10 characters.',
            'images.max' => 'You can upload up to 5 review images.',
            'images.*.image' => 'Review attachments must be image files.',
            'images.*.mimes' => 'Review images must be JPG, PNG, JPEG or WEBP.',
            'images.*.max' => 'Each review image must be 5 MB or smaller.',
        ];
    }
}
