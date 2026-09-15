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
    action="{{ route('customer.login.store') }}"
>
    @csrf

    <label for="email">
        E-posta
    </label>

    <input
        id="email"
        type="email"
        name="email"
        value="{{ old('email') }}"
        required
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
        autocomplete="current-password"
    >

    @error('password')
        <span>{{ $message }}</span>
    @enderror


    <label>
        <input
            type="checkbox"
            name="remember"
            value="1"
        >

        Beni hatırla
    </label>


    <button type="submit">
        Giriş Yap
    </button>
</form>
</body>
</html>