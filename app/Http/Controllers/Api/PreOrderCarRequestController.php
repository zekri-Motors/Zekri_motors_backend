<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreOrderCarRequest\StorePreOrderCarRequestRequest;
use App\Http\Resources\PreOrderCarRequestResource;
use App\Models\PreOrderCar;
use App\Models\PreOrderCarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PreOrderCarRequestController extends Controller
{
    /**
     * Public: submit a pre-order request linked directly to a customer.
     */
    public function store(StorePreOrderCarRequestRequest $request, PreOrderCar $preOrderCar): JsonResponse
    {
        $validated = $request->validated();

        $carRequest = $preOrderCar->requests()->create([
            'customer_id' => $validated['customer_id'],
            'status'      => PreOrderCarRequest::STATUS_PENDING,
            'notes'       => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم تسجيل طلبك المسبق على هذه السيارة بنجاح',
            'data'    => new PreOrderCarRequestResource($carRequest->load('customer')),
        ], 201);
    }

    /**
     * All pre-order requests for one catalog car (staff).
     */
    public function index(PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('viewAny', PreOrderCarRequest::class);

        $requests = $preOrderCar->requests()->with('customer')->orderByDesc('id')->get();

        return response()->json(['data' => PreOrderCarRequestResource::collection($requests)]);
    }

    /**
     * Approve: mark the pre-order request completed only (no order/car/batch).
     */
    public function approve(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        try {
            $approved = $preOrderCar->approveRequest($preOrderCarRequest, Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تمت الموافقة على الطلب المسبق (بدون إنشاء طلب شراء أو سيارة في النظام)',
            'data' => new PreOrderCarRequestResource($approved),
        ]);
    }

    public function reject(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        return response()->json([
            'message' => 'لا يمكن رفض طلبات الطلب المسبق — يُقبل كل طلب على حدة دون رفض الآخرين',
        ], 422);
    }

    public function destroy(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('delete', $preOrderCarRequest);

        if ($preOrderCarRequest->pre_order_car_id !== $preOrderCar->id) {
            return response()->json(['message' => 'هذا الطلب لا يخص سيارة الطلب المسبق هذه'], 422);
        }

        if (! $preOrderCarRequest->isPending()) {
            return response()->json(['message' => 'لا يمكن إلغاء إلا طلب لا يزال قيد الانتظار'], 422);
        }

        $preOrderCarRequest->delete();

        return response()->json(['message' => 'تم إلغاء الطلب']);
    }
}
