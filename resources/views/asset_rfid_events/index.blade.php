@extends('layouts/default')

{{-- Page title --}}
@section('title')
    Asset RFID Status
    @parent
@stop

{{-- =============================================================
HEADER ACTIONS
============================================================= --}}
@section('header_right')

    <div class="btn-group pull-right">

        {{-- Refresh --}}
        <a href="{{ route('asset-rfid-scan-events.index') }}" class="btn btn-default">

            <i class="fas fa-sync-alt"></i>
            Refresh

        </a>

        {{-- Clear RFID Events --}}
        <button type="button" class="btn btn-danger" id="clearRfidEvents">

            <i class="fas fa-trash-alt"></i>
            Clear Events

        </button>

    </div>

@stop


{{-- =============================================================
PAGE CONTENT
============================================================= --}}
@section('content')


    {{-- =============================================================
WORKING HOURS
============================================================= --}}

    <div class="row">

        <div class="col-md-12">

            <div class="box box-primary">

                <div class="box-header with-border">

                    <h3 class="box-title">

                        <i class="fas fa-business-time"></i>

                        RFID Working Hours

                    </h3>

                </div>

                <div class="box-body">

                    <div class="row">

                        {{-- Working Hours --}}
                        <div class="col-md-3 col-sm-6">

                            <div class="form-group">

                                <label for="working_hours">
                                    Working Hours
                                </label>

                                <select name="working_hours" id="working_hours" class="form-control" disabled>

                                    @foreach ([1, 2, 4, 6, 8, 10, 12, 16, 24] as $hours)
                                        <option value="{{ $hours }}"
                                            {{ (int) ($workingHours ?? 8) === $hours ? 'selected' : '' }}>

                                            {{ $hours }}
                                            {{ $hours == 1 ? 'Hour' : 'Hours' }}

                                        </option>
                                    @endforeach

                                </select>

                            </div>

                        </div>


                        {{-- Save Working Hours --}}
                        <div class="col-md-9 col-sm-6">

                            <div class="form-group">

                                <label>&nbsp;</label>

                                <div>

                                    <button type="button" class="btn btn-primary" id="saveWorkingHours" disabled>

                                        <i class="fas fa-save"></i>

                                        Save Working Hours

                                    </button>

                                    <span class="text-muted" style="margin-left:10px;">

                                        Default:

                                        <strong>
                                            {{ $workingHours ?? 8 }} Hours
                                        </strong>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="alert alert-info" style="margin-bottom:0;">

                        <i class="fas fa-info-circle"></i>

                        <strong>Working Hours Logic:</strong>

                        Once an asset is scanned IN, repeated scans of the
                        same RFID tag during the configured working hours
                        will keep the asset IN.

                        After the configured duration, the asset will
                        automatically be marked OUT.

                    </div>

                </div>

            </div>

        </div>

    </div>



    {{-- =============================================================
SUMMARY
============================================================= --}}

    <div class="row">

        {{-- Total RFID Tags --}}
        <div class="col-md-3 col-sm-6 col-xs-12">

            <div class="small-box bg-aqua">

                <div class="inner">

                    <h3>
                        {{ number_format($summary['total_tags'] ?? ($summary['total_scans'] ?? 0)) }}
                    </h3>

                    <p>
                        Total RFID Tags
                    </p>

                </div>

                <div class="icon">

                    <i class="fas fa-tags"></i>

                </div>

            </div>

        </div>


        {{-- Unique Assets --}}
        <div class="col-md-3 col-sm-6 col-xs-12">

            <div class="small-box bg-blue">

                <div class="inner">

                    <h3>
                        {{ number_format($summary['unique_assets'] ?? 0) }}
                    </h3>

                    <p>
                        Unique Assets
                    </p>

                </div>

                <div class="icon">

                    <i class="fas fa-laptop"></i>

                </div>

            </div>

        </div>


        {{-- Currently IN --}}
        <div class="col-md-3 col-sm-6 col-xs-12">

            <div class="small-box bg-green">

                <div class="inner">

                    <h3>
                        {{ number_format($sessionSummary['currently_in'] ?? 0) }}
                    </h3>

                    <p>
                        Currently IN
                    </p>

                </div>

                <div class="icon">

                    <i class="fas fa-sign-in-alt"></i>

                </div>

            </div>

        </div>


        {{-- Scanned Today --}}
        <div class="col-md-3 col-sm-6 col-xs-12">

            <div class="small-box bg-yellow">

                <div class="inner">

                    <h3>
                        {{ number_format($summary['scanned_today'] ?? 0) }}
                    </h3>

                    <p>
                        Scanned Today
                    </p>

                </div>

                <div class="icon">

                    <i class="fas fa-calendar-check"></i>

                </div>

            </div>

        </div>

    </div>



    {{-- =============================================================
LATEST RFID SCAN
============================================================= --}}

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

                            {{-- Last Scanned --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    Last Scanned
                                </strong>

                                <p>

                                    {{ $latestScan->scanned_at ? $latestScan->scanned_at->format('d-m-Y h:i:s A') : '-' }}

                                </p>

                            </div>


                            {{-- Asset --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    Asset
                                </strong>

                                <p>

                                    {{ $latestScan->asset_name ?: '-' }}

                                    @if ($latestScan->asset_id)
                                        <br>

                                        <small>
                                            Asset ID:
                                            {{ $latestScan->asset_id }}
                                        </small>
                                    @endif

                                </p>

                            </div>


                            {{-- Asset Tag --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    Asset Tag
                                </strong>

                                <p>
                                    {{ $latestScan->asset_tag ?: '-' }}
                                </p>

                            </div>


                            {{-- RFID EPC --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    RFID EPC
                                </strong>

                                <p>

                                    @if ($latestScan->rfid_epc)
                                        <code>
                                            {{ $latestScan->rfid_epc }}
                                        </code>
                                    @else
                                        -
                                    @endif

                                </p>

                            </div>


                            {{-- Reader --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    Reader
                                </strong>

                                <p>
                                    {{ $latestScan->reader_code ?: '-' }}
                                </p>

                            </div>


                            {{-- Status --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    Status
                                </strong>

                                <p>

                                    @php
                                        $latestStatus = strtoupper(trim($latestScan->status ?? 'OUT'));
                                    @endphp


                                    @if ($latestStatus === 'IN')
                                        <span class="label label-success">

                                            <i class="fas fa-sign-in-alt"></i>
                                            IN

                                        </span>
                                    @elseif ($latestStatus === 'AUTO_OUT')
                                        <span class="label label-warning">

                                            <i class="fas fa-clock"></i>
                                            AUTO OUT

                                        </span>
                                    @else
                                        <span class="label label-primary">

                                            <i class="fas fa-sign-out-alt"></i>
                                            {{ $latestStatus ?: 'OUT' }}

                                        </span>
                                    @endif

                                </p>

                            </div>

                        </div>
                    @else
                        <div class="alert alert-info">

                            <i class="fas fa-info-circle"></i>

                            No RFID scans are available yet.

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>



    {{-- =============================================================
FILTERS
============================================================= --}}

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

                    <form method="GET" action="{{ route('asset-rfid-scan-events.index') }}">

                        <div class="row">

                            {{-- Asset Name --}}
                            <div class="col-md-3 col-sm-6">

                                <div class="form-group">

                                    <label for="asset_name">
                                        Asset Name
                                    </label>

                                    <input type="text" name="asset_name" id="asset_name" class="form-control"
                                        value="{{ request('asset_name') }}" placeholder="Laptop, mobile, charger...">

                                </div>

                            </div>


                            {{-- RFID EPC --}}
                            <div class="col-md-3 col-sm-6">

                                <div class="form-group">

                                    <label for="rfid_epc">
                                        RFID EPC
                                    </label>

                                    <input type="text" name="rfid_epc" id="rfid_epc" class="form-control"
                                        value="{{ request('rfid_epc') }}" placeholder="RFID EPC">

                                </div>

                            </div>


                            {{-- Serial Number --}}
                            <div class="col-md-3 col-sm-6">

                                <div class="form-group">

                                    <label for="serial">
                                        Serial Number
                                    </label>

                                    <input type="text" name="serial" id="serial" class="form-control"
                                        value="{{ request('serial') }}" placeholder="Device serial number">

                                </div>

                            </div>


                            {{-- Location --}}
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

                            {{-- Current Status --}}
                            <div class="col-md-3 col-sm-6">

                                <div class="form-group">

                                    <label for="status">
                                        Status
                                    </label>

                                    <select name="status" id="status" class="form-control">

                                        <option value="">
                                            All Status
                                        </option>

                                        <option value="IN" {{ request('status') === 'IN' ? 'selected' : '' }}>
                                            IN
                                        </option>

                                        <option value="OUT" {{ request('status') === 'OUT' ? 'selected' : '' }}>
                                            OUT
                                        </option>

                                        <option value="AUTO_OUT" {{ request('status') === 'AUTO_OUT' ? 'selected' : '' }}>
                                            AUTO OUT
                                        </option>

                                    </select>

                                </div>

                            </div>


                            {{-- OUT Type --}}
                            <div class="col-md-3 col-sm-6">

                                <div class="form-group">

                                    <label for="out_type">
                                        OUT Type
                                    </label>

                                    <select name="out_type" id="out_type" class="form-control">

                                        <option value="">
                                            All OUT Types
                                        </option>

                                        <option value="AUTO" {{ request('out_type') === 'AUTO' ? 'selected' : '' }}>
                                            AUTO
                                        </option>

                                        <option value="MANUAL" {{ request('out_type') === 'MANUAL' ? 'selected' : '' }}>
                                            MANUAL
                                        </option>

                                        <option value="SCAN" {{ request('out_type') === 'SCAN' ? 'selected' : '' }}>
                                            SCAN
                                        </option>

                                    </select>

                                </div>

                            </div>


                            {{-- Rows Per Page --}}
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


                            {{-- Filter Actions --}}
                            <div class="col-md-4 col-sm-12">

                                <div class="form-group">

                                    <label>
                                        &nbsp;
                                    </label>

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



    {{-- =============================================================
CURRENT RFID TAG STATUS
============================================================= --}}

    <div class="row" id="rfidStatusContent">

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

                            {{ $scanEvents->total() == 1 ? 'tag' : 'tags' }}

                        </span>

                    </div>

                </div>


                <div class="box-body table-responsive">

                    <table class="table table-striped table-bordered table-hover">

                        <thead>

                            <tr>

                                <th>Asset Name</th>
                                <th>Asset Tag</th>
                                <th>RFID EPC</th>
                                <th>Serial Number</th>
                                <th>Location</th>

                                <th>IN Time</th>
                                <th>OUT Time</th>
                                <th>Expected OUT</th>
                                <th>Working Hours</th>

                                <th>Status</th>
                                <th>OUT Type</th>

                                <th>Last Scanned</th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse ($scanEvents as $scanEvent)
                                @php

                                    /*
                                     * IMPORTANT:
                                     *
                                     * Actual database column:
                                     * status
                                     */
                                    $status = strtoupper(trim($scanEvent->status ?? 'OUT'));

                                    /*
                                     * Actual database column:
                                     * out_type
                                     */
                                    $outType = strtoupper(trim($scanEvent->out_type ?? ''));

                                @endphp


                                <tr>

                                    {{-- =================================================
                                ASSET NAME
                                ================================================== --}}
                                    <td>

                                        <strong>
                                            {{ $scanEvent->asset_name ?: '-' }}
                                        </strong>

                                        @if ($scanEvent->asset_id)
                                            <br>

                                            <small class="text-muted">

                                                ID:
                                                {{ $scanEvent->asset_id }}

                                            </small>
                                        @endif

                                    </td>


                                    {{-- Asset Tag --}}
                                    <td>

                                        {{ $scanEvent->asset_tag ?: '-' }}

                                    </td>


                                    {{-- RFID EPC --}}
                                    <td>

                                        @if ($scanEvent->rfid_epc)
                                            <code>
                                                {{ $scanEvent->rfid_epc }}
                                            </code>
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- Serial --}}
                                    <td>

                                        {{ $scanEvent->serial ?: '-' }}

                                    </td>


                                    {{-- Location --}}
                                    <td>

                                        @if ($scanEvent->location_id)
                                            {{ $locations->get($scanEvent->location_id, 'Location ID: ' . $scanEvent->location_id) }}
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- =================================================
                                IN TIME
                                Actual column: in_at
                                ================================================== --}}
                                    <td style="white-space:nowrap;">

                                        @if ($scanEvent->in_at)
                                            <span class="text-success">

                                                <i class="fas fa-sign-in-alt"></i>

                                                {{ $scanEvent->in_at->format('d-m-Y h:i:s A') }}

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- =================================================
                                OUT TIME
                                Actual column: out_at
                                ================================================== --}}
                                    <td style="white-space:nowrap;">

                                        @if ($scanEvent->out_at)
                                            <span class="text-primary">

                                                <i class="fas fa-sign-out-alt"></i>

                                                {{ $scanEvent->out_at->format('d-m-Y h:i:s A') }}

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- =================================================
                                EXPECTED OUT
                                Actual column: expected_out_at
                                ================================================== --}}
                                    <td style="white-space:nowrap;">

                                        @if ($status === 'IN' && $scanEvent->expected_out_at)
                                            <span class="text-warning">

                                                <i class="fas fa-clock"></i>

                                                {{ $scanEvent->expected_out_at->format('d-m-Y h:i:s A') }}

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- =================================================
                                WORKING HOURS
                                Actual column: working_hours
                                ================================================== --}}
                                    <td>

                                        @if ($scanEvent->working_hours)
                                            {{ $scanEvent->working_hours }}

                                            {{ (int) $scanEvent->working_hours === 1 ? 'Hour' : 'Hours' }}
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- =================================================
                                STATUS
                                Actual column: status
                                ================================================== --}}
                                    <td>

                                        @if ($status === 'IN')
                                            <span class="label label-success">

                                                <i class="fas fa-sign-in-alt"></i>

                                                IN

                                            </span>
                                        @elseif ($status === 'AUTO_OUT')
                                            <span class="label label-warning">

                                                <i class="fas fa-clock"></i>

                                                AUTO OUT

                                            </span>
                                        @elseif ($status === 'OUT')
                                            <span class="label label-primary">

                                                <i class="fas fa-sign-out-alt"></i>

                                                OUT

                                            </span>
                                        @else
                                            <span class="label label-default">

                                                {{ $status ?: 'UNKNOWN' }}

                                            </span>
                                        @endif

                                    </td>


                                    {{-- =================================================
                                OUT TYPE
                                Actual column: out_type
                                ================================================== --}}
                                    <td>

                                        @if ($outType === 'AUTO')
                                            <span class="label label-warning">

                                                <i class="fas fa-clock"></i>

                                                AUTO OUT

                                            </span>
                                        @elseif ($outType === 'MANUAL')
                                            <span class="label label-danger">

                                                <i class="fas fa-hand-paper"></i>

                                                MANUAL OUT

                                            </span>
                                        @elseif ($outType === 'SCAN')
                                            <span class="label label-primary">

                                                <i class="fas fa-sign-out-alt"></i>

                                                SCAN OUT

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>


                                    {{-- Last Scanned --}}
                                    <td style="white-space:nowrap;">

                                        @if ($scanEvent->scanned_at)
                                            {{ $scanEvent->scanned_at->format('d-m-Y h:i:s A') }}
                                        @else
                                            -
                                        @endif

                                    </td>

                                </tr>


                            @empty

                                <tr>

                                    <td colspan="12" class="text-center text-muted" style="padding:30px;">

                                        <i class="fas fa-info-circle"></i>

                                        No RFID events found.

                                        <br>

                                        <small>
                                            Scan a mapped RFID tag to create a
                                            new IN session.
                                        </small>

                                    </td>

                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                </div>


                {{-- Pagination --}}
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



