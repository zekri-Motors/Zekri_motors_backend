<?php

namespace App\Policies;

use App\Models\AgentTransaction;
use App\Models\User;

class AgentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('agent_transactions.view') || $user->can('agent_transactions.view_own');
    }

    /**
     * Per-record visibility:
     *   - agent_transactions.view      -> admin/super-admin, any entry.
     *   - agent_transactions.view_own  -> the agent this entry belongs to.
     */
    public function view(User $user, AgentTransaction $agentTransaction): bool
    {
        if ($user->agent) {
            return $user->agent->id === $agentTransaction->agent_id;
        }

        if ($user->can('agent_transactions.view')) {
            return true;
        }

        if ($user->can('agent_transactions.view_own')) {
            return $user->agent?->id === $agentTransaction->agent_id;
        }

        return false;
    }

    /**
     * Creating manual ledger entries (commissions, manual corrections) is
     * an admin-only action — an agent never writes their own ledger.
     */
    public function create(User $user): bool
    {
        return $user->can('agent_transactions.create');
    }

    public function update(User $user, AgentTransaction $agentTransaction): bool
    {
        return $user->can('agent_transactions.update');
    }

    public function delete(User $user, AgentTransaction $agentTransaction): bool
    {
        return $user->can('agent_transactions.delete');
    }
}
