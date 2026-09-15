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
    action="{{ route('customer.password.send') }}"
>
    @csrf

    <label for="phone">
        Telefon Numaranız
    </label>

    <input
        id="phone"
        type="tel"
        name="phone"
        value="{{ old('phone') }}"
        autocomplete="tel"
        required
    >

    @error('phone')
        <div>{{ $message }}</div>
    @enderror

    <button type="submit">
        Doğrulama Kodu Gönder
    </button>
</form>
</body>
</html>