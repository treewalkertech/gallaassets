<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'purchase-orders';
    }

    /**
     * Approve/reject share one rule: the user needs the approve permission,
     * and if this specific PO has a designated approver, only that person
     * may act on it (an admin can always act, via SnipePermissionsPolicy's
     * before() hook, which runs before this method is ever reached).
     */
    public function approve(User $user, PurchaseOrder $item = null)
    {
        if (!$user->hasAccess('purchase-orders.approve')) {
            return false;
        }

        if ($item && $item->approver_id) {
            return (int) $item->approver_id === (int) $user->id;
        }

        return true;
    }

    public function reject(User $user, PurchaseOrder $item = null)
    {
        return $this->approve($user, $item);
    }
}
