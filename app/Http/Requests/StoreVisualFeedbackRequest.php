<?php

namespace App\Http\Requests;

use App\Models\VisualFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisualFeedbackRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $jsonFields = [
            'element_attributes',
            'element_rect',
            'scroll_position',
            'viewport',
            'browser_info',
        ];

        $decoded = [];
        foreach ($jsonFields as $field) {
            $value = $this->input($field);
            if (is_string($value) && $value !== '') {
                $json = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decoded[$field] = $json;
                }
            }
        }

        if (is_string($this->input('element_classes'))) {
            $classes = json_decode($this->input('element_classes'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($classes)) {
                $decoded['element_classes'] = implode(' ', array_slice($classes, 0, 40));
            }
        }

        $this->merge($decoded);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', VisualFeedback::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(VisualFeedback::TYPES)],
            'priority' => ['required', Rule::in(VisualFeedback::PRIORITIES)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'expected_result' => ['nullable', 'string', 'max:10000'],
            'page_url' => ['required', 'url', 'max:2048'],
            'page_path' => ['nullable', 'string', 'max:512'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'element_tag' => ['nullable', 'string', 'max:80'],
            'element_text' => ['nullable', 'string', 'max:10000'],
            'element_id' => ['nullable', 'string', 'max:255'],
            'element_classes' => ['nullable', 'string', 'max:2000'],
            'css_selector' => ['required', 'string', 'max:2048'],
            'xpath' => ['nullable', 'string', 'max:10000'],
            'element_attributes' => ['nullable', 'array'],
            'element_rect' => ['nullable', 'array'],
            'scroll_position' => ['nullable', 'array'],
            'viewport' => ['nullable', 'array'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'browser_info' => ['nullable', 'array'],
            'screenshot_data' => ['nullable', 'string'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf,txt,doc,docx',
            ],
        ];
    }
}
