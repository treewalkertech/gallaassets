<?php

namespace App\Policies;

use App\Models\Grn;
use App\Models\User;

class GrnPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'grn';
    }

    /**
     * Posting a GRN is what actually creates Assets / bumps consumable qty
     * / adds license seats -- gated by its own permission rather than
     * riding along on 'edit', same reasoning as PurchaseOrder's approve().
     */
    public function post(User $user, Grn $item = null)
    {
        return $user->hasAccess('grn.post');
    }
}
