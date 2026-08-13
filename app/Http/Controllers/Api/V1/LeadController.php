<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Resources\LeadActivityResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\Interfaces\LeadServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function __construct(
        protected LeadServiceInterface $leadService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Lead::with(['tags', 'assignee', 'product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('company', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $leads = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return LeadResource::collection($leads);
    }

    public function store(StoreLeadRequest $request): LeadResource
    {
        $lead = $this->leadService->create($request->validated());

        return new LeadResource($lead);
    }

    public function show(Lead $lead): LeadResource
    {
        $lead->load(['tags', 'assignee', 'product', 'activities.user', 'contact']);

        return new LeadResource($lead);
    }

    public function update(Request $request, Lead $lead): LeadResource
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'status' => 'sometimes|in:new,contacted,qualified,proposal,negotiation,won,lost,dormant',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'estimated_value' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'product_id' => 'nullable|exists:products,id',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $lead = $this->leadService->update($lead, $request->all());

        return new LeadResource($lead);
    }

    public function assign(Request $request, Lead $lead): LeadResource
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $lead = $this->leadService->assign($lead, $request->assigned_to);

        return new LeadResource($lead->load('assignee', 'tags', 'product'));
    }

    public function updateStatus(Request $request, Lead $lead): LeadResource
    {
        $request->validate([
            'status' => 'required|in:new,contacted,qualified,proposal,negotiation,won,lost,dormant',
        ]);

        $lead = $this->leadService->changeStatus($lead, $request->status);

        return new LeadResource($lead->load('assignee', 'tags', 'product'));
    }

    public function convert(Lead $lead): JsonResponse
    {
        if ($lead->isConverted()) {
            return response()->json(['message' => 'این لید قبلاً تبدیل شده است.'], 422);
        }

        $result = $this->leadService->convertToCustomer($lead);

        return response()->json([
            'message' => 'لید با موفقیت به مشتری تبدیل شد.',
            'user' => $result['user'],
            'lead' => new LeadResource($result['lead']),
        ]);
    }

    public function activities(Lead $lead): AnonymousResourceCollection
    {
        $activities = $lead->activities()->with('user')->orderByDesc('created_at')->get();

        return LeadActivityResource::collection($activities);
    }

    public function addActivity(Request $request, Lead $lead): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:note,call,email,meeting,task',
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $this->leadService->addActivity($lead, $request->only([
            'type', 'subject', 'description', 'scheduled_at',
        ]));

        return response()->json(['message' => 'فعالیت ثبت شد.'], 201);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->leadService->getStats());
    }

    public function pipeline(): JsonResponse
    {
        $pipeline = $this->leadService->getPipeline();

        return response()->json($pipeline);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'exists:leads,id',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $count = $this->leadService->bulkAssign($request->lead_ids, $request->assigned_to);

        return response()->json([
            'message' => "{$count} lead(s) assigned.",
            'assigned_count' => $count,
        ]);
    }

    public function reminders(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $userId = $user->role === 'admin' ? null : $user->id;

        return LeadActivityResource::collection($this->leadService->getReminders($userId));
    }

    public function createReminder(Request $request, Lead $lead): JsonResponse
    {
        $request->validate([
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date',
        ]);

        $reminder = $this->leadService->createReminder($lead, $request->only([
            'subject', 'description', 'scheduled_at',
        ]));

        return response()->json([
            'data' => new LeadActivityResource($reminder->load('user')),
        ], 201);
    }

    public function completeReminder(LeadActivity $activity): JsonResponse
    {
        $reminder = $this->leadService->completeReminder($activity);

        return response()->json([
            'data' => new LeadActivityResource($reminder),
        ]);
    }
}
