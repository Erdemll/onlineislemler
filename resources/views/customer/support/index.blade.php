@extends('layouts.customer')

@section('title', 'Destek')

@section('content')
    <x-customer.page-placeholder
        number="03"
        title="Destek"
        description="Destek taleplerini oluşturacağın ve devam eden görüşmeleri takip edeceğin alan."
        :items="[
            'Yeni destek talebi oluşturma',
            'Açık ve sonuçlanan talepler',
            'Talep ayrıntıları ve yanıt geçmişi',
            'Dosya ve belge ekleri',
        ]"
    />
@endsection
