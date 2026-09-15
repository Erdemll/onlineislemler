<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form
    method="POST"
    action="{{ route('customer.password.update') }}"
>
    @csrf

    <label for="password">
        Yeni Şifre
    </label>

    <input
        id="password"
        type="password"
        name="password"
        autocomplete="new-password"
        required
    >

    <label for="password_confirmation">
        Yeni Şifre Tekrar
    </label>

    <input
        id="password_confirmation"
        type="password"
        name="password_confirmation"
        autocomplete="new-password"
        required
    >

    @error('password')
        <div>{{ $message }}</div>
    @enderror

    <button type="submit">
        Şifreyi Değiştir
    </button>
</form>
</body>
</html>