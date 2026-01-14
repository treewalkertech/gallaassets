<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Labels</title>

</head>
<body>

<?php
$settings->labels_width = $settings->labels_width - $settings->labels_display_sgutter;
$settings->labels_height = $settings->labels_height - $settings->labels_display_bgutter;
// Leave space on bottom for 1D barcode if necessary
$qr_size = ($settings->alt_barcode_enabled=='1') && ($settings->label2_1d_type!='') ? $settings->labels_height - .3 : $settings->labels_height - 0.1;
?>

<style>
    body {
        font-family: arial, helvetica, sans-serif;
        width: {{ $settings->labels_pagewidth }}in;
        height: {{ $settings->labels_pageheight }}in;
        margin: {{ $settings->labels_pmargin_top }}in {{ $settings->labels_pmargin_right }}in {{ $settings->labels_pmargin_bottom }}in {{ $settings->labels_pmargin_left }}in;
        font-size: {{ $settings->labels_fontsize }}pt;
    }
    .label {
        width: {{ $settings->labels_width }}in;
        height: {{ $settings->labels_height }}in;
        padding: 0.8in;
        margin-right: {{ $settings->labels_display_sgutter }}in; /* the gutter */
        margin-bottom: {{ $settings->labels_display_bgutter }}in;
        display: inline-block;
        overflow: hidden;
    }
    .page-break  {
        page-break-after:always;
    }
    div.qr_img {
        width: {{ $qr_size }}in;
        height: {{ $qr_size }}in;

        float: left;
        display: inline-flex;
        padding-right: .15in;
    }
    img.qr_img {

        width: 120.79%;
        height: 120.79%;
        margin-top: -6.9%;
        margin-left: -6.9%;
        padding-bottom: .04in;
    }
    img.barcode {
        display:block;
        margin-top:{{$settings->qr_code=='1' ? '-15px' : '-7px;'}};
        width: 100%;
    }
    div.label-logo {
        float: right;
        display: inline-block;
        /* min-width:4in; */
    }
    img.label-logo {
        height: 1in;
    }
    .qr_text {
        width: {{ $settings->labels_width }}in;
        height: {{ $settings->labels_height }}in;
        padding-top: {{$settings->labels_display_bgutter}}in;
        font-family: arial, helvetica, sans-serif;
        font-size: {{$settings->labels_fontsize}}pt;
        padding-right: .0001in;
        overflow: hidden !important;
        display: inline;
        word-wrap: break-word;
        word-break: break-all;
        text-align: center;
    }
    div.barcode_container {

        width: 100%;
        display: inline;
        overflow: hidden;
    }
    .next-padding {
        margin: {{ $settings->labels_pmargin_top }}in {{ $settings->labels_pmargin_right }}in {{ $settings->labels_pmargin_bottom }}in {{ $settings->labels_pmargin_left }}in;
    }
    @media print {
        .noprint {
            display: none !important;
        }
        .next-padding {
            margin: {{ $settings->labels_pmargin_top }}in {{ $settings->labels_pmargin_right }}in {{ $settings->labels_pmargin_bottom }}in {{ $settings->labels_pmargin_left }}in;
            font-size: 0;
        }
    }
    @media screen {
        .label {
            outline: .01in rgb(111 111 111) solid; /* outline doesn't occupy space like border does */
            border-radius: 40px;
        }
        .noprint {
            font-size: 13px;
            padding-bottom: 15px;
        }
    }
    @if ($snipeSettings->custom_css)
        {!! $snipeSettings->show_custom_css() !!}
    @endif
    
 @keyframes scan {
        0% {
            left: -100%;
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            left: 100%;
            opacity: 0;
        }
    }
    
    @keyframes moveBars {
        0% {
            background-position: 0 0;
        }
        100% {
            background-position: 50px 0;
        }
    }
    
    @keyframes pulseGlow {
        0%, 100% {
            filter: drop-shadow(0 0 4px rgba(0,255,204,0.5));
        }
        50% {
            filter: drop-shadow(0 0 8px rgba(0,255,204,0.8));
        }
    }
    
    .cool-barcode-btn:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 15px 30px rgba(0,0,0,0.4),
                    0 0 0 2px rgba(0,255,204,0.2) inset;
        letter-spacing: 2px;
    }
    
    .cool-barcode-btn:hover .neon-glow {
        opacity: 1;
    }
    
    .cool-barcode-btn:hover .printer-icon {
        transform: translateY(-2px);
        animation: pulseGlow 1s infinite;
    }
    
    .cool-barcode-btn:active {
        transform: translateY(1px) scale(0.98);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3),
                    0 0 0 2px rgba(0,255,204,0.3) inset;
    }
    
    .cool-barcode-btn:hover .scan-line {
        animation-duration: 1.5s;
    }
</style>

