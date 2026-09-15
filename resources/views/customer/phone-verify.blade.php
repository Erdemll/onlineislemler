<h1>Telefonunuzu Doğrulayın</h1>

<p>
    Telefonunuza gönderilen
    6 haneli doğrulama kodunu giriniz.
</p>

<form
    method="POST"
    action="{{ route('customer.phone.verify.store') }}"
>
    @csrf

    <input
        type="text"
        name="code"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="6"
        pattern="[0-9]{6}"
        required
    >

    @error('code')
        <div>
            {{ $message }}
        </div>
    @enderror

    <button type="submit">
        Doğrula
    </button>
</form>

<form
    method="POST"
    action="{{ route('customer.phone.resend') }}"
>
    @csrf

    <button type="submit">
        Kodu Tekrar Gönder
    </button>
</form>