<?php

namespace App\Policies;

class ItemPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'items';
    }
}
