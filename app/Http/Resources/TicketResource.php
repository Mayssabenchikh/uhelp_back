<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array for the front.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            // ticket_id au format TK-XXX
            'ticket_id' => 'TK-' . str_pad($this->id, 3, '0', STR_PAD_LEFT),
            // customer attendu par le front (on expose le client ici)
            'customer' => [
                'id' => $this->client?->id,
                'name' => $this->client?->name ?? ($this->client?->email ?? 'Unknown'),
            ],
            'subject' => $this->titre,
            'description' => $this->description,
            'status' => $this->statut,
            'assigned_agent' => $this->agent ? ['id' => $this->agent->id, 'name' => $this->agent->name] : null,
            'priority' => $this->priorite,
            'category' => $this->category ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),

            // raw utile au front
            'raw' => [
                'client_id' => $this->client_id,
                'agentassigne_id' => $this->agentassigne_id,
                'subscription_id' => $this->subscription_id,
                'category' => $this->category ?? null,
            ],
        ];
    }
}
