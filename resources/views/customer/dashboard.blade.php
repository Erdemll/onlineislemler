<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online İşlemler</title>
</head>
<body>
    {{ auth('customer')->user()->first_name}}
    <form
    method="POST"
    action="{{ route('customer.logout') }}"
>
    @csrf

    <button type="submit">
        Çıkış Yap
    </button>
</form>
</body>
</html>