<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>kayıt</title>
</head>
<body>
    <div style="display: block; width: 200px;">
        <form
    method="POST"
    action="{{ route('customer.register.store') }}"
>
    @csrf

    <label for="first_name">
        Ad
    </label>

    <input
        id="first_name"
        type="text"
        name="first_name"
        value="{{ old('first_name') }}"
        required
        autocomplete="given-name"
    >

    @error('first_name')
        <span>{{ $message }}</span>
    @enderror


    <label for="last_name">
        Soyad
    </label>

    <input
        id="last_name"
        type="text"
        name="last_name"
        value="{{ old('last_name') }}"
        required
        autocomplete="family-name"
    >

    @error('last_name')
        <span>{{ $message }}</span>
    @enderror


    <label for="phone">
        Telefon
    </label>

    <input
        id="phone"
        type="tel"
        name="phone"
        value="{{ old('phone') }}"
        required
        autocomplete="tel"
    >

    @error('phone')
        <span>{{ $message }}</span>
    @enderror


    <label for="email">
        E-posta
    </label>

    <input
        id="email"
        type="email"
        name="email"
        value="{{ old('email') }}"
        autocomplete="email"
    >

    @error('email')
        <span>{{ $message }}</span>
    @enderror


    <label for="password">
        Şifre
    </label>

    <input
        id="password"
        type="password"
        name="password"
        required
        autocomplete="new-password"
    >


    <label for="password_confirmation">
        Şifre Tekrar
    </label>

    <input
        id="password_confirmation"
        type="password"
        name="password_confirmation"
        required
        autocomplete="new-password"
    >

    @error('password')
        <span>{{ $message }}</span>
    @enderror


    <button type="submit">
        Kayıt Ol
    </button>
</form>
    </div>
</body>
</html>