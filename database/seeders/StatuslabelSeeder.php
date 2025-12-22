<?php

namespace Database\Seeders;

use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Database\Seeder;

class StatuslabelSeeder extends Seeder
{
    public function run()
    {
        Statuslabel::truncate();

        $admin = User::where('permissions->superuser', '1')->first()
            ?? User::factory()->firstAdmin()->create();

        /**
         * -----------------------------------------
         * DEFAULT / SYSTEM STATUSES
         * -----------------------------------------
         */

        Statuslabel::factory()->rtd()->create([
            'name'       => 'Ready to Deploy',
            'color'      => '#2ecc71', // green
            'created_by'=> $admin->id,
        ]);

        Statuslabel::factory()->pending()->create([
            'name'       => 'Pending',
            'color'      => '#f1c40f', // yellow
            'created_by'=> $admin->id,
        ]);

        Statuslabel::factory()->archived()->create([
            'name'       => 'Archived',
            'color'      => '#7f8c8d', // grey
            'created_by'=> $admin->id,
        ]);

        Statuslabel::factory()->outForDiagnostics()->create([
            'color'      => '#3498db', // blue
            'created_by'=> $admin->id,
        ]);

        Statuslabel::factory()->outForRepair()->create([
            'color'      => '#e67e22', // orange
            'created_by'=> $admin->id,
        ]);

        Statuslabel::factory()->broken()->create([
            'color'      => '#e74c3c', // red
            'created_by'=> $admin->id,
        ]);

        Statuslabel::factory()->lost()->create([
            'color'      => '#c0392b', // dark red
            'created_by'=> $admin->id,
        ]);

        /**
         * -----------------------------------------
         * SERVICE DESK PLUS (SDP) STATES
         * -----------------------------------------
         */

        // In Store → Deployable
        Statuslabel::factory()->create([
            'name'       => 'In Store',
            'deployable' => 1,
            'pending'    => 0,
            'archived'   => 0,
            'color'      => '#27ae60', // green
            'created_by'=> $admin->id,
        ]);

        // In Use → Deployable
        Statuslabel::factory()->create([
            'name'       => 'In Use',
            'deployable' => 1,
            'pending'    => 0,
            'archived'   => 0,
            'color'      => '#2980b9', // blue
            'created_by'=> $admin->id,
        ]);

        // To Be Returned → Pending
        Statuslabel::factory()->create([
            'name'       => 'To Be Returned',
            'deployable' => 0,
            'pending'    => 1,
            'archived'   => 0,
            'color'      => '#f39c12', // amber
            'created_by'=> $admin->id,
        ]);

        // In Repair → Pending
        Statuslabel::factory()->create([
            'name'       => 'In Repair',
            'deployable' => 0,
            'pending'    => 1,
            'archived'   => 0,
            'color'      => '#d35400', // dark orange
            'created_by'=> $admin->id,
        ]);

        // Expired → Archived
        Statuslabel::factory()->create([
            'name'       => 'Expired',
            'deployable' => 0,
            'pending'    => 0,
            'archived'   => 1,
            'color'      => '#95a5a6', // light grey
            'created_by'=> $admin->id,
        ]);

        // Disposed → Archived
        Statuslabel::factory()->create([
            'name'       => 'Disposed',
            'deployable' => 0,
            'pending'    => 0,
            'archived'   => 1,
            'color'      => '#aa3399', // ✅ your requested color
            'created_by'=> $admin->id,
        ]);
    }
}
