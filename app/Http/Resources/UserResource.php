<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array for the front.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'role'              => $this->role,
            'phone_number'      => $this->phone_number,
            'profile_photo'     => $this->profile_photo,
            'profile_photo_url' => $this->profile_photo
                ? asset('storage/' . $this->profile_photo)
                : null,
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id'   => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),
            'status'   => $this->email_verified_at ? 'Active' : 'Inactive',
            'location' => $this->location ?? 'Not specified',

            // IMPORTANT : whenCounted attend le nom de la relation,
            // et utilise automatiquement <relation>_count
            'created_tickets'  => $this->whenCounted('createdTickets'),
            'resolved_tickets' => $this->whenCounted('resolvedTickets'),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
