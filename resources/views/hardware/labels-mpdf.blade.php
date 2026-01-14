@php
    $barcodeWidthMm  = $settings->barcode_width_in * 25.4;
    $barcodeHeightMm = $settings->barcode_height_in * 25.4;
@endphp
<style>
    .pull-left{
        style="font-size: {{ $settings->labels_fontsize }}pt;"
    }
</style>

<div style=" margin-top: 20px;
    font-family: Arial, Helvetica, sans-serif;

    width: {{ $labelWidthMm }}mm;
    text-align: center;
">

        @if ($settings->label_logo)
                <div class="label-logo">
                    <img class="label-logo" src="{{ Storage::disk('public')->url('').e($snipeSettings->label_logo) }}">
                </div>
        @endif


        @if($settings->labels_display_barcode == '1')
            <div >
                <img src="{{ $barcodeBase64 }}"
                    style=" margin-top: 10px;
                        width: {{ $barcodeWidthMm }}mm;
                        height: {{ $barcodeHeightMm }}mm;
                    ">
            </div>
        @endif

    @if($settings->labels_display_tag == '1')
        <div style="font-size: {{ $settings->labels_fontsize }}pt;">
            {{ $asset->asset_tag }}
        </div>
    @endif

    @if($settings->labels_display_company_name == '1' && $asset->company)
        <div style="font-size: {{ $settings->labels_fontsize - 1 }}pt;">
            {{ $asset->company->name }}
        </div>
    @endif

    @if($settings->labels_display_model == '1' && $asset->model)
        <div style="font-size: {{ $settings->labels_fontsize - 1 }}pt;">
            {{ $asset->model->name }}
        </div>
    @endif
    
        @if ($settings->qr_text!='')
            <div class="pull-left">
                <strong>{{ $settings->qr_text }}</strong>
                <br>
            </div>
        @endif
        
        @if (($settings->labels_display_name=='1') && ($asset->name!=''))
            <div class="pull-left">
                    {{ $asset->name }}
            </div>
        @endif
       
        @if (($settings->labels_display_serial=='1') && ($asset->serial!=''))
            <div class="pull-left">
                 {{ $asset->serial }}
            </div>
        @endif
        @if (($settings->labels_display_barcode == '1')&& ($asset->_snipeit_barcode_2))
            <div class="pull-left">
                    {{ $asset->_snipeit_barcode_2 }}
            </div>
        @endif

        

</div>
