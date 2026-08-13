<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeadStageResource;
use App\Models\LeadStage;
use App\Services\Interfaces\LeadServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadStageController extends Controller
{
    public function __construct(
        protected LeadServiceInterface $leadService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return LeadStageResource::collection($this->leadService->getStages());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:100|unique:lead_stages,key',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $stage = $this->leadService->createStage($validated);

        return response()->json([
            'data' => new LeadStageResource($stage),
        ], 201);
    }

    public function update(Request $request, LeadStage $stage): LeadStageResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:100|unique:lead_stages,key,'.$stage->id,
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        return new LeadStageResource($this->leadService->updateStage($stage, $validated));
    }

    public function destroy(LeadStage $stage): JsonResponse
    {
        $this->leadService->deleteStage($stage);

        return response()->json(['message' => 'Stage deleted.']);
    }
}
