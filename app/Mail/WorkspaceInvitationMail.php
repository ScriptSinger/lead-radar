<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Invitation to %s', $this->invitation->workspace->name),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.workspace-invitation',
            with: [
                'acceptUrl' => route('workspace-invitations.show', ['token' => $this->token]),
            ],
        );
    }
}
