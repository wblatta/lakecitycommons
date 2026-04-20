<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => 'required|string|max:255',
            'description'         => 'required|string|max:2000',
            'category_id'         => 'required|exists:categories,id',
            'condition'           => 'required|in:excellent,good,fair,poor',
            'offer_type'          => 'required|in:gift,lend',
            'credit_type'         => 'required_if:offer_type,lend|nullable|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
            'photos'              => 'nullable|array|max:5',
            'photos.*'            => 'image|mimes:jpeg,png,webp|max:5120',
        ];
    }
}
