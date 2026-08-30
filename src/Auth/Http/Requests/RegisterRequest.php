<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PrimeVueKit\Auth\UserModel;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(UserModel::class())],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
