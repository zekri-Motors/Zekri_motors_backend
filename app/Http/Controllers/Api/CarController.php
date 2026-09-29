<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Car\StoreCarExpenseRequest;
use App\Http\Requests\Car\StoreCarRequest;
use App\Http\Requests\Car\UpdateCarRequest;
use App\Http\Resources\CarExpenseResource;
use App\Http\Resources\CarResource;
use App\Http\Resources\CarOwnershipHistoryResource;
use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarController extends Controller
{
    /**
     * Public catalogue endpoint: list only available cars without requiring
     * a Sanctum token. CarResource already hides purchase/cost fields for
     * guest requests because there is no authenticated cars.view_cost user.
     */
    public function available(Request $request): JsonResponse
    {
        $query = Car::query()
            ->with(['media' => fn ($q) => $q->orderByDesc('car_media_links.is_cover')->orderBy('car_media_links.sort_order')->orderByDesc('car_media.id')])
            ->where('status', '!=', Car::STATUS_SOLD)
            ->when($request->filled('brand'), fn($q) => $q->where('brand', 'like', '%' . $request->string('brand') . '%'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('brand', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhere('vin', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id');

        $cars = $query->paginate($request->integer('per_page', 15));

        return response()->json(CarResource::collection($cars)->response()->getData(true));
    }

    /**
     * List cars with filters relevant to every audience:
     *   - super-admin (cars.view_cost): full data including cost/profit.
     *   - admin (suppliers.view but NOT cars.view_cost): full operational
     *     data (supplier, batch...) but never cost figures.
     *   - agent/customer (plain cars.view only): browsing data only
     *     (brand/model/sale price), catalogue filtered to non-sold cars.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Car::class);

        $user = $request->user();
        $canSeeOperationalData = $user->can('suppliers.view');

        $query = Car::query()
            ->with([
                'firstOrder.customer',
                'currentOrder.customer',
                'media' => fn ($q) => $q->orderByDesc('car_media_links.is_cover')->orderBy('car_media_links.sort_order')->orderByDesc('car_media.id'),
            ])
            ->when($canSeeOperationalData, fn($q) => $q->with([
                'supplier',
                'containerOpener',
                'batch:id,exchange_rate,status', // exchange_rate مطلوب صريحاً
            ]))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('brand'), fn($q) => $q->where('brand', 'like', '%' . $request->string('brand') . '%'))
            ->when($request->filled('batch_id'), fn($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('supplier_id') && $canSeeOperationalData, fn($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('vin'), fn($q) => $q->where('vin', $request->string('vin')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('brand', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhere('vin', 'like', "%{$term}%");
                });
            })
            // Anyone without operational visibility (agent/customer) is
            // browsing a sales catalogue: exclude already-sold cars.
            ->when(! $canSeeOperationalData, fn($q) => $q->whereNotIn('status', [Car::STATUS_SOLD]))
            ->orderByDesc('id');

        $cars = $query->paginate($request->integer('per_page', 15));

        return response()->json(CarResource::collection($cars)->response()->getData(true));
    }

    public function store(StoreCarRequest $request): JsonResponse
    {
        $car = Car::create($request->validated() + ['status' => $request->input('status', Car::STATUS_SHIPPING)]);

        $user = $request->user();
        $canSeeOperationalData = $user && $user->can('suppliers.view');

        $car->load([
            'supplier',
            'containerOpener',
            'firstOrder.customer',
            'currentOrder.customer',
            'media' => fn ($q) => $q->orderByDesc('car_media_links.is_cover')->orderBy('car_media_links.sort_order')->orderByDesc('car_media.id'),
        ]);

        return response()->json([
            'message' => 'تم إضافة السيارة بنجاح',
            'data' => new CarResource($car),
        ], 201);
    }

    public function show(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        $user = $request->user();
        $canSeeOperationalData = $user->can('suppliers.view');
        $canSeeCosts = $user->can('cars.view_cost');

        $car->load([
            'documents',
            'firstOrder.customer',
            'currentOrder.customer',
            'media' => fn ($q) => $q->with('tags')->orderByDesc('car_media_links.is_cover')->orderBy('car_media_links.sort_order')->orderByDesc('car_media.id'),
        ]);

        if ($canSeeOperationalData) {
            $car->load([
                'supplier',
                'containerOpener',
                'order',
            ]);
        }
        if ($canSeeCosts) {
            $car->load(['expenses', 'generalExpenses']);
        }

        return response()->json([
            'data' => new CarResource($car),
        ]);
    }

    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $car->update($request->validated());

        $user = $request->user();
        $canSeeOperationalData = $user && $user->can('suppliers.view');

        $car->load([
            'supplier',
            'containerOpener',
            'firstOrder.customer',
            'currentOrder.customer',
            'media' => fn ($q) => $q->orderByDesc('car_media_links.is_cover')->orderBy('car_media_links.sort_order')->orderByDesc('car_media.id'),
        ]);

        return response()->json([
            'message' => 'تم تحديث بيانات السيارة بنجاح',
            'data' => new CarResource($car),
        ]);
    }

    public function destroy(Car $car): JsonResponse
    {
        $this->authorize('delete', $car);

        if ($car->order()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف السيارة لوجود طلب مرتبط بها',
            ], 422);
        }

        $car->delete();

        return response()->json(['message' => 'تم حذف السيارة بنجاح']);
    }

    /**
     * Add a cost line (customs, transport, repair...) to a car.
     *
     * NOTE: adding an expense only requires cars.update (an admin manages
     * a car's logistics and may need to log a cost line as it happens),
     * but per expenses() below, an admin still cannot READ the resulting
     * cost breakdown — only super-admin (cars.view_cost) can.
     */
    public function storeExpense(StoreCarExpenseRequest $request, Car $car): JsonResponse
    {
        $expense = $car->expenses()->create($request->validated());

        return response()->json([
            'message' => 'تم إضافة المصروف بنجاح',
            'data' => new CarExpenseResource($expense),
        ], 201);
    }

    /**
     * List cost lines for a car. Cost-tier data: requires cars.view_cost
     * (super-admin only), separate from cars.update used by storeExpense.
     */
    public function expenses(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        if (! $request->user()->can('cars.view_cost')) {
            return response()->json(['message' => 'لا تملك الصلاحية اللازمة لعرض تكاليف السيارة'], 403);
        }

        return response()->json([
            'data' => CarExpenseResource::collection($car->expenses()->orderByDesc('id')->get()),
        ]);
    }

    /**
     * Get the ownership history of a car (all orders with their associated customers).
     */
    public function ownershipHistory(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        $orders = $car->orders()
            ->with(['customer'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => CarOwnershipHistoryResource::collection($orders),
        ]);
    }
}
