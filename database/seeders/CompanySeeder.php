<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompanySeeder extends Seeder
{
    public function run()
    {
        Log::debug('Seed companies');

        Company::truncate();

        // 1️⃣ Create company FIRST
        $company = Company::factory()->create([
            'name' => 'Treewalker Technology',
            'image' => 'favicon.png', // ✅ THIS WAS MISSING
        ]);

        // 2️⃣ Source & destination
        $src = public_path('img/demo/companies');
        $dst = 'companies/';

        // 3️⃣ Delete old files
        $files = Storage::disk('public')->files($dst);
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }

        // 4️⃣ Copy demo images
        $add_files = glob($src . '/*.*');
        foreach ($add_files as $add_file) {

            $filename = basename($add_file);

            try {
                Storage::disk('public')->put(
                    $dst . $filename,
                    file_get_contents($add_file)
                );
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }
}