@foreach ($assets as $asset)
    <?php $count++; ?>
    <div class="label">

        @if ($settings->qr_code=='1')
            <div class="qr_img">
                <img src="{{ config('app.url') }}/hardware/{{ $asset->id }}/qr_code" class="qr_img">
            </div>
        @endif

        
        @if ((($settings->alt_barcode_enabled=='1') && $settings->label2_1d_type!=''))
            <div class="barcode_container">
                <img src="{{ config('app.url') }}/hardware/{{ $asset->id }}/barcode" class="barcode">
            </div>
        @endif


        <div class="qr_text">
            @if ($settings->label_logo)
                <div class="label-logo">
                    <img class="label-logo" src="{{ Storage::disk('public')->url('').e($snipeSettings->label_logo) }}">
                </div>
            @endif
            @if ($settings->qr_text!='')
                <div class="pull-left">
                    <strong>{{ $settings->qr_text }}</strong>
                    <br>
                </div>
            @endif
            @if (($settings->labels_display_company_name=='1') && ($asset->company))
                <div class="pull-left">
                    C: {{ $asset->company->name }}
                </div>
            @endif
            @if (($settings->labels_display_name=='1') && ($asset->name!=''))
                <div class="pull-left">
                    N: {{ $asset->name }}
                </div>
            @endif
            @if (($settings->labels_display_tag=='1') && ($asset->asset_tag!=''))
                <div class="pull-left">
                    T: {{ $asset->asset_tag }}
                </div>
            @endif
            @if (($settings->labels_display_serial=='1') && ($asset->serial!=''))
                <div class="pull-left">
                    S: {{ $asset->serial }}
                </div>
            @endif
            @if (($settings->labels_display_barcode == '1')&& ($asset->_snipeit_barcode_2))
                <div class="pull-left">
                     {{ $asset->_snipeit_barcode_2 }}
                </div>
            @endif

            @if (($settings->labels_display_model=='1') && ($asset->model->name!=''))
                <div class="pull-left">
                    M: {{ $asset->model->name }} {{ $asset->model->model_number }}
                </div>
            @endif

        </div>



    </div>

    @if (($count % $settings->labels_per_page == 0) && $count!=count($assets))
    <div class="page-break"></div>
    <div class="next-padding">&nbsp;</div>
    @endif

@endforeach
<div class="noprint" style="margin-top: 20px;">
    <form method="POST"
          action="{{ route('labels.print.barcodes') }}"
          target="_blank">
        @csrf
        @foreach ($assets as $asset)
            <input type="hidden" name="asset_ids[]" value="{{ $asset->id }}">
        @endforeach

        <button type="submit" class="cool-barcode-btn" 
                style="background: linear-gradient(135deg, #0a0a0a 0%, #222 100%);
                       border: none;
                       border-radius: 8px;
                       color: white;
                       cursor: pointer;
                       font-family: 'Segoe UI', 'SF Pro Display', -apple-system, sans-serif;
                       font-size: 12px;
                       font-weight: 600;
                       padding: 15px 20px;
                       position: relative;
                       overflow: hidden;
                       display: inline-flex;
                       align-items: center;
                       gap: 15px;
                       text-transform: uppercase;
                       letter-spacing: 1px;
                       box-shadow: 0 8px 25px rgba(0,0,0,0.3),
                                   0 0 0 2px rgba(255,255,255,0.1) inset;
                       transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
            
            <!-- Animated scanning line -->
            <div class="scan-line" 
                 style="position: absolute;
                        top: 0;
                        left: -100%;
                        width: 100%;
                        height: 2px;
                        background: linear-gradient(90deg, transparent, #00ffcc, transparent);
                        filter: drop-shadow(0 0 6px #00ffcc);
                        animation: scan 2s linear infinite;
                        animation-delay: 0.5s;">
            </div>
            
            <!-- Pulsing barcode pattern background -->
            <div class="barcode-bg" 
                 style="position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        opacity: 0.15;
                        background-image: repeating-linear-gradient(
                            90deg,
                            transparent,
                            transparent 2px,
                            #00ffcc 2px,
                            #00ffcc 4px,
                            transparent 4px,
                            transparent 10px
                        );
                        background-size: 50px 100%;
                        animation: moveBars 8s linear infinite;
                        filter: blur(0.5px);">
            </div>
            
            <!-- Neon glow effect -->
            <div class="neon-glow" 
                 style="position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        opacity: 0;
                        border-radius: 8px;
                        background: radial-gradient(circle at center, rgba(0,255,204,0.3) 0%, transparent 70%);
                        transition: opacity 0.3s ease;">
            </div>
            
            <!-- Printer icon with glow -->
            <svg class="printer-icon" 
                 style="width: 20px; 
                        height: 20px; 
                        filter: drop-shadow(0 0 4px rgba(0,255,204,0.5));
                        position: relative;
                        z-index: 2;
                        transition: transform 0.3s ease;"
                 xmlns="http://www.w3.org/2000/svg" 
                 viewBox="0 0 24 24" 
                 fill="#00ffcc">
                <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
            </svg>
            
            <!-- Text with glow -->
            <span class="btn-text" 
                  style="position: relative;
                         z-index: 2;
                         text-shadow: 0 0 10px rgba(0,255,204,0.5);
                         letter-spacing: 1.5px;">
                GENERATE BARCODE PDF
            </span>
            
            <!-- Corner accents -->
            <div style="position: absolute;
                        top: 8px;
                        left: 8px;
                        width: 12px;
                        height: 12px;
                        border-top: 2px solid #00ffcc;
                        border-left: 2px solid #00ffcc;
                        opacity: 0.7;">
            </div>
            <div style="position: absolute;
                        bottom: 8px;
                        right: 8px;
                        width: 12px;
                        height: 12px;
                        border-bottom: 2px solid #00ffcc;
                        border-right: 2px solid #00ffcc;
                        opacity: 0.7;">
            </div>
            
        </button>
    </form>
</div>

</body>
</html>
