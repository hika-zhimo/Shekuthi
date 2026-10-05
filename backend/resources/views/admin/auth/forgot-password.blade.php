<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Reset admin password - {{ config('branding.name') }}</title>
    <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/admin-tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/admin.css') }}">
</head>
<body class="login-page">
<main class="login-card">
    @include('components.brand-logo', ['height' => 28])
    <h1>Reset admin password</h1>
    <p class="muted">Use your registered admin email. Your password changes only after you confirm the emailed code.</p>
    @if (session('status'))
        <p class="alert" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="alert error" role="alert">{{ $errors->first() }}</p>
    @endif
    @if (session('admin_recovery_requested'))
        <form method="post" action="{{ route('admin.recovery.confirm') }}">
            @csrf
            <div class="field">
                <label for="otp">Email verification code</label>
                <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
            </div>
            <button class="button" type="submit">Confirm password reset</button>
        </form>
    @endif
    <form method="post" action="{{ route('admin.recovery.request') }}">
        @csrf
        <div class="field">
            <label for="email">Admin email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
        </div>
        <div class="field">
            <label for="password">New password (at least 12 characters)</label>
            <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
        </div>
        <button class="button" type="submit">Send verification code</button>
    </form>
    <p><a class="button secondary" href="{{ route('admin.login') }}">Back to sign-in</a></p>
</main>
</body>
</html>
