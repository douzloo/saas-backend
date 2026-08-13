<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\AssignmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductAssignmentRequest;
use App\Http\Requests\UpdateProductAssignmentRequest;
use App\Http\Resources\ProductAssignmentResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductAssignment;
use App\Models\User;
use App\Services\Interfaces\ProductAssignmentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductAssignmentController extends Controller
{
    public function __construct(
        protected ProductAssignmentServiceInterface $assignmentService,
    ) {}

    public function index(Product $product): AnonymousResourceCollection
    {
        $staff = $this->assignmentService->getProductStaff($product);

        return ProductAssignmentResource::collection($staff);
    }

    public function store(Product $product, CreateProductAssignmentRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail((int) $request->validated('user_id'));

        try {
            $assignment = $this->assignmentService->assign(
                $currentUser,
                $targetUser,
                $product,
                $request->validated('role'),
                $request->validated('is_primary', false),
            );
        } catch (AssignmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new ProductAssignmentResource($assignment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        Product $product,
        ProductAssignment $assignment,
        UpdateProductAssignmentRequest $request,
    ): JsonResponse {
        try {
            $assignment = $this->assignmentService->update(
                $request->user(),
                $assignment,
                $request->validated(),
            );
        } catch (AssignmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(new ProductAssignmentResource($assignment->load('user')));
    }

    public function destroy(Product $product, ProductAssignment $assignment): JsonResponse
    {
        $targetUser = $assignment->user;

        try {
            $this->assignmentService->remove(
                request()->user(),
                $targetUser,
                $product,
            );
        } catch (AssignmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(null, 204);
    }

    public function userProducts(User $user): AnonymousResourceCollection
    {
        $products = $this->assignmentService->getUserProducts($user);

        return ProductResource::collection($products);
    }
}
