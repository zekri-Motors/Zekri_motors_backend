<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Car;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * List orders, scoped by who's asking:
     *   - admin/super-admin (orders.view): every order.
     *   - agent (orders.view_assigned): only orders tied to them.
     *   - customer (orders.view_own): only their own orders.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();

        // أول طلب (أقل id) لكل سيارة - هيتم استثناؤه
        $firstOrderIdsPerCar = Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPING, Order::STATUS_IN_SHOW_ROOM, Order::STATUS_AVAILABLE])
            ->selectRaw('MIN(id) as id')
            ->groupBy('car_id');

        $query = Order::query()
            ->with(['customer', 'car', 'agent'])
            ->whereNotIn('id', $firstOrderIdsPerCar)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')));

        if ($user->agent) {
            $query->where('agent_id', $user->agent->id);
        } elseif ($user->can('orders.view')) {
            $query
                ->when($request->filled('customer_id'), fn($q) => $q->where('customer_id', $request->integer('customer_id')))
                ->when($request->filled('agent_id'), fn($q) => $q->where('agent_id', $request->integer('agent_id')));
        } elseif ($user->can('orders.view_assigned')) {
            $query->where('agent_id', $user->agent?->id ?? 0);
        }

        $orders = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json(OrderResource::collection($orders)->response()->getData(true));
    }

    /**
     * Create a new order linking a customer, a car, and (optionally) an
     * agent. The first order keeps the car in `shipping`, which is still
     * available for sale. A later order for a different customer is treated
     * as an ownership transfer and marks the car as `sold`.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $car = Car::query()
                ->whereKey($data['car_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $firstOwnerCustomerId = $car->orders()
                ->oldest('id')
                ->value('customer_id');

            $isOwnershipTransfer = $firstOwnerCustomerId !== null
                && (int) $firstOwnerCustomerId !== (int) $data['customer_id'];

            $agentId = $request->user()->agent ? $request->user()->agent->id : ($data['agent_id'] ?? null);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $data['customer_id'],
                'car_id' => $data['car_id'],
                'agent_id' => $agentId,
                'status' => Order::STATUS_SHIPPING,
                'purchase_date' => $data['purchase_date'] ?? null,
                'shipping_date' => now()->toDateString(),
                'paid_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $order->remaining_amount = (float) $car->sale_price + (float) $car->total_expenses;
            $order->save();

            $car->update([
                'status' => $isOwnershipTransfer ? Car::STATUS_SOLD : Car::STATUS_SHIPPING,
            ]);

            return $order->fresh();
        });

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح',
            'data' => new OrderResource($order->load(['customer', 'car', 'agent'])),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'car', 'agent', 'invoice']);

        if ($request->user()->can('customer_payments.view')) {
            $order->load('payments');
        }

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $order->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الطلب بنجاح',
            'data' => new OrderResource($order->load(['customer', 'car', 'agent'])),
        ]);
    }

    /**
     * Advance the order's status by moving its CAR's status forward, per
     * the workflow defined in Car::STATUSES (order statuses are no longer
     * tracked independently — Car::booted() mirrors the new status onto
     * this order automatically the moment the car is saved below).
     */
    public function changeStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        DB::transaction(function () use ($request, $order) {
            $newStatus = $request->validated('status');
            $date = $request->input('date', now()->toDateString());

            // Query the model directly so static analysis knows this is a Car,
            // rather than inferring the relation result as stdClass.
            $car = Car::query()
                ->whereKey($order->car_id)
                ->lockForUpdate()
                ->first();

            if (! $car) {
                abort(422, 'لا توجد سيارة مرتبطة بهذا الطلب');
            }

            $dateColumn = match ($newStatus) {
                Car::STATUS_SHIPPING => 'shipping_date',
                Car::STATUS_IN_SHOW_ROOM => 'arrival_date',
                Car::STATUS_DELIVERED => 'delivery_date',
                default => null,
            };

            $car->status = $newStatus;
            if ($dateColumn) {
                $car->{$dateColumn} = $date;
            }
            $car->save(); // Car::booted() propagates $newStatus onto $order automatically.
        });

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => new OrderResource($order->fresh()->load(['customer', 'car', 'agent'])),
        ]);
    }

    /**
     * Delete an order. Blocked if any payment has already been recorded
     * against it — cancelling a paid order should go through a refund /
     * void process, not a hard delete. Also frees the car back to
     * `shipping` so it can be re-sold under the new default workflow.
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        if ($order->payments()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الطلب لوجود دفعات مسجلة عليه',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->car?->update(['status' => Car::STATUS_SHIPPING]);
            $order->delete();
        });

        return response()->json(['message' => 'تم حذف الطلب بنجاح']);
    }

    /**
     * Generate a unique, human-friendly order number, e.g. ORD-2026-A1B2C3.
     * Retries on the rare chance of a collision rather than trusting a
     * single random draw against the `unique` constraint.
     */
    protected function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
