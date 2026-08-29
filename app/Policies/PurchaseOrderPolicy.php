<?php

namespace App\Policies;

class PurchaseOrderPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'purchase-orders';
    }
}
