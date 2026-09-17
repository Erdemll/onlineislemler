<?php

namespace App\Http\Requests\Admin;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContractVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contract_mode' => ['required', Rule::in(['existing', 'new'])],
            'contract_id' => [
                'nullable',
                'integer',
                'required_if:contract_mode,existing',
                'prohibited_if:contract_mode,new',
                Rule::exists('contracts', 'id')->where('is_active', true),
            ],
            'new_contract_name' => [
                'nullable',
                'string',
                'max:255',
                'required_if:contract_mode,new',
                'prohibited_if:contract_mode,existing',
            ],
            'version' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,29}$/'],
            'effective_at' => ['required', 'date'],
            'document' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:20480'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNotNull('cari_plus_product_id')),
            ],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['contract_mode', 'contract_id', 'new_contract_name', 'version'])) {
                    return;
                }

                if ($this->filled('contract_id')) {
                    $exists = Contract::query()
                        ->whereKey($this->integer('contract_id'))
                        ->whereHas('versions', fn ($query) => $query->where('version', $this->string('version')->toString()))
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add('version', 'Bu sözleşme sürümü daha önce yayımlanmış.');
                    }

                    return;
                }

                $slug = str($this->string('new_contract_name')->toString())->slug()->toString();

                if ($slug !== '' && Contract::query()->where('slug', $slug)->exists()) {
                    $validator->errors()->add('new_contract_name', 'Bu sözleşme zaten mevcut. Mevcut sözleşmelerden seçin.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contract_mode.required' => 'Sözleşme türünü seçin.',
            'contract_id.required_if' => 'Mevcut bir sözleşme seçin.',
            'new_contract_name.required_if' => 'Yeni sözleşmenin adını girin.',
            'version.regex' => 'Sürüm yalnızca harf, rakam, nokta, tire ve alt çizgi içerebilir.',
            'document.mimes' => 'Yalnızca geçerli bir PDF dosyası yükleyebilirsiniz.',
            'document.extensions' => 'Dosya uzantısı PDF olmalıdır.',
            'document.max' => 'PDF dosyası en fazla 20 MB olabilir.',
            'service_ids.required' => 'En az bir ürün seçmelisiniz.',
            'service_ids.min' => 'En az bir ürün seçmelisiniz.',
            'service_ids.*.exists' => 'Seçilen ürün aktif değil veya Cari Plus eşleşmesi bulunmuyor.',
        ];
    }
}
