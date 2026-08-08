<p>You have been invited to the workspace <strong>{{ $invitation->workspace->name }}</strong>.</p>

<p><a href="{{ $acceptUrl }}">Accept invitation</a></p>

<p>This link expires on {{ $invitation->expires_at->format('Y-m-d H:i') }}.</p>
