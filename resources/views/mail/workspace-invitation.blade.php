<div style="background:#f7f7fb;padding:32px 16px;font-family:Arial,sans-serif;color:#202033">
    <div style="background:#ffffff;border:1px solid #e8e7f0;border-radius:18px;margin:0 auto;max-width:520px;padding:32px">
        <div style="background:#7454f5;border-radius:10px;color:#ffffff;font-size:16px;font-weight:bold;padding:12px 14px;width:max-content">LR</div>
        <h1 style="font-size:24px;line-height:1.25;margin:24px 0 12px">Вас пригласили в {{ $invitation->workspace->name }}</h1>
        <p style="color:#66657a;line-height:1.55;margin:0 0 24px">Перейдите по ссылке, чтобы создать аккаунт или присоединиться к рабочему пространству.</p>
        <a href="{{ $acceptUrl }}" style="background:#7454f5;border-radius:8px;color:#ffffff;display:inline-block;font-weight:bold;padding:13px 18px;text-decoration:none">Принять приглашение</a>
        <p style="color:#8b899c;font-size:13px;line-height:1.5;margin:28px 0 0">Ссылка действует до {{ $invitation->expires_at->format('d.m.Y H:i') }}.</p>
    </div>
</div>
