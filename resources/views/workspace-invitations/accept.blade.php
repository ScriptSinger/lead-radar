<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Присоединиться к {{ $invitation->workspace->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            align-items: center;
            background: #f7f7fb;
            color: #202033;
            display: flex;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }
        .card {
            background: #fff;
            border: 1px solid #e8e7f0;
            border-radius: 20px;
            box-shadow: 0 18px 45px rgba(42, 29, 92, .08);
            max-width: 460px;
            padding: 40px;
            width: 100%;
        }
        .logo {
            align-items: center;
            background: #7454f5;
            border-radius: 12px;
            color: #fff;
            display: flex;
            font-size: 20px;
            font-weight: 800;
            height: 44px;
            justify-content: center;
            margin-bottom: 28px;
            width: 44px;
        }
        h1 { font-size: 28px; letter-spacing: -.03em; line-height: 1.15; margin: 0 0 14px; }
        p { color: #66657a; line-height: 1.55; margin: 0 0 26px; }
        label { display: block; font-size: 14px; font-weight: 650; margin: 18px 0 7px; }
        input {
            border: 1px solid #d8d6e5;
            border-radius: 9px;
            font: inherit;
            outline-color: #7454f5;
            padding: 12px 13px;
            width: 100%;
        }
        button {
            background: #7454f5;
            border: 0;
            border-radius: 9px;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            margin-top: 28px;
            padding: 13px 18px;
            width: 100%;
        }
        button:hover { background: #6242e8; }
        .error { color: #c72d47; font-size: 13px; margin: 6px 0 0; }
        .note { font-size: 13px; margin: 0; }
        @media (max-width: 480px) { .card { padding: 28px 22px; } }
    </style>
</head>
<body>
    <main class="card">
        <div class="logo">LR</div>
        <h1>Добро пожаловать в {{ $invitation->workspace->name }}</h1>
        <p>Вы приглашены в рабочее пространство как <strong>{{ $invitation->role }}</strong>. Вход будет привязан к адресу {{ $invitation->email }}.</p>

        @if ($hasAccount)
            <p class="note">Аккаунт с этим адресом уже существует. Примите приглашение, затем войдите в систему.</p>
        @endif

        <form method="post" action="{{ route('workspace-invitations.accept', ['token' => $token]) }}">
            @csrf

            @if (! $hasAccount)
                <label>
                    Имя
                    <input name="name" value="{{ old('name') }}" required autofocus>
                </label>
                @error('name') <p class="error">{{ $message }}</p> @enderror

                <label>
                    Пароль
                    <input type="password" name="password" minlength="12" required>
                </label>
                @error('password') <p class="error">{{ $message }}</p> @enderror

                <label>
                    Повторите пароль
                    <input type="password" name="password_confirmation" minlength="12" required>
                </label>
            @endif

            <button type="submit">Принять приглашение</button>
        </form>
    </main>
</body>
</html>
