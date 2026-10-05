<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in - {{ config('branding.name') }}</title>
    <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/admin-tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/admin.css') }}">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="brand">
            @include('components.brand-logo', ['height' => 28])
        </div>
        <h1 style="margin-top: var(--adm-space-4);">Dashboard sign-in</h1>
        <p class="muted small">
            Administrator accounts only. Vendors, drivers and volunteers
            sign in at the <a href="{{ route('login') }}">member sign-in</a>.
        </p>

        @if (session('status'))
            <p class="alert" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <p class="alert error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="post" action="{{ route('admin.login.attempt') }}" novalidate>
            @csrf

            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" required
                       value="{{ old('email') }}" autocomplete="username" autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password">
            </div>

            <label class="checkbox" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                <span class="small">Keep me signed in on this device</span>
            </label>

            <button class="button" type="submit" style="width: 100%; margin-top: var(--adm-space-4);">
                Sign in
            </button>
        </form>
        <p><a class="button secondary" href="{{ route('admin.recovery.show') }}">Forgot password?</a></p>
    </main>
</body>
</html>