{{-- =============================================================
JAVASCRIPT
============================================================= --}}

@section('moar_scripts')

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            /*
             * =============================================================
             * STATE
             * =============================================================
             */

            let userIsFiltering = false;

            let isClearingEvents = false;

            let isRefreshing = false;

            let refreshTimer = null;

            const REFRESH_INTERVAL = 10000;


            /*
             * =============================================================
             * ELEMENTS
             * =============================================================
             */

            const filterForm = document.querySelector(
                'form[action="{{ route('asset-rfid-scan-events.index') }}"]'
            );

            const clearEventsButton =
                document.getElementById('clearRfidEvents');

            const rfidStatusContent =
                document.getElementById('rfidStatusContent');


            /*
             * =============================================================
             * FILTER FORM
             * =============================================================
             */

            if (filterForm) {

                filterForm.addEventListener('input', function() {

                    userIsFiltering = true;

                });

                filterForm.addEventListener('change', function() {

                    userIsFiltering = true;

                });

                filterForm.addEventListener('submit', function() {

                    userIsFiltering = true;

                });

            }


            /*
             * =============================================================
             * CSRF TOKEN
             * =============================================================
             */

            function getCsrfToken() {

                const csrfTokenElement =
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    );

                if (!csrfTokenElement) {

                    return '';

                }

                return csrfTokenElement.getAttribute('content') || '';

            }


            /*
             * =============================================================
             * REFRESH RFID STATUS
             * =============================================================
             */

            function refreshRfidStatus() {

                if (userIsFiltering) {
                    return;
                }

                if (isClearingEvents) {
                    return;
                }

                if (isRefreshing) {
                    return;
                }

                if (!rfidStatusContent) {
                    return;
                }

                isRefreshing = true;


                const url = new URL(
                    "{{ route('asset-rfid-scan-events.status') }}",
                    window.location.origin
                );


                /*
                 * Copy filters.
                 */

                if (filterForm) {

                    const formData =
                        new FormData(filterForm);

                    formData.forEach(function(value, key) {

                        if (
                            value !== null &&
                            value !== ''
                        ) {

                            url.searchParams.set(
                                key,
                                value
                            );

                        }

                    });

                }


                fetch(
                        url.toString(), {
                            method: 'GET',

                            headers: {

                                'Accept': 'application/json',

                                'X-Requested-With': 'XMLHttpRequest'

                            },

                            cache: 'no-store'

                        }
                    )

                    .then(function(response) {

                        if (!response.ok) {

                            throw new Error(
                                'RFID status request failed. HTTP ' +
                                response.status
                            );

                        }

                        return response.json();

                    })

                    .then(function(data) {

                        if (
                            !data ||
                            !data.success
                        ) {

                            throw new Error(
                                data.message ||
                                'Unable to refresh RFID status.'
                            );

                        }


                        /*
                         * Replace only RFID status area.
                         */

                        if (data.html) {

                            rfidStatusContent.innerHTML =
                                data.html;

                        }

                    })

                    .catch(function(error) {

                        console.error(
                            'RFID status AJAX error:',
                            error
                        );

                    })

                    .finally(function() {

                        isRefreshing = false;

                    });

            }


            /*
             * =============================================================
             * CLEAR RFID EVENTS
             * =============================================================
             */

            if (clearEventsButton) {

                clearEventsButton.addEventListener(
                    'click',
                    function() {

                        if (isClearingEvents) {
                            return;
                        }


                        const confirmed = confirm(
                            'Are you sure you want to clear all RFID events?\n\n' +
                            'This will permanently delete ALL RFID event records.'
                        );


                        if (!confirmed) {
                            return;
                        }


                        isClearingEvents = true;


                        /*
                         * Stop polling.
                         */

                        if (refreshTimer) {

                            clearTimeout(refreshTimer);

                            refreshTimer = null;

                        }


                        const originalButtonHtml =
                            clearEventsButton.innerHTML;


                        clearEventsButton.disabled = true;


                        clearEventsButton.innerHTML =
                            '<i class="fas fa-spinner fa-spin"></i> Clearing...';


                        fetch(
                                "{{ route('asset-rfid-scan-events.clear') }}", {
                                    method: 'POST',

                                    headers: {

                                        'Content-Type': 'application/json',

                                        'Accept': 'application/json',

                                        'X-CSRF-TOKEN': getCsrfToken(),

                                        'X-Requested-With': 'XMLHttpRequest'

                                    },

                                    body: JSON.stringify({})

                                }
                            )

                            .then(function(response) {

                                return response.json().then(
                                    function(data) {

                                        return {

                                            ok: response.ok,

                                            data: data

                                        };

                                    }
                                );

                            })

                            .then(function(result) {

                                if (
                                    !result.ok ||
                                    !result.data.success
                                ) {

                                    throw new Error(
                                        result.data.message ||
                                        'Unable to clear RFID events.'
                                    );

                                }


                                alert(
                                    result.data.message ||
                                    'All RFID events cleared successfully.'
                                );


                                /*
                                 * Reload complete page.
                                 *
                                 * This is intentional because the clear operation
                                 * affects summary, latest scan and status table.
                                 */

                                window.location.reload();

                            })

                            .catch(function(error) {

                                console.error(
                                    'Clear RFID events error:',
                                    error
                                );


                                alert(
                                    error.message ||
                                    'Unable to clear RFID events.'
                                );


                                clearEventsButton.disabled = false;

                                clearEventsButton.innerHTML =
                                    originalButtonHtml;

                                isClearingEvents = false;


                                scheduleNextRefresh();

                            });

                    }
                );

            }


            /*
             * =============================================================
             * WORKING HOURS
             * =============================================================
             *
             * Currently disabled.
             */

            const saveWorkingHoursButton =
                document.getElementById('saveWorkingHours');


            if (saveWorkingHoursButton) {

                saveWorkingHoursButton.addEventListener(
                    'click',
                    function() {

                        const workingHoursElement =
                            document.getElementById('working_hours');


                        if (!workingHoursElement) {
                            return;
                        }


                        const selectedWorkingHours =
                            workingHoursElement.value;


                        console.log(
                            'Selected working hours:',
                            selectedWorkingHours
                        );

                    }
                );

            }


            /*
             * =============================================================
             * AJAX POLLING
             * =============================================================
             */

            function scheduleNextRefresh() {

                if (refreshTimer) {

                    clearTimeout(refreshTimer);

                }


                refreshTimer = setTimeout(
                    function() {

                        refreshRfidStatus();

                        scheduleNextRefresh();

                    },
                    REFRESH_INTERVAL
                );

            }


            /*
             * Start polling.
             */

            scheduleNextRefresh();


            /*
             * =============================================================
             * PAGE VISIBILITY
             * =============================================================
             */

            document.addEventListener(
                'visibilitychange',
                function() {

                    if (
                        document.visibilityState === 'hidden'
                    ) {

                        if (refreshTimer) {

                            clearTimeout(refreshTimer);

                            refreshTimer = null;

                        }

                    } else {

                        if (!userIsFiltering) {

                            refreshRfidStatus();

                        }

                        scheduleNextRefresh();

                    }

                }
            );

        });
    </script>

@stop
