<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BatchCarsImportFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Batch\ImportBatchCarsRequest;
use App\Http\Requests\Batch\StoreBatchRequest;
use App\Http\Requests\Batch\UpdateBatchRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Services\BatchCarsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Batch::class);

        $batches = Batch::query()
            ->with('supplier')
            ->withCount('cars')
            ->when($request->filled('supplier_id'), fn($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(BatchResource::collection($batches)->response()->getData(true));
    }

    public function store(StoreBatchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cars = $data['cars'];
        unset($data['cars']);

        $batch = app(BatchCarsImportService::class)->createFromCars(
            $data,
            $cars,
            $request->user()->id,
        );

        return response()->json([
            'message' => 'تم إنشاء دفعة الاستيراد بنجاح',
            'data' => new BatchResource($batch->load(['supplier', 'cars'])),
        ], 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        $this->authorize('view', $batch);

        $batch->load([
            'supplier',
            'cars.firstOrder.customer',
            'cars.currentOrder.customer',
            'payments',
        ]);

        return response()->json([
            'data' => new BatchResource($batch),
        ]);
    }

    public function update(UpdateBatchRequest $request, Batch $batch): JsonResponse
    {
        $batch->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث دفعة الاستيراد بنجاح',
            'data' => new BatchResource($batch->fresh(['supplier'])),
        ]);
    }

    public function destroy(Batch $batch): JsonResponse
    {
        $this->authorize('delete', $batch);

        if ($batch->cars()->exists() || $batch->payments()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الدفعة لوجود سيارات أو دفعات مالية مرتبطة بها',
            ], 422);
        }

        $batch->delete();

        return response()->json(['message' => 'تم حذف الدفعة بنجاح']);
    }

    public function import(ImportBatchCarsRequest $request, BatchCarsImportService $importService): JsonResponse
    {
        try {
            $result = $importService->import(
                $request->validated(),
                $request->file('file'),
                $request->user()->id
            );
        } catch (BatchCarsImportFailedException $e) {
            Log::warning('Batch cars import failed: ' . $e->getMessage(), [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'data' => [
                    'batch' => null,
                    'created' => 0,
                    'skipped' => count($e->errors()),
                    'errors' => $e->errors(),
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'تم استيراد دفعة الاستيراد والسيارات بنجاح',
            'data' => [
                'batch' => new BatchResource($result['batch']),
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ],
        ], 201);
    }
}
