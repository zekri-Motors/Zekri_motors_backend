<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreOrderCarRequest\StorePreOrderCarRequestRequest;
use App\Http\Requests\PreOrderCarRequest\UpdatePreOrderCarRequestRequest;
use App\Http\Requests\PreOrderCarRequest\StorePreOrderCarRequestDirectRequest;
use App\Http\Requests\PreOrderCarRequest\UpdatePreOrderCarRequestDirectRequest;
use App\Http\Resources\PreOrderCarRequestResource;
use App\Models\PreOrderCar;
use App\Models\PreOrderCarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreOrderCarRequestController extends Controller
{
    // -----------------------------------------------------------------------
    // GET /pre-order-car-requests
    // بحث شامل عبر كل الطلبات المسبقة بغض النظر عن السيارة
    // query params:
    //   customer_id      — فلتر بمعرّف العميل
    //   pre_order_car_id — فلتر بمعرّف السيارة
    //   status           — فلتر بالحالة (draft | pending | completed)
    //   search           — بحث نصي حر: اسم/هاتف العميل، ماركة/موديل السيارة
    //   per_page         — عدد النتائج في الصفحة (افتراضي 15)
    // -----------------------------------------------------------------------
    public function allRequests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PreOrderCarRequest::class);

        $query = PreOrderCarRequest::query()
            ->with(['customer', 'preOrderCar'])
            // فلتر بالعميل
            ->when(
                $request->filled('customer_id'),
                fn ($q) => $q->where('customer_id', $request->integer('customer_id'))
            )
            // فلتر بالسيارة
            ->when(
                $request->filled('pre_order_car_id'),
                fn ($q) => $q->where('pre_order_car_id', $request->integer('pre_order_car_id'))
            )
            // فلتر بالحالة
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status'))
            )
            // بحث نصي حر: اسم العميل، هاتفه، ماركة السيارة، موديلها
            ->when(
                $request->filled('search'),
                function ($q) use ($request) {
                    $term = '%' . $request->string('search') . '%';
                    $q->where(function ($sub) use ($term) {
                        $sub->whereHas('customer', fn ($cq) =>
                            $cq->where('name', 'like', $term)
                               ->orWhere('phone', 'like', $term)
                        )
                        ->orWhereHas('preOrderCar', fn ($cq) =>
                            $cq->where('brand', 'like', $term)
                               ->orWhere('model', 'like', $term)
                               ->orWhere('finition', 'like', $term)
                        );
                    });
                }
            )
            ->orderByDesc('id');

        $requests = $query->paginate($request->integer('per_page', 15));

        return response()->json(
            PreOrderCarRequestResource::collection($requests)->response()->getData(true)
        );
    }

    // -----------------------------------------------------------------------
    // GET /pre-order-car-requests/{preOrderCarRequest}
    // تفاصيل طلب واحد (مسار مسطّح بدون الحاجة لمعرّف السيارة)
    // -----------------------------------------------------------------------
    public function showDirect(PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('view', $preOrderCarRequest);

        $preOrderCarRequest->load(['customer', 'preOrderCar', 'decidedByUser']);

        return response()->json(['data' => new PreOrderCarRequestResource($preOrderCarRequest)]);
    }

    // -----------------------------------------------------------------------
    // POST /pre-order-car-requests
    // إنشاء طلب مسبق جديد من مسار مسطّح (يتضمن pre_order_car_id)
    // -----------------------------------------------------------------------
    public function storeDirect(StorePreOrderCarRequestDirectRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var PreOrderCar $preOrderCar */
        $preOrderCar = PreOrderCar::findOrFail($validated['pre_order_car_id']);

        $carRequest = $preOrderCar->requests()->create([
            'customer_id' => $validated['customer_id'],
            'status'      => PreOrderCarRequest::STATUS_PENDING,
            'notes'       => $validated['notes'] ?? null,
            'paid_amount' => $validated['paid_amount'] ?? 0,
        ]);

        return response()->json([
            'message' => 'تم تسجيل الطلب المسبق بنجاح',
            'data'    => new PreOrderCarRequestResource($carRequest->load(['customer', 'preOrderCar'])),
        ], 201);
    }

    // -----------------------------------------------------------------------
    // PUT /pre-order-car-requests/{preOrderCarRequest}
    // تعديل طلب من مسار مسطّح
    // -----------------------------------------------------------------------
    public function updateDirect(
        UpdatePreOrderCarRequestDirectRequest $request,
        PreOrderCarRequest $preOrderCarRequest
    ): JsonResponse {
        // منع تغيير العميل إذا كان الطلب مكتملاً
        if ($preOrderCarRequest->isCompleted() && $request->has('customer_id')) {
            return response()->json(['message' => 'لا يمكن تغيير العميل على طلب مكتمل'], 422);
        }

        $preOrderCarRequest->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الطلب المسبق بنجاح',
            'data'    => new PreOrderCarRequestResource(
                $preOrderCarRequest->fresh(['customer', 'preOrderCar'])
            ),
        ]);
    }

    // -----------------------------------------------------------------------
    // DELETE /pre-order-car-requests/{preOrderCarRequest}
    // حذف طلب من مسار مسطّح
    // -----------------------------------------------------------------------
    public function destroyDirect(PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('delete', $preOrderCarRequest);

        if (! $preOrderCarRequest->isPending()) {
            return response()->json(['message' => 'لا يمكن حذف إلا طلب لا يزال قيد الانتظار'], 422);
        }

        $preOrderCarRequest->delete();

        return response()->json(['message' => 'تم حذف الطلب المسبق بنجاح']);
    }

    // -----------------------------------------------------------------------
    // POST /pre-order-car-requests/{preOrderCarRequest}/approve
    // الموافقة على طلب من مسار مسطّح
    // -----------------------------------------------------------------------
    public function approveDirect(PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCarRequest);

        /** @var PreOrderCar $preOrderCar */
        $preOrderCar = $preOrderCarRequest->preOrderCar;

        try {
            $approved = $preOrderCar->approveRequest($preOrderCarRequest, Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تمت الموافقة على الطلب المسبق بنجاح',
            'data'    => new PreOrderCarRequestResource($approved->load(['customer', 'preOrderCar'])),
        ]);
    }

    // =======================================================================
    // المسارات المتداخلة (nested) /pre-order-cars/{preOrderCar}/requests/...
    // =======================================================================

    // -----------------------------------------------------------------------
    // GET /pre-order-cars/{preOrderCar}/requests
    // كل طلبات سيارة معيّنة مع البحث والفلترة
    // -----------------------------------------------------------------------
    public function index(Request $request, PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('viewAny', PreOrderCarRequest::class);

        $requests = $preOrderCar->requests()
            ->with('customer')
            // فلتر بالحالة
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status'))
            )
            // فلتر بالعميل
            ->when(
                $request->filled('customer_id'),
                fn ($q) => $q->where('customer_id', $request->integer('customer_id'))
            )
            // بحث نصي باسم العميل أو هاتفه
            ->when(
                $request->filled('search'),
                fn ($q) => $q->whereHas('customer', function ($cq) use ($request) {
                    $term = '%' . $request->string('search') . '%';
                    $cq->where('name', 'like', $term)
                       ->orWhere('phone', 'like', $term);
                })
            )
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(
            PreOrderCarRequestResource::collection($requests)->response()->getData(true)
        );
    }

    // -----------------------------------------------------------------------
    // POST /pre-order-cars/{preOrderCar}/requests
    // إنشاء طلب مسبق جديد (يستخدم customer_id)
    // -----------------------------------------------------------------------
    public function store(StorePreOrderCarRequestRequest $request, PreOrderCar $preOrderCar): JsonResponse
    {
        $validated = $request->validated();

        $carRequest = $preOrderCar->requests()->create([
            'customer_id' => $validated['customer_id'],
            'status'      => PreOrderCarRequest::STATUS_PENDING,
            'notes'       => $validated['notes'] ?? null,
            'paid_amount' => $validated['paid_amount'] ?? 0,
        ]);

        return response()->json([
            'message' => 'تم تسجيل الطلب المسبق على هذه السيارة بنجاح',
            'data'    => new PreOrderCarRequestResource($carRequest->load('customer')),
        ], 201);
    }

    // -----------------------------------------------------------------------
    // GET /pre-order-cars/{preOrderCar}/requests/{preOrderCarRequest}
    // تفاصيل طلب واحد
    // -----------------------------------------------------------------------
    public function show(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('view', $preOrderCarRequest);

        if ($preOrderCarRequest->pre_order_car_id !== $preOrderCar->id) {
            return response()->json(['message' => 'هذا الطلب لا يخص سيارة الطلب المسبق هذه'], 422);
        }

        $preOrderCarRequest->load(['customer', 'preOrderCar', 'decidedByUser']);

        return response()->json(['data' => new PreOrderCarRequestResource($preOrderCarRequest)]);
    }

    // -----------------------------------------------------------------------
    // PUT /pre-order-cars/{preOrderCar}/requests/{preOrderCarRequest}
    // تعديل الطلب (ملاحظات / عميل / حالة)
    // -----------------------------------------------------------------------
    public function update(
        UpdatePreOrderCarRequestRequest $request,
        PreOrderCar $preOrderCar,
        PreOrderCarRequest $preOrderCarRequest
    ): JsonResponse {
        if ($preOrderCarRequest->pre_order_car_id !== $preOrderCar->id) {
            return response()->json(['message' => 'هذا الطلب لا يخص سيارة الطلب المسبق هذه'], 422);
        }

        // منع تغيير العميل إذا كان الطلب مكتملاً
        if ($preOrderCarRequest->isCompleted() && $request->has('customer_id')) {
            return response()->json(['message' => 'لا يمكن تغيير العميل على طلب مكتمل'], 422);
        }

        $preOrderCarRequest->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الطلب المسبق بنجاح',
            'data'    => new PreOrderCarRequestResource($preOrderCarRequest->fresh(['customer', 'preOrderCar'])),
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /pre-order-cars/{preOrderCar}/requests/{preOrderCarRequest}/approve
    // الموافقة على الطلب وتحويله إلى completed
    // -----------------------------------------------------------------------
    public function approve(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        try {
            $approved = $preOrderCar->approveRequest($preOrderCarRequest, Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تمت الموافقة على الطلب المسبق بنجاح',
            'data'    => new PreOrderCarRequestResource($approved->load('customer')),
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /pre-order-cars/{preOrderCar}/requests/{preOrderCarRequest}/reject
    // رفض الطلب — منطق العمل لا يدعمه حاليًا
    // -----------------------------------------------------------------------
    public function reject(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        return response()->json([
            'message' => 'لا يمكن رفض طلبات الطلب المسبق — يُقبل كل طلب على حدة دون رفض الآخرين',
        ], 422);
    }

    // -----------------------------------------------------------------------
    // DELETE /pre-order-cars/{preOrderCar}/requests/{preOrderCarRequest}
    // حذف طلب (يُسمح فقط إذا كانت حالته pending)
    // -----------------------------------------------------------------------
    public function destroy(PreOrderCar $preOrderCar, PreOrderCarRequest $preOrderCarRequest): JsonResponse
    {
        $this->authorize('delete', $preOrderCarRequest);

        if ($preOrderCarRequest->pre_order_car_id !== $preOrderCar->id) {
            return response()->json(['message' => 'هذا الطلب لا يخص سيارة الطلب المسبق هذه'], 422);
        }

        if (! $preOrderCarRequest->isPending()) {
            return response()->json(['message' => 'لا يمكن حذف إلا طلب لا يزال قيد الانتظار'], 422);
        }

        $preOrderCarRequest->delete();

        return response()->json(['message' => 'تم حذف الطلب المسبق بنجاح']);
    }
}
