<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreOrderCarRequest\StorePreOrderCarRequestRequest;
use App\Http\Resources\PreOrderCarRequestResource;
use App\Http\Resources\PreOrderCarResource;
use App\Models\PreOrderCar;
use App\Models\PreOrderCarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PreOrderCarRequestController extends Controller
{
    /**
     * Submit a new customer request ("open request") for a pending
     * pre-order car. Multiple different customers may each submit their
     * own request for the same car; one request per customer per car.
     */
    public function store(StorePreOrderCarRequestRequest $request, PreOrderCar $preOrderCar): JsonResponse
    {
        $carRequest = $preOrderCar->requests()->create([
            'customer_id' => $request->validated('customer_id'),
            'status' => PreOrderCarRequest::STATUS_PENDING,
            'notes' => $request->validated('notes'),
        ]);

        return response()->json([
            'message' => 'تم تسجيل طلبك على هذه السيارة بنجاح',
            'data' => new PreOrderCarRequestResource($carRequest->load('customer')),
        ], 201);
    }

    /**
     * All requests submitted for one pre-order car (for staff review).
     */
    public function index(PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('viewAny', PreOrderCarRequest::class);

        $requests = $preOrderCar->requests()->with('customer')->orderByDesc('id')->get();

        return response()->json(['data' => PreOrderCarRequestResource::collection($requests)]);
    }

    /**
     * Approve one customer's request: completes the pre-order car,
     * auto-rejects every other request on it, and creates the real
     * Batch + Car + Order for the winning customer.
     */
    public function approve(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        try {
            $order = $preOrderCar->approveRequest($preOrderCarRequest, Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تمت الموافقة على الطلب، وتم إنشاء طلب شراء فعلي للعميل',
            'data' => [
                'pre_order_car' => new PreOrderCarResource($preOrderCar->fresh()),
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);
    }

    /**
     * Manually reject a single request without completing the car — the
     * car stays "pending" and open to the remaining customers.
     */
    public function reject(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);
        $this->authorize('update', $preOrderCarRequest);

        if ($preOrderCarRequest->pre_order_car_id !== $preOrderCar->id) {
            return response()->json(['message' => 'هذا الطلب لا يخص سيارة الطلب المسبق هذه'], 422);
        }

        if (! $preOrderCarRequest->isPending()) {
            return response()->json(['message' => 'لا يمكن رفض إلا طلب لا يزال قيد الانتظار'], 422);
        }

        $preOrderCarRequest->update([
            'status' => PreOrderCarRequest::STATUS_REJECTED,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم رفض الطلب',
            'data' => new PreOrderCarRequestResource($preOrderCarRequest->fresh()),
        ]);
    }

    /**
     * Cancel a still-pending request (by the customer, or staff on their
     * behalf).
     */
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
