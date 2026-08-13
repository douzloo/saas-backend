<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeadNoteResource;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\Interfaces\LeadServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadNoteController extends Controller
{
    public function __construct(
        protected LeadServiceInterface $leadService,
    ) {}

    public function index(Lead $lead): AnonymousResourceCollection
    {
        $notes = $lead->notes()->with('user')->orderByDesc('created_at')->get();

        return LeadNoteResource::collection($notes);
    }

    public function store(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $note = $this->leadService->addNote($lead, $validated);

        return response()->json([
            'data' => new LeadNoteResource($note),
        ], 201);
    }

    public function update(Request $request, LeadNote $note): LeadNoteResource
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        return new LeadNoteResource($this->leadService->updateNote($note, $validated));
    }

    public function destroy(LeadNote $note): JsonResponse
    {
        $this->leadService->deleteNote($note);

        return response()->json(['message' => 'Note deleted.']);
    }
}
