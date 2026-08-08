<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join {{ $invitation->workspace->name }}</title>
</head>
<body>
    <main>
        <h1>Join {{ $invitation->workspace->name }}</h1>
        <p>You were invited as {{ $invitation->role }} using {{ $invitation->email }}.</p>

        @if ($hasAccount)
            <p>An account with this email already exists. Accept the invitation, then sign in.</p>
        @endif

        <form method="post" action="{{ route('workspace-invitations.accept', ['token' => $token]) }}">
            @csrf

            @if (! $hasAccount)
                <label>
                    Name
                    <input name="name" value="{{ old('name') }}" required autofocus>
                </label>
                @error('name') <p>{{ $message }}</p> @enderror

                <label>
                    Password
                    <input type="password" name="password" required>
                </label>
                @error('password') <p>{{ $message }}</p> @enderror

                <label>
                    Repeat password
                    <input type="password" name="password_confirmation" required>
                </label>
            @endif

            <button type="submit">Accept invitation</button>
        </form>
    </main>
</body>
</html>
