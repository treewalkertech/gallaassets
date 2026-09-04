<?php

namespace App\Services;

use Mpdf\Mpdf;

class MpdfLabelService
{
    public function make()
    {
        return new Mpdf([
            'mode' => 'utf-8',
            'format' => [50, 30], // label size (mm)
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font_size' => 9,
        ]);
    }
}
