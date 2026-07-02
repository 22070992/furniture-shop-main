<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProduct extends FormRequest
{

    public function authorize(): bool
    {
        // check permission here
        return true;
    }
    public function rules()
    {
        // validate here
        return [
            'title' => 'required',
            'category' => 'required|exists:categories,category_id',
            'product_type' => 'nullable|exists:product_types,product_type_id',
            'brand' => 'required|exists:brands,brand_id',
            'description' => 'required',
            'short_description' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
        ];
    }
    public function messages()
    {
        // response message here
        return [
            'title.required' => 'The title field is required.',
            'category.required' => 'The category field is required.',
            'category.exists' => 'The category doesnt exist.',
            'product_type.exists' => 'The product type doesnt exist.',
            'brand.required' => 'The brand field is required.',
            'brand.exists' => 'The brand doesnt exist.',
            'description.required' => 'The description field is required.',
        ];
    }
}
