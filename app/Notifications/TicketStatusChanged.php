<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Ticket;

class TicketStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;
    protected $oldStatus;

    public function __construct(Ticket $ticket, $oldStatus)
    {
        $this->ticket = $ticket;
        $this->oldStatus = $oldStatus;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Statut du ticket #{$this->ticket->id} changé")
            ->line("Le statut du ticket '{$this->ticket->titre}' a changé de '{$this->oldStatus}' à '{$this->ticket->statut}'.")
            ->action('Voir le ticket', url("/tickets/{$this->ticket->id}"))
            ->line('Merci.');
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket->id,
            'titre' => $this->ticket->titre,
            'old_status' => $this->oldStatus,
            'new_status' => $this->ticket->statut,
        ];
    }
}
