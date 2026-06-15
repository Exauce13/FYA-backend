<?php

namespace App\Mail;

use App\Models\NotificationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public NotificationModel $notification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectForType($this->notification->type),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-notification',
            with: [
                'notification' => $this->notification,
                'title' => $this->subjectForType($this->notification->type),
                'body' => $this->bodyForNotification(),
            ],
        );
    }

    private function subjectForType(string $type): string
    {
        return match ($type) {
            'nouveau_message' => 'Nouveau message sur FYA',
            'nouvel_appel_offre' => 'Nouvel appel d offre disponible',
            'nouvelle_candidature' => 'Nouvelle candidature recue',
            'candidature_acceptee' => 'Votre candidature a ete acceptee',
            'candidature_refusee' => 'Votre candidature n a pas ete retenue',
            'post_like' => 'Votre post a recu un nouveau like',
            default => 'Nouvelle notification FYA',
        };
    }

    private function bodyForNotification(): string
    {
        $data = $this->notification->data_json ?? [];

        return match ($this->notification->type) {
            'nouveau_message' => sprintf(
                '%s vous a envoye un message.',
                $data['sender_name'] ?? 'Un utilisateur',
            ),
            'nouvel_appel_offre' => sprintf(
                'Un appel d offre correspond a votre metier: %s.',
                $data['titre'] ?? 'sans titre',
            ),
            'nouvelle_candidature' => sprintf(
                '%s a postule a votre appel d offre: %s.',
                $data['artisan_name'] ?? 'Un artisan',
                $data['titre'] ?? 'sans titre',
            ),
            'candidature_acceptee' => sprintf(
                'Votre candidature pour "%s" a ete acceptee.',
                $data['titre'] ?? 'cet appel d offre',
            ),
            'candidature_refusee' => sprintf(
                'Votre candidature pour "%s" n a pas ete retenue.',
                $data['titre'] ?? 'cet appel d offre',
            ),
            'post_like' => sprintf(
                '%s a aime votre post.',
                $data['liker_name'] ?? 'Un utilisateur',
            ),
            default => 'Vous avez une nouvelle notification.',
        };
    }
}
