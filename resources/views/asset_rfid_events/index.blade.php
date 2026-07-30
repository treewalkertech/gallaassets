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

    {{-- =========================================================
WORKING HOURS
========================================================== --}}

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

                                <label>
                                    &nbsp;
                                </label>


                                <div>

                                    <button type="button" class="btn btn-primary" id="saveWorkingHours" disabled>

                                        <i class="fas fa-save"></i>

                                        Save Working Hours

                                    </button>


                                    <span class="text-muted" style="margin-left: 10px;">

                                        Default:

                                        <strong>
                                            {{ $workingHours ?? 8 }} Hours
                                        </strong>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="alert alert-info" style="margin-bottom: 0;">

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



    {{-- =========================================================
SUMMARY
========================================================== --}}

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



    {{-- =========================================================
LATEST RFID SCAN
========================================================== --}}

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

                                    <code>
                                        {{ $latestScan->rfid_epc ?: '-' }}
                                    </code>

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



                            {{-- Scan Result --}}
                            <div class="col-md-2 col-sm-6">

                                <strong>
                                    Scan Result
                                </strong>

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



    {{-- =========================================================
FILTERS
========================================================== --}}

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

                                    <label for="current_status">
                                        Current Status
                                    </label>


                                    <select name="current_status" id="current_status" class="form-control">

                                        <option value="">
                                            All Status
                                        </option>


                                        <option value="IN" {{ request('current_status') === 'IN' ? 'selected' : '' }}>

                                            IN

                                        </option>


                                        <option value="OUT"
                                            {{ request('current_status') === 'OUT' ? 'selected' : '' }}>

                                            OUT

                                        </option>


                                        <option value="AUTO_OUT"
                                            {{ request('current_status') === 'AUTO_OUT' ? 'selected' : '' }}>

                                            AUTO OUT

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
                            <div class="col-md-7 col-sm-12">

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



    {{-- =========================================================
CURRENT RFID TAG STATUS
========================================================== --}}

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

                                <th>
                                    Asset Name
                                </th>

                                <th>
                                    Asset Tag
                                </th>

                                <th>
                                    RFID EPC
                                </th>

                                <th>
                                    Serial Number
                                </th>

                                <th>
                                    Location
                                </th>

                                <th>
                                    Last IN
                                </th>

                                <th>
                                    Last OUT
                                </th>

                                <th>
                                    Expected OUT
                                </th>

                                <th>
                                    Working Duration
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    OUT Type
                                </th>

                                <th>
                                    Last Scanned
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse ($scanEvents as $scanEvent)
                                <tr>


                                    {{-- Asset Name --}}
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

                                        <code>
                                            {{ $scanEvent->rfid_epc ?: '-' }}
                                        </code>

                                    </td>



                                    {{-- Serial --}}
                                    <td>

                                        {{ $scanEvent->serial ?: '-' }}

                                    </td>



                                    {{-- Location --}}
                                    <td>

                                        {{ $scanEvent->location_id
                                            ? $locations->get($scanEvent->location_id, 'Location ID: ' . $scanEvent->location_id)
                                            : '-' }}

                                    </td>



                                    {{-- Last IN --}}
                                    <td style="white-space: nowrap;">

                                        @if ($scanEvent->last_in_at)
                                            <span class="text-success">

                                                <i class="fas fa-sign-in-alt"></i>

                                                {{ $scanEvent->last_in_at->format('d-m-Y h:i:s A') }}

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>



                                    {{-- Last OUT --}}
                                    <td style="white-space: nowrap;">

                                        @if ($scanEvent->last_out_at)
                                            <span class="text-primary">

                                                <i class="fas fa-sign-out-alt"></i>

                                                {{ $scanEvent->last_out_at->format('d-m-Y h:i:s A') }}

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>



                                    {{-- Expected OUT --}}
                                    <td style="white-space: nowrap;">

                                        @if (($scanEvent->current_status ?? null) === 'IN' && $scanEvent->expected_out_at)
                                            {{ $scanEvent->expected_out_at->format('d-m-Y h:i:s A') }}
                                        @else
                                            -
                                        @endif

                                    </td>



                                    {{-- Working Duration --}}
                                    <td style="white-space: nowrap;">

                                        @if (($scanEvent->current_status ?? null) === 'IN' && $scanEvent->last_in_at)
                                            @php

                                                $start = $scanEvent->last_in_at;

                                                $end = now();

                                                $totalMinutes = max(0, $start->diffInMinutes($end));

                                                $hours = intdiv($totalMinutes, 60);

                                                $minutes = $totalMinutes % 60;

                                            @endphp


                                            {{ $hours }}h {{ $minutes }}m
                                        @elseif (($scanEvent->current_status ?? null) === 'OUT' && $scanEvent->last_in_at && $scanEvent->last_out_at)
                                            @php

                                                $totalMinutes = max(
                                                    0,
                                                    $scanEvent->last_in_at->diffInMinutes($scanEvent->last_out_at),
                                                );

                                                $hours = intdiv($totalMinutes, 60);

                                                $minutes = $totalMinutes % 60;

                                            @endphp


                                            {{ $hours }}h {{ $minutes }}m
                                        @else
                                            -
                                        @endif

                                    </td>



                                    {{-- Current Status --}}
                                    <td>

                                        @php

                                            $status = strtoupper($scanEvent->current_status ?? 'OUT');

                                        @endphp


                                        @if ($status === 'IN')
                                            <span class="label label-success">

                                                <i class="fas fa-circle"></i>

                                                IN

                                            </span>
                                        @elseif ($status === 'AUTO_OUT')
                                            <span class="label label-warning">

                                                <i class="fas fa-clock"></i>

                                                AUTO OUT

                                            </span>
                                        @else
                                            <span class="label label-primary">

                                                <i class="fas fa-sign-out-alt"></i>

                                                OUT

                                            </span>
                                        @endif

                                    </td>



                                    {{-- OUT Type --}}
                                    <td>

                                        @php

                                            $outType = strtoupper($scanEvent->out_type ?? '');

                                        @endphp


                                        @if ($outType === 'AUTO')
                                            <span class="label label-warning">

                                                AUTO OUT

                                            </span>
                                        @elseif ($outType === 'MANUAL')
                                            <span class="label label-danger">

                                                MANUAL OUT

                                            </span>
                                        @elseif ($outType === 'SCAN')
                                            <span class="label label-primary">

                                                SCAN OUT

                                            </span>
                                        @else
                                            -
                                        @endif

                                    </td>



                                    {{-- Last Scanned --}}
                                    <td style="white-space: nowrap;">

                                        {{ $scanEvent->scanned_at ? $scanEvent->scanned_at->format('d-m-Y h:i:s A') : '-' }}

                                    </td>

                                </tr>


                            @empty

                                <tr>

                                    <td colspan="12" class="text-center text-muted">

                                        <i class="fas fa-info-circle"></i>

                                        No mapped RFID tags found.

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
             * =========================================================
             * STATE
             * =========================================================
             */

            let userIsFiltering = false;

            let isClearingEvents = false;



            /*
             * =========================================================
             * FILTER FORM
             * =========================================================
             *
             * Stop automatic refresh when the user is interacting
             * with the filter form.
             */

            const filterForm = document.querySelector(
                'form[action="{{ route('asset-rfid-scan-events.index') }}"]'
            );


            if (filterForm) {

                filterForm.addEventListener(
                    'input',
                    function() {

                        userIsFiltering = true;

                    }
                );


                filterForm.addEventListener(
                    'change',
                    function() {

                        userIsFiltering = true;

                    }
                );

            }



            /*
             * =========================================================
             * CLEAR RFID EVENTS
             * =========================================================
             */

            const clearEventsButton =
                document.getElementById('clearRfidEvents');


            if (clearEventsButton) {

                clearEventsButton.addEventListener(
                    'click',
                    function() {


                        /*
                         * Prevent double click.
                         */

                        if (isClearingEvents) {

                            return;

                        }


                        /*
                         * Confirmation.
                         */

                        const confirmed = confirm(
                            'Are you sure you want to clear all RFID events?\n\n' +
                            'This will remove all current RFID event records.'
                        );


                        if (!confirmed) {

                            return;

                        }


                        /*
                         * Set state.
                         */

                        isClearingEvents = true;

                        userIsFiltering = true;


                        /*
                         * Save original button text.
                         */

                        const originalButtonHtml =
                            clearEventsButton.innerHTML;


                        /*
                         * Disable button.
                         */

                        clearEventsButton.disabled = true;


                        clearEventsButton.innerHTML =
                            '<i class="fas fa-spinner fa-spin"></i> Clearing...';



                        /*
                         * Get Laravel CSRF token.
                         */

                        const csrfTokenElement =
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            );


                        const csrfToken =
                            csrfTokenElement ?
                            csrfTokenElement.getAttribute('content') :
                            '';



                        /*
                         * Make Laravel request.
                         */

                        fetch(
                                "{{ route('asset-rfid-scan-events.clear') }}", {
                                    method: 'POST',

                                    headers: {

                                        'Content-Type': 'application/json',

                                        'Accept': 'application/json',

                                        'X-CSRF-TOKEN': csrfToken,

                                        'X-Requested-With': 'XMLHttpRequest'

                                    },

                                    body: JSON.stringify({})

                                }
                            )


                            /*
                             * =================================================
                             * PROCESS RESPONSE
                             * =================================================
                             */

                            .then(function(response) {

                                return response.json()

                                    .then(function(data) {

                                        return {

                                            ok: response.ok,

                                            data: data

                                        };

                                    });

                            })


                            .then(function(result) {


                                /*
                                 * Laravel returned an error.
                                 */

                                if (
                                    !result.ok ||
                                    !result.data.success
                                ) {

                                    throw new Error(
                                        result.data.message ||
                                        'Unable to clear RFID events.'
                                    );

                                }


                                /*
                                 * Success message.
                                 */

                                alert(
                                    result.data.message ||
                                    'RFID events cleared successfully.'
                                );


                                /*
                                 * Reload the page.
                                 *
                                 * This updates:
                                 *
                                 * - Total RFID Tags
                                 * - Unique Assets
                                 * - Currently IN
                                 * - Scanned Today
                                 * - Latest RFID Scan
                                 * - Current RFID Tag Status
                                 * - Pagination
                                 */

                                window.location.reload();

                            })


                            /*
                             * =================================================
                             * ERROR
                             * =================================================
                             */

                            .catch(function(error) {

                                console.error(
                                    'Clear RFID events error:',
                                    error
                                );


                                alert(
                                    error.message ||
                                    'Unable to clear RFID events.'
                                );


                                /*
                                 * Restore button.
                                 */

                                clearEventsButton.disabled = false;


                                clearEventsButton.innerHTML =
                                    originalButtonHtml;


                                isClearingEvents = false;

                                userIsFiltering = false;

                            });

                    }
                );

            }



            /*
             * =========================================================
             * WORKING HOURS
             * =========================================================
             *
             * Currently disabled.
             *
             * Backend currently uses:
             *
             * $workingHours = 8;
             *
             * Later this can be connected to the settings table.
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
        });
    </script>

@stop
