<?php

namespace App\Http\Requests\Customer;

use App\Enums\SupportTicketCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(SupportTicketCategory::class)],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => trim((string) $this->input('subject')),
            'message' => trim((string) $this->input('message')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.required' => 'Destek konusunu seçin.',
            'category.enum' => 'Geçerli bir destek konusu seçin.',
            'subject.required' => 'Talep başlığını yazın.',
            'subject.min' => 'Talep başlığı en az 3 karakter olmalıdır.',
            'message.required' => 'Talebinizi açıklayın.',
            'message.min' => 'Açıklama en az 10 karakter olmalıdır.',
            'message.max' => 'Açıklama en fazla 5000 karakter olabilir.',
        ];
    }
}
