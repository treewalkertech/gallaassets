<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Mpdf\Mpdf;
use Picqer\Barcode\BarcodeGeneratorPNG;
use App\Models\Setting;

class LabelPrintController extends Controller
{
    public function print(Request $request)
{
    $assets = Asset::with('company', 'model')
        ->whereIn('id', $request->asset_ids ?? [])
        ->get();

    $settings = Setting::getSettings();

    // LABEL SIZE (inch → mm)
    $labelWidthMm  = $settings->labels_width * 25.4;
    $labelHeightMm = $settings->labels_height * 25.4;

    $mpdf = new Mpdf([
        'format' => [$labelWidthMm, $labelHeightMm],
        'margin_left'   => 0.2,
        'margin_right'  => 0.2,
        'margin_top'    => 0.2,
        'margin_bottom' => 0.2,
    ]);

    // BARCODE SIZE (inch → px)
    $barcodeWidthPx  = $settings->barcode_width_in * 96;
    $barcodeHeightPx = $settings->barcode_height_in * 96;
    $barWidthPx      = max(1, $settings->barcode_bar_width_in * 96);

    $generator = new BarcodeGeneratorPNG();

    foreach ($assets as $asset) {

        $barcodePng = $generator->getBarcode(
            $asset->_snipeit_barcode_2,
            $generator::TYPE_CODE_128,
            $barWidthPx,
            $barcodeHeightPx
        );

        $barcodeBase64 = 'data:image/png;base64,' . base64_encode($barcodePng);

        $html = view('hardware.labels-mpdf', [
            'asset' => $asset,
            'settings' => $settings,
            'barcodeBase64' => $barcodeBase64,
            'labelWidthMm' => $labelWidthMm,
        ])->render();

        $mpdf->AddPage();
        $mpdf->WriteHTML($html);
    }

    return response(
        $mpdf->Output('asset-barcodes.pdf', 'S'),
        200,
        ['Content-Type' => 'application/pdf']
    );
}
}
