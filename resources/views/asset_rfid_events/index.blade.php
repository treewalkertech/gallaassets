@extends('layouts/default')

{{-- Page title --}}
@section('title')
    Asset RFID Status
    @parent
@stop

{{-- Header actions --}}
{{-- @section('header_right')
    <a href="{{ route('asset-rfid-scan-events.index') }}" class="btn btn-default pull-right">
        <i class="fas fa-sync-alt"></i>
        Refresh
    </a>
@stop --}}


@section('header_right')
    @php
        $rfidRoute = Route::has('asset-rfid-scan-events.index') 
            ? route('asset-rfid-scan-events.index') 
            : '#';
    @endphp
    <a href="{{ $rfidRoute }}" class="btn btn-default pull-right">
        <i class="fas fa-sync-alt"></i>
        Refresh
    </a>
@stop

{{-- Page content --}}
@section('content')

    {{-- Summary cards --}}
    <div class="row">

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>
                        {{ number_format($summary['total_tags'] ?? ($summary['total_scans'] ?? 0)) }}
                    </h3>

                    <p>Total RFID Tags</p>
                </div>

                <div class="icon">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="small-box bg-blue">
                <div class="inner">
                    <h3>
                        {{ number_format($summary['unique_assets'] ?? 0) }}
                    </h3>

                    <p>Unique Assets</p>
                </div>

                <div class="icon">
                    <i class="fas fa-laptop"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>
                        {{ number_format($summary['scanned_today'] ?? 0) }}
                    </h3>

                    <p>Scanned Today</p>
                </div>

                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3 style="font-size: 22px;">
                        @if ($latestScan && $latestScan->scanned_at)
                            {{ $latestScan->scanned_at->format('h:i:s A') }}
                        @else
                            -
                        @endif
                    </h3>

                    <p>Latest Scan Time</p>
                </div>

                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>

    </div>

    {{-- Latest RFID scan --}}
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">

                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-broadcast-tower"></i>
                        Latest RFID Scan
                    </h3>
                </div>

                <div class="box-body">

                    @if ($latestScan)

                        <div class="row">

                            <div class="col-md-2 col-sm-6">
                                <strong>Last Scanned</strong>

                                <p>
                                    {{ $latestScan->scanned_at ? $latestScan->scanned_at->format('d-m-Y h:i:s A') : '-' }}
                                </p>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <strong>Asset</strong>

                                <p>
                                    {{ $latestScan->asset_name ?: '-' }}

                                    @if ($latestScan->asset_id)
                                        <br>

                                        <small>
                                            Asset ID: {{ $latestScan->asset_id }}
                                        </small>
                                    @endif
                                </p>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <strong>Asset Tag</strong>

                                <p>
                                    {{ $latestScan->asset_tag ?: '-' }}
                                </p>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <strong>RFID EPC</strong>

                                <p>
                                    <code>{{ $latestScan->rfid_epc }}</code>
                                </p>
                            </div>

                            <div class="col-md-1 col-sm-6">
                                <strong>Reader</strong>

                                <p>
                                    {{ $latestScan->reader_code ?: '-' }}
                                </p>
                            </div>

                            <div class="col-md-1 col-sm-6">
                                <strong>Gate</strong>

                                <p>
                                    {{ $latestScan->antenna_no ?: '-' }}
                                </p>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <strong>Status</strong>

                                <p>
                                    <span class="label label-success">
                                        {{ $latestScan->scan_result ?: 'MAPPED' }}
                                    </span>
                                </p>
                            </div>

                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No mapped RFID tags are available yet.
                        </div>

                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">

                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-filter"></i>
                        Filters
                    </h3>
                </div>

                <div class="box-body">
                    @php
                        $formAction = \App\Helpers\Helper::safeRoute('asset-rfid-scan-events.index');
                    @endphp

                    <form method="GET" action="{{ $formAction }}">

                        <div class="row">

                            {{-- <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="date_from">
                                        Date From
                                    </label>

                                    <input type="date" name="date_from" id="date_from" class="form-control"
                                        value="{{ request('date_from') }}">
                                </div>
                            </div> --}}

                            {{-- <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="date_to">
                                        Date To
                                    </label>

                                    <input type="date" name="date_to" id="date_to" class="form-control"
                                        value="{{ request('date_to') }}">
                                </div>
                            </div> --}}

                            {{-- <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="asset_id">
                                        Asset ID
                                    </label>

                                    <input type="number" name="asset_id" id="asset_id" class="form-control"
                                        value="{{ request('asset_id') }}" placeholder="Asset ID">
                                </div>
                            </div> --}}

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="asset_name">
                                        Asset Name
                                    </label>

                                    <input type="text" name="asset_name" id="asset_name" class="form-control"
                                        value="{{ request('asset_name') }}" placeholder="Laptop, mobile, charger...">
                                </div>
                            </div>
                            {{-- 
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="asset_tag">
                                        Asset Tag
                                    </label>

                                    <input type="text" name="asset_tag" id="asset_tag" class="form-control"
                                        value="{{ request('asset_tag') }}" placeholder="Asset tag">
                                </div>
                            </div> --}}
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="rfid_epc">
                                        RFID EPC
                                    </label>

                                    <input type="text" name="rfid_epc" id="rfid_epc" class="form-control"
                                        value="{{ request('rfid_epc') }}" placeholder="RFID EPC">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="serial_number">
                                        Serial Number
                                    </label>

                                    <input type="text" name="serial_number" id="serial_number" class="form-control"
                                        value="{{ request('serial_number') }}" placeholder="Device serial number">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="location_id">
                                        Location
                                    </label>

                                    <select name="location_id" id="location_id" class="form-control">
                                        <option value="">
                                            All Locations
                                        </option>

                                        @foreach ($locations as $locationId => $locationName)
                                            <option value="{{ $locationId }}"
                                                {{ (string) request('location_id') === (string) $locationId ? 'selected' : '' }}>
                                                {{ $locationName }}
                                            </option>
                                        @endforeach

                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">



                            {{-- <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="reader_code">
                                        Reader Code
                                    </label>

                                    <input type="text" name="reader_code" id="reader_code" class="form-control"
                                        value="{{ request('reader_code') }}" placeholder="Reader code">
                                </div>
                            </div> --}}

                            {{-- <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="gate_no">
                                        Gate No
                                    </label>

                                    <input type="text" name="gate_no" id="gate_no" class="form-control"
                                        value="{{ request('gate_no') }}" placeholder="Gate number">
                                </div>
                            </div> --}}





                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="per_page">
                                        Rows Per Page
                                    </label>

                                    <select name="per_page" id="per_page" class="form-control">
                                        @foreach ([10, 25, 50, 100] as $pageSize)
                                            <option value="{{ $pageSize }}"
                                                {{ (int) request('per_page', 50) === $pageSize ? 'selected' : '' }}>
                                                {{ $pageSize }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-10 col-sm-12">
                                <div class="form-group">
                                    <label>&nbsp;</label>

                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                            Apply Filters
                                        </button>

                                        <a href="{{ route('asset-rfid-scan-events.index') }}" class="btn btn-default">
                                            <i class="fas fa-times"></i>
                                            Clear Filters
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    {{-- Current unique RFID records --}}
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">

                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-list"></i>
                        Current RFID Tag Status
                    </h3>

                    <div class="box-tools pull-right">
                        <span class="label label-default">
                            {{ number_format($scanEvents->total()) }}
                            tags
                        </span>
                    </div>
                </div>

                <div class="box-body table-responsive">

                    <table class="table table-striped table-bordered table-hover">

                        <thead>
                            <tr>
                                <th>Last Scanned</th>
                                {{-- <th>Asset ID</th> --}}
                                <th>Asset Name</th>
                                <th>Asset Tag</th>
                                <th>RFID EPC</th>
                                <th>Serial Number</th>
                                {{-- <th>Reader Code</th> --}}
                                {{-- <th>Gate No</th> --}}
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            @forelse ($scanEvents as $scanEvent)
                                <tr>
                                    <td style="white-space: nowrap;">
                                        {{ $scanEvent->scanned_at ? $scanEvent->scanned_at->format('d-m-Y h:i:s A') : '-' }}
                                    </td>

                                    {{-- <td>
                                            {{ $scanEvent->asset_id ?: '-' }}
                                        </td> --}}

                                    <td>
                                        {{ $scanEvent->asset_name ?: '-' }}
                                    </td>

                                    <td>
                                        {{ $scanEvent->asset_tag ?: '-' }}
                                    </td>

                                    <td>
                                        <code>
                                            {{ $scanEvent->rfid_epc }}
                                        </code>
                                    </td>

                                    <td>
                                        {{ $scanEvent->serial_number ?: '-' }}
                                    </td>

                                    {{-- <td>
                                        <td>
                                            {{ $scanEvent->reader_code ?: '-' }}
                                        </td>
                                    </td> --}}

                                    {{-- <td>
                                        {{ $scanEvent->antenna_no ?: '-' }}
                                    </td> --}}

                                    <td>
                                        {{ $scanEvent->location_id
                                            ? $locations->get($scanEvent->location_id, 'Location ID: ' . $scanEvent->location_id)
                                            : '-' }}
                                    </td>

                                    <td>
                                        <span class="label label-success">
                                            {{ $scanEvent->scan_result ?: 'MAPPED' }}
                                        </span>
                                    </td>
                                </tr>

                            @empty

                                <tr>
                                    <td colspan="10" class="text-center text-muted">
                                        No mapped RFID tags found.
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                </div>

                @if ($scanEvents->hasPages())
                    <div class="box-footer clearfix">

                        <div class="pull-left">
                            Showing
                            {{ $scanEvents->firstItem() }}
                            to
                            {{ $scanEvents->lastItem() }}
                            of
                            {{ $scanEvents->total() }}
                            tags
                        </div>

                        <div class="pull-right">
                            {{ $scanEvents->links() }}
                        </div>

                    </div>
                @endif

            </div>
        </div>
    </div>

@stop
@section('moar_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let userIsFiltering = false;
            
            @php
                $rfidRoute = Route::has('asset-rfid-scan-events.index') 
                    ? route('asset-rfid-scan-events.index') 
                    : 'window.location.href';
            @endphp
            
            let rfidIndexUrl = "{{ $rfidRoute }}";

            const filterForm = document.querySelector('form[action="' + rfidIndexUrl + '"]');

            if (filterForm) {
                filterForm.addEventListener('input', function() {
                    userIsFiltering = true;
                });

                filterForm.addEventListener('change', function() {
                    userIsFiltering = true;
                });
            }

            setInterval(function() {
                if (!userIsFiltering) {
                    if (rfidIndexUrl === '#') {
                        window.location.reload();
                    } else {
                        window.location.href = rfidIndexUrl;
                    }
                }
            }, 10000);
        });
    </script>
@stop