<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class VerifyContractSigningRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_uuid' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'Doğrulama kodunu giriniz.',
            'code.digits' => 'Doğrulama kodu altı haneli olmalıdır.',
        ];
    }
}
