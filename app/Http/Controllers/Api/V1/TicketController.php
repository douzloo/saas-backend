<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\Interfaces\TicketServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(
        protected TicketServiceInterface $ticketService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->isStaff()
            ? Ticket::query()
            : $request->user()->tickets();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                    ->orWhere('ticket_number', 'like', "%{$request->search}%");
            });
        }

        $tickets = $query->with(['user', 'assignee', 'product'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): TicketResource
    {
        $data = $request->only(['subject', 'product_id', 'priority', 'category', 'department']);
        $data['message'] = $request->message;

        if ($request->has('license_key')) {
            $license = $request->user()->licenses()
                ->where('key', $request->license_key)
                ->first();
            if ($license) {
                $data['license_id'] = $license->id;
            }
        }

        $ticket = $this->ticketService->create($data, $request->user());

        return new TicketResource($ticket->load(['messages.user', 'messages.attachments', 'user']));
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        if (! $request->user()->isStaff() && $ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $ticket->load(['user', 'assignee', 'product', 'license', 'messages.user', 'customFields']);

        return new TicketResource($ticket);
    }

    public function reply(Request $request, Ticket $ticket): TicketResource
    {
        if (! $request->user()->isStaff() && $ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'body' => 'required|string',
            'is_internal_note' => 'boolean',
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $this->ticketService->addMessage(
            $ticket,
            $request->body,
            $request->user(),
            $request->boolean('is_internal_note', false),
            $request->file('attachments', [])
        );

        return new TicketResource($ticket->fresh()->load(['messages.user', 'messages.attachments']));
    }

    public function assign(Request $request, Ticket $ticket): TicketResource
    {
        if (! $request->user()->isStaff()) {
            abort(403);
        }

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = $this->ticketService->assign($ticket, $request->assigned_to);

        return new TicketResource($ticket);
    }

    public function updateStatus(Request $request, Ticket $ticket): TicketResource
    {
        if (! $request->user()->isStaff() && $ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:open,in_progress,waiting_reply,resolved,closed',
        ]);

        $ticket = $this->ticketService->changeStatus($ticket, $request->status);

        return new TicketResource($ticket->load('assignee', 'product', 'user'));
    }

    public function messages(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        if (! $request->user()->isStaff() && $ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $messages = $ticket->messages()
            ->with('user', 'attachments')
            ->orderByDesc('created_at')
            ->get();

        return TicketMessageResource::collection($messages);
    }

    public function downloadAttachment(Request $request, TicketAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        $ticket = $attachment->message()->with('ticket')->first()->ticket;

        if (! $request->user()->isStaff() && $ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $path = $attachment->path;

        if (! $path || ! Storage::disk($attachment->disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->ticketService->getStats());
    }
}
