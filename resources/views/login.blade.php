<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f6f8;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .wrap {
            max-width: 420px;
            margin: 60px auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }
        h1 {
            margin: 0 0 16px;
            font-size: 22px;
        }
        label {
            display: block;
            font-weight: 600;
            margin: 12px 0 6px;
        }
        input, select, button {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 12px;
        }
        .row input {
            width: auto;
        }
        .error {
            color: #b91c1c;
            font-size: 13px;
            margin-top: 8px;
        }
        .hint {
            color: #6b7280;
            font-size: 12px;
            margin-top: 8px;
        }
        button {
            background: #111827;
            color: #ffffff;
            border: none;
            margin-top: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #0b1220;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Login</h1>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="post" action="/login">
            @csrf

            <label for="login">Email or Username</label>
            <input id="login" name="login" value="{{ old('login') }}" autocomplete="username" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <div class="row">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <label for="remember">Remember me</label>
            </div>

            <button type="submit">Sign in</button>
        </form>

        <p class="hint">Sign in with a user from the users table.</p>
    </div>
</body>
</html>
