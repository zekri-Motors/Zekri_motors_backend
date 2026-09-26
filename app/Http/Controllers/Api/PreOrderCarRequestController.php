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
     * Approve one customer's request: creates a dedicated Batch + Car +
     * Order for that customer. The catalog model stays pending so every
     * other request can be approved too — nothing is auto-rejected.
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
            'message' => 'تمت الموافقة على الطلب، وتم إنشاء طلب شراء فعلي للعميل (يبقى النموذج مفتوحًا لبقية الطلبات)',
            'data' => [
                'pre_order_car' => new PreOrderCarResource($preOrderCar->fresh()),
                
            ],
        ]);
    }

    /**
     * Rejecting customer requests on a catalog model is not supported —
     * every submitted request is meant to be approved when ready.
     */
    public function reject(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        return response()->json([
            'message' => 'لا يمكن رفض طلبات الطلب المسبق — يُقبل كل طلب على حدة دون رفض الآخرين',
        ], 422);
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