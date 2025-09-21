<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreTicketResponseRequest;
use App\Http\Requests\UpdateTicketResponseRequest;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Notifications\TicketReplied;
use App\Notifications\TicketStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TicketResponseController extends Controller
{
    // Vérifie que l'utilisateur peut voir / participer au ticket
    protected function ensureCanAccessTicket(Ticket $ticket): void
    {
        $u = Auth::user(); // ✅
        abort_unless(
            $u && (
                $u->role === 'admin'
                || $u->id === (int) $ticket->client_id
                || $u->id === (int) $ticket->agentassigne_id
            ),
            403,
            'Unauthorized'
        );
    }

    // GET /api/tickets/{ticket}/responses
    public function index(Ticket $ticket)
    {
        $user = Auth::user();

        // Vérifie que l'utilisateur appartient au département de l'agent assigné
        if ($user->role === 'agent' && $user->department_id !== optional($ticket->agent)->department_id) {
            abort(403, 'Unauthorized: ticket not in your department');
        }

        return $ticket->responses()
                      ->with('author:id,name,email')
                      ->latest()
                      ->paginate(20);
    }


    /**
     * POST /api/tickets/{ticket}/responses
     *
     * @param \App\Http\Requests\StoreTicketResponseRequest|\Illuminate\Http\Request $request
     * @param \App\Models\Ticket $ticket
     */
    public function store(StoreTicketResponseRequest $request, Ticket $ticket)
    {
        // Vérifie l'accès au ticket (admin, client propriétaire, ou agent assigné)
        $this->ensureCanAccessTicket($ticket);

        $data = $request->validated();
        $user = Auth::user();

        // On met tout dans une transaction pour garantir l'atomicité
        DB::beginTransaction();

        try {
            // Handle file upload if present
            $attachmentData = [];
            if ($request->hasFile('attachment') && $request->file('attachment')) {
                $file = $request->file('attachment');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $mimeType = $file->getMimeType();
                $size = $file->getSize();
                
                // Generate unique filename
                $filename = uniqid() . '_' . time() . '.' . $extension;
                
                // Store file in storage/app/public/attachments/ticket-responses
                $path = $file->storeAs('attachments/ticket-responses', $filename, 'public');
                
                $attachmentData = [
                    'attachment_path' => $path,
                    'attachment_name' => $originalName,
                    'attachment_type' => $mimeType,
                    'attachment_size' => $size,
                ];
            }

            // Créer la réponse
            $response = $ticket->responses()->create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user->id,
                'message'   => $data['message'],
                ...$attachmentData, // Spread attachment data if present
            ]);

            // Déterminer le nouveau statut selon la logique métier
            // LOGIQUE APPLIQUÉE (modifiable) :
            // - si agent ou admin répond => 'in_progress' (l'agent travaille / a répondu)
            // - si client répond => 'open' (ticket rouvert / attente agent)
            $oldStatus = $ticket->statut;
            $newStatus = $oldStatus;

            if (in_array($user->role, ['agent', 'admin'])) {
                $newStatus = 'in_progress';
            } else {
                // role 'client' or other non-agent => reopen
                $newStatus = 'open';
            }

            // Mettre à jour le ticket seulement si différent
            if ($oldStatus !== $newStatus) {
                $ticket->statut = $newStatus;
                $ticket->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // Log: à adapter selon ton logger
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create response: ' . $e->getMessage()
            ], 500);
        }

        // Charger relations pour retour
        $response->load('author:id,name,email');
        $ticket->refresh()->load(['client:id,name,email', 'agent:id,name,email']);

        // Préparer destinataires de la notification "TicketReplied"
        $recipients = collect([$ticket->client, $ticket->agent])
            ->filter()
            ->reject(fn ($recipient) => $recipient->id === $user->id)
            ->unique('id')
            ->all();

        if (!empty($recipients)) {
            Notification::send($recipients, new TicketReplied($ticket, $response));
        }

        // Si le statut a changé, notifier le client et l'agent du changement de statut
        if ($oldStatus !== $ticket->statut) {
            $statusRecipients = collect([$ticket->client, $ticket->agent])
                ->filter()
                ->reject(fn ($recipient) => $recipient->id === $user->id)
                ->unique('id')
                ->all();

            if (!empty($statusRecipients)) {
                Notification::send($statusRecipients, new TicketStatusChanged($ticket, $oldStatus));
            }
        }

        return response()->json([
            'status'   => true,
            'message'  => 'Response created and ticket status updated',
            'response' => $response,
            'ticket'   => $ticket,
        ], 201);
    }


    // GET /api/responses/{response}
    public function show(TicketResponse $response)
    {
        $this->ensureCanAccessTicket($response->ticket);
        return $response->load('author:id,name,email');
    }

    /**
     * PATCH /api/responses/{response}
     *
     * @param \App\Http\Requests\UpdateTicketResponseRequest|\Illuminate\Http\Request $request
     * @param \App\Models\TicketResponse $response
     */
    public function update(UpdateTicketResponseRequest $request, TicketResponse $response)
    {
        // Verify user can update this response
        $user = Auth::user();
        abort_unless($user && ($user->role === 'admin' || $user->id === (int) $response->user_id), 403);

        $data = $request->validated();
        
        // Handle new attachment upload
        if ($request->hasFile('attachment') && $request->file('attachment')) {
            // Delete old attachment if exists
            if ($response->hasAttachment() && Storage::disk('public')->exists($response->attachment_path)) {
                Storage::disk('public')->delete($response->attachment_path);
            }
            
            $file = $request->file('attachment');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();
            
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Store file in storage/app/public/attachments/ticket-responses
            $path = $file->storeAs('attachments/ticket-responses', $filename, 'public');
            
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $originalName;
            $data['attachment_type'] = $mimeType;
            $data['attachment_size'] = $size;
        }

        $response->update($data);
        return $response->load('author:id,name,email');
    }

    // DELETE /api/responses/{response}
    public function destroy(Request $request, TicketResponse $response)
    {
        $user = Auth::user(); // ✅
        abort_unless($user && ($user->role === 'admin' || $user->id === (int) $response->user_id), 403);

        // Delete attachment file if exists
        if ($response->hasAttachment() && Storage::disk('public')->exists($response->attachment_path)) {
            Storage::disk('public')->delete($response->attachment_path);
        }

        $response->delete();
        return response()->json(['deleted' => true]);
    }

    // GET /api/responses/{response}/download-attachment
    public function downloadAttachment(TicketResponse $response)
    {
        // Verify access to the ticket
        $this->ensureCanAccessTicket($response->ticket);
        
        // Check if response has attachment
        if (!$response->hasAttachment()) {
            abort(404, 'No attachment found');
        }

        // Check if file exists
        if (!Storage::disk('public')->exists($response->attachment_path)) {
            abort(404, 'Attachment file not found');
        }

        return Storage::disk('public')->download(
            $response->attachment_path,
            $response->attachment_name
        );
    }

}
