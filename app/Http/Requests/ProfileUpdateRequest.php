<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'bio'               => ['nullable', 'string', 'max:500'],
            'neighborhood_area' => ['nullable', 'string', 'max:100'],
            'cross_streets'     => ['nullable', 'string', 'max:100'],
            'avatar'            => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:3072'],
        ];
    }
}
