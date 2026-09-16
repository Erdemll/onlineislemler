<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Başlangıç İnternet',
                'description' => 'Temel kullanım için yalın, güvenilir internet hizmeti.',
                'price' => 649.90,
            ],
            [
                'name' => 'Hızlı İnternet',
                'description' => 'Evden çalışma ve yüksek çözünürlüklü yayın için güçlü paket.',
                'price' => 899.90,
            ],
            [
                'name' => 'Statik IP',
                'description' => 'Uzaktan erişim ve sunucu bağlantıları için sabit IP hizmeti.',
                'price' => 189.90,
            ],
            [
                'name' => 'Kurulum Desteği',
                'description' => 'Adresinizde tek seferlik cihaz kurulumu ve bağlantı kontrolü.',
                'price' => 750.00,
            ],
        ];

        foreach ($services as $service) {
            Service::query()->updateOrCreate(
                ['name' => $service['name']],
                [...$service, 'tax_rate' => 20, 'is_active' => true],
            );
        }
    }
}
