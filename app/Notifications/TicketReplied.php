<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketReplied extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public TicketResponse $response
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->response->author?->name ?? 'Someone';

        // Lien frontend si tu as APP_FRONTEND_URL=. Sinon fallback sur URL backend.
        $url = config('app.frontend_url')
            ? rtrim(config('app.frontend_url'), '/')."/tickets/{$this->ticket->id}"
            : url("/tickets/{$this->ticket->id}");

        return (new MailMessage)
            ->subject("New reply on Ticket #{$this->ticket->id} — {$this->ticket->titre}")
            ->greeting("Hi {$notifiable->name},")
            ->line("$sender replied to the ticket:")
            ->line('"'.Str::limit($this->response->message, 300).'"')
            ->action('View ticket', $url)
            ->line('This is an automated notification.');
    }
}
