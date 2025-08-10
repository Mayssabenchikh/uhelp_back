<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Ticket;

class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;
    protected $previousAgentId;

    public function __construct(Ticket $ticket, $previousAgentId = null)
    {
        $this->ticket = $ticket;
        $this->previousAgentId = $previousAgentId;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Nouveau ticket assigné: {$this->ticket->titre}")
            ->line("Vous avez été assigné au ticket #{$this->ticket->id}: {$this->ticket->titre}.")
            ->action('Voir le ticket', url("/tickets/{$this->ticket->id}"))
            ->line('Merci.');
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket->id,
            'titre' => $this->ticket->titre,
            'previous_agent_id' => $this->previousAgentId,
        ];
    }
}
