<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ApproveAgentRemittanceRequest;
use App\Http\Requests\AgentTransaction\StoreAgentTransactionRequest;
use App\Http\Resources\AgentTransactionResource;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\AgentTransaction;
use App\Models\CustomerPayment;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentTransactionController extends Controller
{
    /**
     * List real agent-collected customer payments. Admins can filter by
     * agent; agents only see payments collected under their own agent profile.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AgentTransaction::class);

        $user = $request->user();

        $query = CustomerPayment::query()
            ->with(['customer', 'agent', 'creator', 'generalTreasuryTransfer'])
            ->whereNotNull('agent_id')
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->date('date_to')));

        if ($user->agent) {
            $query->where('agent_id', $user->agent->id);
        } elseif ($user->can('agent_transactions.view')) {
            $query
                ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
                ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->integer('agent_id')))
                ->when($request->filled('is_remitted'), function ($q) use ($request) {
                    $request->boolean('is_remitted')
                        ? $q->whereNotNull('remittance_id')
                        : $q->whereNull('remittance_id');
                });
        } else {
            $query->where('agent_id', $user->agent?->id ?? 0);
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json(CustomerPaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Record a manual ledger entry (commission, cash advance, correction)
     * against an agent, chaining off their most recent balance.
     */
    public function store(StoreAgentTransactionRequest $request): JsonResponse
    {
        $transaction = DB::transaction(function () use ($request) {
            $agentId = $request->validated('agent_id');
            $direction = $request->validated('direction');
            $amount = (float) $request->validated('amount');

            $previousBalance = (float) (AgentTransaction::query()
                ->where('agent_id', $agentId)
                ->latest('id')
                ->value('current_balence') ?? 0);

            $currentBalance = $direction === AgentTransaction::DIRECTION_IN
                ? $previousBalance + $amount
                : $previousBalance - $amount;

            return AgentTransaction::create([
                'agent_id' => $agentId,
                'direction' => $direction,
                'amount' => $amount,
                'previous_balence' => $previousBalance,
                'current_balence' => $currentBalance,
                'transaction_date' => $request->validated('transaction_date'),
                'attachment' => $request->validated('attachment'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'تم تسجيل الحركة بنجاح',
            'data' => new AgentTransactionResource($transaction->load(['agent', 'creator'])),
        ], 201);
    }

    public function show(AgentTransaction $agentTransaction): JsonResponse
    {
        $this->authorize('view', $agentTransaction);

        $agentTransaction->load(['agent', 'customerPayment', 'treasuryTransaction.approver', 'creator']);

        return response()->json([
            'data' => new AgentTransactionResource($agentTransaction),
        ]);
    }

    public function approveRemittance(
        ApproveAgentRemittanceRequest $request,
        AgentTransaction $agentTransaction
    ): JsonResponse {
        $treasuryTransaction = TreasuryTransaction::query()
            ->where('source_type', TreasuryTransaction::SOURCE_AGENT_REMITTANCE)
            ->where('source_id', $agentTransaction->id)
            ->where('direction', TreasuryTransaction::DIRECTION_IN)
            ->where('status', TreasuryTransaction::STATUS_PENDING)
            ->latest('id')
            ->first();

        if (! $treasuryTransaction) {
            return response()->json([
                'message' => 'لا يوجد تحويل خزينة معلق لهذه الحركة أو تم اعتماده سابقًا',
            ], 422);
        }

        DB::transaction(function () use ($request, $agentTransaction, $treasuryTransaction) {
            $previousBalance = (float) (TreasuryTransaction::query()
                ->approved()
                ->latest('id')
                ->value('current_balence') ?? 0);
            $newBalance = $previousBalance + (float) $treasuryTransaction->amount;

            $treasuryTransaction->update([
                'status' => TreasuryTransaction::STATUS_APPROVED,
                'previous_balence' => $previousBalance,
                'current_balence' => $newBalance,
                'transaction_date' => $request->input('approval_date', now()->toDateString()),
                'notes' => $request->input('notes', $treasuryTransaction->notes),
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            CustomerPayment::query()
                ->where('remittance_id', $agentTransaction->id)
                ->update([
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'تم اعتماد تحويل الوكيل إلى الخزينة بنجاح',
            'data' => new AgentTransactionResource(
                $agentTransaction->fresh(['agent', 'customerPayment', 'treasuryTransaction.approver', 'creator'])
            ),
        ]);
    }

    /**
     * Delete a MANUAL ledger entry only. Entries that originated from a
     * customer payment (payment_id set) or a treasury remittance
     * (transaction_id set) are part of the automated, cross-referenced
     * financial trail and must be reversed through their origin (delete
     * the customer payment / void the remittance) rather than directly
     * here, to avoid breaking the chain those other records still point to.
     */
    public function destroy(AgentTransaction $agentTransaction): JsonResponse
    {
        $this->authorize('delete', $agentTransaction);

        if ($agentTransaction->payment_id !== null || $agentTransaction->transaction_id !== null) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه الحركة مباشرة لأنها ناتجة تلقائيًا عن دفعة أو تحويل، يجب التعامل معها من مصدرها',
            ], 422);
        }

        $agentTransaction->delete();

        return response()->json(['message' => 'تم حذف الحركة بنجاح']);
    }
}
