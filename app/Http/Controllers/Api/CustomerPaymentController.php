<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\RemitAgentBalanceRequest;
use App\Http\Requests\CustomerPayment\ApproveTreasuryTransferRequest;
use App\Http\Requests\CustomerPayment\RemitCustomerPaymentRequest;
use App\Http\Requests\CustomerPayment\StoreCustomerPaymentRequest;
use App\Http\Resources\AgentTransactionResource;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\AgentTransaction;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerPayment::class);

        $user = $request->user();

        $query = CustomerPayment::query()
            ->with(['customer', 'agent', 'generalTreasuryTransfer'])
            ->when($request->filled('order_id'), fn($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('payment_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('payment_date', '<=', $request->date('date_to')));

        if ($user->agent) {
            $query->where('agent_id', $user->agent->id);
        } elseif ($user->can('customer_payments.view')) {
            $query
                ->when($request->filled('customer_id'), fn($q) => $q->where('customer_id', $request->integer('customer_id')))
                ->when($request->filled('agent_id'), fn($q) => $q->where('agent_id', $request->integer('agent_id')))
                ->when($request->filled('is_remitted'), function ($q) use ($request) {
                    $request->boolean('is_remitted')
                        ? $q->whereNotNull('remittance_id')
                        : $q->whereNull('remittance_id');
                });
        } else {
            // Scoped to: payments made by this user's own customer record,
            // OR payments collected by this user's own agent record.
            $query->where(function ($q) use ($user) {
                $q->Where('agent_id', $user->agent?->id ?? 0);
            });
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(CustomerPaymentResource::collection($payments)->response()->getData(true));
    }

    /**
     * Record a customer payment against an order.
     *
     * Two distinct financial paths depending on `received_by`:
     *
     *   - "company": cash went straight to the company. Posts an "in"
     *     movement to treasury_transactions immediately.
     *
     *   - "agent": an agent physically collected cash on the company's
     *     behalf. This does NOT touch the treasury yet — the company
     *     hasn't actually received the money. Instead it posts an "out"
     *     ledger line to agent_transactions (the agent now "owes" this
     *     amount to the company, i.e. holds it on the company's behalf).
     *     The money only reaches treasury later, when the agent remits it
     *     — see remit().
     *
     * In both cases, the parent order's paid/remaining amounts are
     * recalculated from the live sum of its payments.
     */
    public function store(StoreCustomerPaymentRequest $request): JsonResponse
    {
        $payment = DB::transaction(function () use ($request) {
            $agentId = $request->validated('agent_id');
            if ($request->user()->agent) {
                $agentId = $request->user()->agent->id;
            }

            /** @var CustomerPayment $payment */
            $payment = CustomerPayment::create([
                'order_id' => $request->validated('order_id'),
                'customer_id' => $request->validated('customer_id'),
                'amount' => $request->validated('amount'),
                'received_by' => $request->user()->id,
                'agent_id' => $agentId,
                'attachment' => $request->validated('attachment'),
                'payment_date' => $request->validated('payment_date'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            $order = Order::find($payment->order_id);
            $order?->recalculateBalance();

            $isAdminOrSuperAdmin = $request->user()->hasAnyRole(['admin', 'super-admin']);

            if ($payment->wasCollectedByAgent()) {
                $this->postAgentLedgerEntry($payment, $request->user()->id);
            } else {
                $this->postTreasuryMovement($payment, $request->user()->id);

                if ($isAdminOrSuperAdmin) {
                    $payment->update([
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ]);
                }
            }

            return $payment;
        });

        return response()->json([
            'message' => 'تم تسجيل دفعة العميل بنجاح',
            'data' => new CustomerPaymentResource($payment->load(['customer', 'agent', 'creator', 'generalTreasuryTransfer'])),
        ], 201);
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('view', $customerPayment);

        $customerPayment->load(['customer', 'agent', 'order', 'creator', 'generalTreasuryTransfer']);

        return response()->json([
            'data' => new CustomerPaymentResource($customerPayment),
        ]);
    }

    /**
     * Soft-delete a payment, reverse whichever ledger it landed in (agent
     * ledger or treasury), and re-sync the parent order's balance.
     *
     * A payment that has ALREADY been remitted (remittance_id set) cannot
     * be deleted directly — the remittance itself represents a real
     * transfer of cash that already happened and has its own treasury
     * entry; voiding the underlying payment at that point would require
     * voiding the remittance first to keep the ledger consistent.
     */
    public function destroy(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('delete', $customerPayment);

        if ($customerPayment->remittance_id !== null) {
            return response()->json([
                'message' => 'لا يمكن حذف دفعة تم تحويلها للخزينة بالفعل، يجب إلغاء عملية التحويل أولاً',
            ], 422);
        }

        DB::transaction(function () use ($customerPayment) {
            if ($customerPayment->wasCollectedByAgent()) {
                $this->reverseAgentLedgerEntry($customerPayment);
            } else {
                $this->postTreasuryReversal($customerPayment);
            }

            $orderId = $customerPayment->order_id;
            $customerPayment->delete();

            Order::find($orderId)?->recalculateBalance();
        });

        return response()->json(['message' => 'تم حذف الدفعة بنجاح']);
    }

    /**
     * Mark a batch of agent-collected payments as remitted: the agent has
     * now physically handed this cash over to the company. This is the
     * moment the money actually reaches the company treasury.
     *
     * Posts ONE agent_transactions "in" entry (the agent's debt clears)
     * and ONE treasury_transactions "in" entry (the company now actually
     * holds the cash), cross-linked via the circular FKs
     * (customer_payments.remittance_id <-> agent_transactions.transaction_id)
     * exactly as modelled in the migration.
     */
    public function remit(RemitAgentBalanceRequest $request,): JsonResponse
    {
        $agent = $request->user()->agent; // موجود حتمًا هنا بفضل authorize()
        $amount = (float) $request->input('amount');

        $outstandingPayments = CustomerPayment::query()
            ->where('agent_id', $agent->id)
            ->whereNull('remittance_id')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $availableBalance = (float) $outstandingPayments->sum('amount');

        if ($availableBalance <= 0) {
            return response()->json([
                'message' => 'لا يوجد رصيد لديك بحاجة إلى تحويل',
            ], 422);
        }

        if ($amount > $availableBalance) {
            return response()->json([
                'message' => "المبلغ المُدخل ({$amount}) أكبر من رصيدك المتاح للتحويل ({$availableBalance})",
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $agent, $amount, $outstandingPayments) {
            $date = $request->input('transaction_date', now()->toDateString());
            $userId = $request->user()->id;

            $agentPrevious = (float) (AgentTransaction::query()
                ->where('agent_id', $agent->id)
                ->latest('id')
                ->value('current_balence') ?? 0);
            $agentNew = $agentPrevious + $amount;

            $agentTransaction = AgentTransaction::create([
                'agent_id' => $agent->id,
                'direction' => AgentTransaction::DIRECTION_IN,
                'amount' => $amount,
                'previous_balence' => $agentPrevious,
                'current_balence' => $agentNew,
                'payment_id' => null,
                'transaction_id' => null,
                'transaction_date' => $date,
                'attachment' => $request->input('attachment'),
                'notes' => $request->input('notes', 'تحويل مبلغ من رصيد الوكيل للخزينة'),
                'created_by' => $userId,
            ]);

            $treasuryPrevious = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);

            $treasuryTransaction = TreasuryTransaction::create([
                'direction' => TreasuryTransaction::DIRECTION_IN,
                'amount' => $amount,
                'previous_balence' => $treasuryPrevious,
                'current_balence' => $treasuryPrevious,
                'source_type' => TreasuryTransaction::SOURCE_AGENT_REMITTANCE,
                'source_id' => $agentTransaction->id,
                'transaction_date' => $date,
                'status' => TreasuryTransaction::STATUS_PENDING,
                'notes' => 'تحويل رصيد من الوكيل: ' . $agent->name . ' - بانتظار اعتماد الإدارة',
                'created_by' => $userId,
            ]);

            $agentTransaction->update(['transaction_id' => $treasuryTransaction->id]);

            $remaining = $amount;
            $settledPayments = collect();

            foreach ($outstandingPayments as $payment) {
                if ((float) $payment->amount > $remaining + 0.0001) {
                    break;
                }

                $payment->update(['remittance_id' => $agentTransaction->id]);
                $settledPayments->push($payment);
                $remaining -= (float) $payment->amount;
            }

            return [
                'agent_transaction' => $agentTransaction->fresh(['agent', 'treasuryTransaction']),
                'settled_payments' => $settledPayments,
                'unallocated_remainder' => round($remaining, 2),
            ];
        });

        $message = "تم تحويل {$amount} من رصيدك للخزينة بنجاح";
        if ($result['settled_payments']->isNotEmpty()) {
            $message .= ' وتمت تسوية ' . $result['settled_payments']->count() . ' دفعة/دفعات بالكامل';
        }
        if ($result['unallocated_remainder'] > 0) {
            $message .= '، ويوجد مبلغ متبقٍ غير مخصص لدفعة معينة قدره ' . $result['unallocated_remainder'];
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'agent_transaction' => new AgentTransactionResource($result['agent_transaction']),
                'settled_payments' => CustomerPaymentResource::collection($result['settled_payments']),
                'unallocated_remainder' => $result['unallocated_remainder'],
            ],
        ]);
    }

    /**
     * Optional extra step: stage an already-received customer payment for
     * transfer into the general treasury, pending management approval.
     *
     * Only makes sense for payments whose money has actually reached the
     * (regular) treasury already:
     *   - received_by=company payments post an "in" instantly at store().
     *   - agent-collected payments only reach treasury once remitted.
     * A still-unremitted agent-collected payment has no treasury entry
     * yet to transfer, so it's rejected here.
     *
     * Creates a single "out" TreasuryTransaction with status=pending —
     * it does NOT touch previous_balence/current_balence yet (those stay
     * null), so it has zero effect on the real running balance until
     * approveTreasuryTransfer() confirms it.
     */
    public function transferToTreasury(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('view', $customerPayment);

        if ($customerPayment->general_treasury_transfer_status === 'approved') {
            return response()->json([
                'message' => 'تم تحويل هذه الدفعة واعتمادها في الخزينة العامة بالفعل',
            ], 422);
        }

        if ($customerPayment->generalTreasuryTransfer) {
            return response()->json([
                'message' => 'هذه الدفعة قيد التحويل للخزينة العامة بالفعل، بانتظار اعتماد الإدارة',
            ], 422);
        }

        $receiver = $customerPayment->receiver;
        $isDirectApproved = $receiver && $receiver->hasAnyRole(['admin', 'super-admin']);

        if ($isDirectApproved) {
            $transfer = DB::transaction(function () use ($request, $customerPayment) {
                $previousBalance = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
                $newBalance = $previousBalance + (float) $customerPayment->amount;

                $t = TreasuryTransaction::create([
                    'direction' => TreasuryTransaction::DIRECTION_OUT,
                    'amount' => $customerPayment->amount,
                    'previous_balence' => $previousBalance,
                    'current_balence' => $newBalance,
                    'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
                    'source_id' => $customerPayment->id,
                    'transaction_date' => now()->toDateString(),
                    'status' => TreasuryTransaction::STATUS_APPROVED,
                    'notes' => 'تحويل دفعة عميل رقم #' . $customerPayment->id . ' إلى الخزينة العامة - معتمد تلقائياً',
                    'created_by' => $request->user()->id,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                $customerPayment->update([
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                return $t;
            });

            return response()->json([
                'message' => 'تم تحويل الدفعة واعتمادها في الخزينة العامة بنجاح',
                'data' => new CustomerPaymentResource(
                    $customerPayment->fresh(['customer', 'agent', 'creator', 'generalTreasuryTransfer', 'approver'])
                ),
                'treasury_transfer_id' => $transfer->id,
            ], 201);
        }

        $treasurytransaction = TreasuryTransaction::latest('id')->first();
        // إنشاء عملية معلقة دون التأثير على رصيد الخزينة
        $transfer = TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_OUT,
            'amount' => $customerPayment->amount,

            // سيتم تعبئتهما عند اعتماد العملية
            'previous_balence' => $treasurytransaction?->previous_balence,
            'current_balence' => $treasurytransaction?->current_balence,

            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $customerPayment->id,
            'transaction_date' => now()->toDateString(),
            'status' => TreasuryTransaction::STATUS_PENDING,
            'notes' => 'تحويل دفعة عميل رقم #' . $customerPayment->id . ' إلى الخزينة العامة - بانتظار الاعتماد',
            'created_by' => $request->user()->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إرسال الدفعة للخزينة العامة، بانتظار اعتماد الإدارة',
            'data' => new CustomerPaymentResource(
                $customerPayment->fresh(['customer', 'agent', 'creator', 'generalTreasuryTransfer'])
            ),
            'treasury_transfer_id' => $transfer->id,
        ], 201);
    }

    /**
     * Management approves a pending general-treasury transfer. This is
     * the moment it actually posts against the real balance chain — the
     * previous/current balance columns (left null since transferToTreasury())
     * are computed now, against the latest APPROVED balance.
     */
    public function approveTreasuryTransfer(ApproveTreasuryTransferRequest $request, CustomerPayment $customerPayment): JsonResponse
    {
        $transfer = TreasuryTransaction::query()
            ->where('source_type', TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('source_id', $customerPayment->id)
            ->where('direction', TreasuryTransaction::DIRECTION_OUT)
            ->where('status', TreasuryTransaction::STATUS_PENDING)
            ->latest('id')
            ->first();

        if (! $transfer) {
            return response()->json([
                'message' => 'لا يوجد تحويل معلّق لهذه الدفعة إلى الخزينة العامة',
            ], 422);
        }

        DB::transaction(function () use ($request, $transfer, $customerPayment) {
            $previousBalance = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
            $newBalance = $previousBalance + (float) $transfer->amount;

            $transfer->update([
                'status' => TreasuryTransaction::STATUS_APPROVED,
                'previous_balence' => $previousBalance,
                'current_balence' => $newBalance,
                'transaction_date' => $request->input('approval_date', now()->toDateString()),
                'notes' => $request->input('notes', $transfer->notes),
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $customerPayment->update([
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'تم اعتماد تحويل الدفعة إلى الخزينة العامة بنجاح',
            'data' => new CustomerPaymentResource($customerPayment->fresh(['customer', 'agent', 'creator', 'generalTreasuryTransfer', 'approver'])),
        ]);
    }

    /**
     * Post the company-received "in" treasury movement for a
     * received_by=company payment.
     */
    protected function postTreasuryMovement(CustomerPayment $payment, int $userId): void
    {
        $previousBalance = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
        $newBalance = $previousBalance + (float) $payment->amount;

        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'status' => TreasuryTransaction::STATUS_APPROVED,
            'notes' => 'دفعة من العميل: ' . ($payment->customer?->name ?? $payment->customer_id),
            'created_by' => $userId,
        ]);
    }

    /**
     * Post the agent's "out" ledger entry for a received_by=agent
     * payment: the agent now holds this cash on the company's behalf
     * (a liability from the agent to the company until remitted).
     */
    protected function postAgentLedgerEntry(CustomerPayment $payment, int $userId): void
    {
        $previousBalance = (float) (AgentTransaction::query()
            ->where('agent_id', $payment->agent_id)
            ->latest('id')
            ->value('current_balence') ?? 0);
        $newBalance = $previousBalance - (float) $payment->amount;

        AgentTransaction::create([
            'agent_id' => $payment->agent_id,
            'direction' => AgentTransaction::DIRECTION_OUT,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'payment_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'notes' => 'قبض دفعة عميل رقم #' . $payment->id . ' لم تُحوَّل للخزينة بعد',
            'created_by' => $userId,
        ]);
    }

    /**
     * Reverse the treasury "in" movement when a company-received payment
     * is deleted, via a new "out" entry (ledger stays append-only).
     */
    protected function postTreasuryReversal(CustomerPayment $payment): void
    {
        $previousBalance = (float) (TreasuryTransaction::query()->approved()->latest('id')->value('current_balence') ?? 0);
        $newBalance = $previousBalance - (float) $payment->amount;

        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_OUT,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
            'transaction_date' => now()->toDateString(),
            'status' => TreasuryTransaction::STATUS_APPROVED,
            'notes' => 'إلغاء دفعة عميل محذوفة رقم #' . $payment->id,
            'created_by' => $payment->created_by,
        ]);
    }

    /**
     * Reverse the agent's "out" ledger entry when an un-remitted
     * agent-collected payment is deleted, via a new "in" entry clearing
     * the agent's liability for that amount (ledger stays append-only).
     */
    protected function reverseAgentLedgerEntry(CustomerPayment $payment): void
    {
        $previousBalance = (float) (AgentTransaction::query()
            ->where('agent_id', $payment->agent_id)
            ->latest('id')
            ->value('current_balence') ?? 0);
        $newBalance = $previousBalance + (float) $payment->amount;

        AgentTransaction::create([
            'agent_id' => $payment->agent_id,
            'direction' => AgentTransaction::DIRECTION_IN,
            'amount' => $payment->amount,
            'previous_balence' => $previousBalance,
            'current_balence' => $newBalance,
            'payment_id' => $payment->id,
            'transaction_date' => now()->toDateString(),
            'notes' => 'إلغاء قبض دفعة عميل محذوفة رقم #' . $payment->id,
            'created_by' => $payment->created_by,
        ]);
    }
}
