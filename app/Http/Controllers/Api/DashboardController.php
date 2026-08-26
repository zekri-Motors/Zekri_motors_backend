<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * The core stats panel, per the explicit requirement list:
     * عدد العملاء/الموردين/السيارات/الطلبات، إجمالي المبيعات، إجمالي
     * الأرباح، إجمالي العمولات، إحصائيات عامة.
     *
     * Sales totals are visible to anyone with dashboard.view (admin and
     * up). Profit figures are gated separately behind reports.view_profit
     * (super-admin only), matching the explicit "Admin: كل الصلاحيات ما
     * عدا... الفائدة لكل سيارة" requirement — profit is the aggregate
     * form of that same restricted figure.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('dashboard.view');

        $user = $request->user();

        if ($user->agent) {
            $agentId = $user->agent->id;
            $realOrders = $this->realOrdersQuery()->where('agent_id', $agentId);
            $soldCars = $this->soldCarsQuery($agentId);

            $stats = [
                'customers_count' => Customer::where('agent_id', $agentId)->count(),
                'suppliers_count' => 0,
                'cars_count' => (clone $soldCars)->count(),
                'orders_count' => (clone $realOrders)->count(),
                'agents_count' => 1,

                'orders_by_status' => (clone $realOrders)
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status'),

                'total_sales' => (float) (clone $soldCars)->sum('sale_price'),

                'total_commissions' => (float) \App\Models\AgentTransaction::query()
                    ->where('agent_id', $agentId)
                    ->where('direction', \App\Models\AgentTransaction::DIRECTION_OUT)
                    ->whereNull('payment_id')
                    ->sum('amount'),
            ];
        } else {
            $canSeeProfit = $user->can('reports.view_profit');
            $realOrders = $this->realOrdersQuery();
            $soldCars = $this->soldCarsQuery();

            $stats = [
                'customers_count' => Customer::count(),
                'suppliers_count' => Supplier::count(),
                'cars_count' => Car::count(),
                'orders_count' => (clone $realOrders)->count(),
                'agents_count' => Agent::count(),

                'orders_by_status' => (clone $realOrders)
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status'),

                // The first order on a car is only a placeholder/first-owner
                // record. A car counts as sold only when it has a later order.
                'total_sales' => (float) (clone $soldCars)->sum('sale_price'),

                'total_commissions' => (float) \App\Models\AgentTransaction::query()
                    ->where('direction', \App\Models\AgentTransaction::DIRECTION_OUT)
                    ->whereNull('payment_id') // exclude customer-payment-collection entries, keep manual commission entries
                    ->sum('amount'),
            ];

            if ($canSeeProfit) {
                $stats['total_purchase_cost'] = (float) (clone $soldCars)->sum('foreign_purchase_price');
                $stats['total_expenses'] = (float) \App\Models\CarExpense::query()->sum('local_amount')
                    + (float) \App\Models\Expense::query()->sum('amount');
                $stats['total_profit'] = $stats['total_sales'] - $stats['total_purchase_cost'] - $stats['total_expenses'];
            }
        }

        return response()->json(['data' => $stats]);
    }

    protected function realOrdersQuery()
    {
        return Order::query()->whereNotIn('id', $this->firstOrderIdsPerCarQuery());
    }

    protected function soldCarsQuery(?int $agentId = null)
    {
        return Car::query()->whereHas('orders', function ($query) use ($agentId) {
            $query->whereNotIn('id', $this->firstOrderIdsPerCarQuery())
                ->when($agentId, fn($query) => $query->where('agent_id', $agentId));
        });
    }

    protected function firstOrderIdsPerCarQuery()
    {
        return Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPING, Order::STATUS_IN_SHOW_ROOM, Order::STATUS_AVAILABLE])
            ->selectRaw('MIN(id) as id')
            ->groupBy('car_id');
    }
}
