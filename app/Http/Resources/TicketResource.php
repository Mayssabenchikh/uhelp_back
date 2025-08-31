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
            // ticket_id au format TK-XXX (tu peux adapter padding si besoin)
            'ticket_id' => 'TK-' . str_pad($this->id, 3, '0', STR_PAD_LEFT),
            // customer attendu par le front
            'customer' => [
                'id' => $this->client?->id,
                'name' => $this->client?->name ?? ($this->client->email ?? 'Unknown'),
            ],
            'subject' => $this->titre,
            'description' => $this->description,
            'status' => $this->statut,
            'assigned_agent' => $this->agent ? ['id' => $this->agent->id, 'name' => $this->agent->name] : null,
            'priority' => $this->priorite,
            'created_at' => $this->created_at?->toISOString(),
            // useful for details in front
            'raw' => [
                'client_id' => $this->client_id,
                'agentassigne_id' => $this->agentassigne_id,
                'subscription_id' => $this->subscription_id,
            ],
        ];
    }
}
