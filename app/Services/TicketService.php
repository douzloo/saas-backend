<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Interfaces\TicketServiceInterface;
use Illuminate\Support\Str;

class TicketService implements TicketServiceInterface
{
    public function create(array $data, User $user): Ticket
    {
        $ticket = Ticket::create(array_merge($data, [
            'user_id' => $user->id,
            'ticket_number' => 'TK-'.strtoupper(Str::random(8)),
            'status' => $data['status'] ?? 'open',
        ]));

        if (! empty($data['message'])) {
            $this->addMessage($ticket, $data['message'], $user);
        }

        return $ticket->load('user', 'assignee', 'product', 'license');
    }

    public function addMessage(Ticket $ticket, string $body, User $user, bool $isInternal = false, array $attachments = []): void
    {
        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'is_staff_reply' => $user->isStaff(),
            'is_internal_note' => $isInternal,
        ]);

        foreach ($attachments as $file) {
            $originalFilename = $file->getClientOriginalName();
            $path = $file->store('ticket-attachments', 'public');

            if ($path === false) {
                continue;
            }

            TicketAttachment::create([
                'ticket_message_id' => $message->id,
                'filename' => basename($path),
                'original_filename' => $originalFilename,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'disk' => 'public',
                'path' => $path,
            ]);
        }

        if ($ticket->status === 'waiting_reply' && $user->isStaff()) {
            $ticket->update(['status' => 'in_progress']);
        } elseif ($ticket->status === 'in_progress' && ! $user->isStaff()) {
            $ticket->update(['status' => 'waiting_reply']);
        }
    }

    public function assign(Ticket $ticket, int $userId): Ticket
    {
        $ticket->update([
            'assigned_to' => $userId,
            'status' => 'in_progress',
        ]);

        $ticket->messages()->create([
            'user_id' => $userId,
            'body' => 'تیکت به '.User::find($userId)->name.' اختصاص داده شد.',
            'is_staff_reply' => true,
            'is_internal_note' => true,
        ]);

        return $ticket->fresh('assignee');
    }

    public function changeStatus(Ticket $ticket, string $status): Ticket
    {
        $oldStatus = $ticket->status;
        $ticket->update(['status' => $status]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'body' => "وضعیت از \"{$oldStatus}\" به \"{$status}\" تغییر کرد.",
            'is_staff_reply' => true,
            'is_internal_note' => true,
        ]);

        return $ticket->fresh();
    }

    public function changePriority(Ticket $ticket, string $priority): Ticket
    {
        $ticket->update(['priority' => $priority]);

        return $ticket->fresh();
    }

    public function close(Ticket $ticket): Ticket
    {
        return $this->changeStatus($ticket, 'closed');
    }

    public function getStats(): array
    {
        return [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'waiting_reply' => Ticket::where('status', 'waiting_reply')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'by_priority' => Ticket::select('priority', \DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'by_category' => Ticket::select('category', \DB::raw('count(*) as count'))
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'avg_resolution_hours' => Ticket::where('status', 'resolved')
                ->whereNotNull('updated_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours')
                ->value('avg_hours') ?? 0,
        ];
    }
}
