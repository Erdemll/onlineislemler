@extends('layouts.customer')

@section('title', 'Sözleşmeler')

@section('content')
    <x-customer.page-placeholder
        number="04"
        title="Sözleşmeler"
        description="Mevcut sözleşmelerini görüntüleyecek ve bekleyen sözleşmeleri güvenli biçimde imzalayabileceksin."
        :items="[
            'Aktif ve geçmiş sözleşmeler',
            'Sözleşme PDF görüntüleme',
            'İmza bekleyen belgeler',
            'Web imzası ve imzalı PDF çıktısı',
        ]"
    />
@endsection
