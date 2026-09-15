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
    action="{{ route('customer.password.otp.verify') }}"
>
    @csrf

    <label for="code">
        Doğrulama Kodu
    </label>

    <input
        id="code"
        type="text"
        name="code"
        maxlength="6"
        inputmode="numeric"
        autocomplete="one-time-code"
        required
    >

    @error('code')
        <div>{{ $message }}</div>
    @enderror

    <button type="submit">
        Doğrula
    </button>
</form>
</body>
</html>