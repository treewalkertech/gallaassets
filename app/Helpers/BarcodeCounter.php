<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class BarcodeCounter
{
    public static function next(?int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {

            $row = DB::table('barcode_counters')
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::table('barcode_counters')->insert([
                    'company_id' => $companyId,
                    'current' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $next = 1;
            } else {
                $next = $row->current + 1;

                DB::table('barcode_counters')
                    ->where('id', $row->id)
                    ->update([
                        'current' => $next,
                        'updated_at' => now(),
                    ]);
            }

            // Always 2-digit serial
            return str_pad($next, 2, '0', STR_PAD_LEFT);
        });
    }
}
