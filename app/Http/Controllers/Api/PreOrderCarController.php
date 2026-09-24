<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PreOrderCarsImportFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreOrderCar\ImportPreOrderCarsRequest;
use App\Http\Requests\PreOrderCar\StorePreOrderCarRequest;
use App\Http\Requests\PreOrderCar\UpdatePreOrderCarRequest;
use App\Http\Resources\PreOrderCarResource;
use App\Models\PreOrderCar;
use App\Services\PreOrderCarsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreOrderCarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PreOrderCar::class);

        $cars = PreOrderCar::query()
            ->with('supplier')
            ->withCount('requests')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(PreOrderCarResource::collection($cars)->response()->getData(true));
    }

    public function store(StorePreOrderCarRequest $request): JsonResponse
    {
        $car = PreOrderCar::create(
            $request->validated()
            + ['status' => PreOrderCar::STATUS_DRAFT, 'created_by' => $request->user()->id]
        );

        return response()->json([
            'message' => 'تم إنشاء سيارة الطلب المسبق بنجاح',
            'data' => new PreOrderCarResource($car->load('supplier')),
        ], 201);
    }

    public function show(PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('view', $preOrderCar);

        $preOrderCar->load(['supplier', 'requests.customer']);

        return response()->json(['data' => new PreOrderCarResource($preOrderCar)]);
    }

    public function update(UpdatePreOrderCarRequest $request, PreOrderCar $preOrderCar): JsonResponse
    {
        if ($preOrderCar->isCompleted()) {
            return response()->json([
                'message' => 'لا يمكن تعديل سيارة طلب مسبق مكتملة',
            ], 422);
        }

        $preOrderCar->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث سيارة الطلب المسبق بنجاح',
            'data' => new PreOrderCarResource($preOrderCar->fresh('supplier')),
        ]);
    }

    /**
     * Move a draft pre-order car into pending, making it visible to
     * customers and open for requests.
     */
    public function publish(PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('update', $preOrderCar);

        try {
            $preOrderCar->publish();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'أصبحت السيارة متاحة الآن للطلب المسبق',
            'data' => new PreOrderCarResource($preOrderCar->fresh()),
        ]);
    }

    public function destroy(PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('delete', $preOrderCar);

        if ($preOrderCar->isCompleted() || $preOrderCar->requests()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف سيارة طلب مسبق لوجود طلبات عملاء مرتبطة بها أو لأنها مكتملة',
            ], 422);
        }

        $preOrderCar->delete();

        return response()->json(['message' => 'تم حذف سيارة الطلب المسبق بنجاح']);
    }

    public function import(ImportPreOrderCarsRequest $request, PreOrderCarsImportService $importService): JsonResponse
    {
        try {
            $result = $importService->import(
                $request->file('file'),
                $request->user()->id
            );
        } catch (PreOrderCarsImportFailedException $e) {
            Log::warning('Pre-order cars import failed: ' . $e->getMessage(), [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'data' => [
                    'count' => 0,
                    'errors' => $e->errors(),
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'تم استيراد سيارات الطلب المسبق بنجاح (كمسودة — تحتاج نشر ليراها العملاء)',
            'data' => [
                'count' => $result['count'],
                'cars' => PreOrderCarResource::collection($result['created']),
                'errors' => $result['errors'],
            ],
        ], 201);
    }
}
