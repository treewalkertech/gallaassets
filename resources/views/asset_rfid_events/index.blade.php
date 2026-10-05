@extends('layouts/default')

@section('title')
    Asset RFID Location Tracking
    @parent
@stop

@section('header_right')
    <div class="btn-group pull-right">
        <button type="button" class="btn btn-default" id="manualRfidRefresh">
            <i class="fas fa-sync-alt"></i>
            Refresh
        </button>

        <button type="button" class="btn btn-danger" id="clearRfidEvents">
            <i class="fas fa-trash-alt"></i>
            Clear Events
        </button>
    </div>
@stop

@section('content')
    <div id="rfidStatusContent">
        <div class="row">
            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ number_format($summary['tracked_assets'] ?? 0) }}</h3>
                        <p>Tracked Assets</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-blue">
                    <div class="inner">
                        <h3>{{ number_format($summary['floors'] ?? 0) }}</h3>
                        <p>Floors Detected</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ number_format($summary['readers'] ?? 0) }}</h3>
                        <p>Readers Detected</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-broadcast-tower"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($summary['scanned_today'] ?? 0) }}</h3>
                        <p>Assets Scanned Today</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Latest RFID Scan
                        </h3>

                        <div class="box-tools pull-right text-muted">
                            <small>
                                <i class="fas fa-sync-alt"></i>
                                Auto-refresh every 10 seconds
                            </small>
                        </div>
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
                                    <strong>Floor</strong>
                                    <p>
                                        <span class="label label-primary">
                                            {{ $latestScan->gate_name ?: $defaultGateName }}
                                        </span>
                                    </p>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <strong>Reader Code</strong>
                                    <p>{{ $latestScan->reader_code ?: $defaultReaderCode }}</p>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <strong>Asset Name</strong>
                                    <p>{{ $latestScan->asset_name ?: '-' }}</p>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <strong>Asset Number</strong>
                                    <p>
                                        @if ($latestScan->asset_tag)
                                            <a href="#" class="rfid-history-link"
                                                data-history-url="{{ route('asset-rfid-scan-events.history', ['scanEvent' => $latestScan->id]) }}"
                                                data-asset-tag="{{ $latestScan->asset_tag }}">
                                                {{ $latestScan->asset_tag }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <strong>Serial Number</strong>
                                    <p>{{ $latestScan->serial ?: '-' }}</p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 col-sm-6">
                                    <strong>RFID EPC</strong>
                                    <p>
                                        @if ($latestScan->rfid_epc)
                                            <code>{{ $latestScan->rfid_epc }}</code>
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>

                                {{-- <div class="col-md-2 col-sm-6">
                                    <strong>Antenna</strong>
                                    <p>{{ $latestScan->antenna_no ?? '-' }}</p>
                                </div> --}}

                                <div class="col-md-2 col-sm-6">
                                    <strong>RSSI</strong>
                                    <p>{{ $latestScan->rssi ?? '-' }}</p>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <strong>Read Count</strong>
                                    <p>{{ number_format($latestScan->read_count ?? 0) }}</p>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info" style="margin-bottom: 0;">
                                <i class="fas fa-info-circle"></i>
                                No mapped RFID asset has been scanned yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

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
                        <form method="GET" action="{{ route('asset-rfid-scan-events.index') }}" id="rfidFilterForm">
                            <div class="row">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="asset_name">Asset Name</label>
                                        <input type="text" name="asset_name" id="asset_name" class="form-control"
                                            value="{{ request('asset_name') }}" placeholder="Laptop, monitor, mobile...">
                                    </div>
                                </div>

                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="asset_tag">Asset Number</label>
                                        <input type="text" name="asset_tag" id="asset_tag" class="form-control"
                                            value="{{ request('asset_tag') }}" placeholder="Asset number">
                                    </div>
                                </div>

                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="serial">Serial Number</label>
                                        <input type="text" name="serial" id="serial" class="form-control"
                                            value="{{ request('serial') }}" placeholder="Serial number">
                                    </div>
                                </div>

                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="rfid_epc">RFID EPC</label>
                                        <input type="text" name="rfid_epc" id="rfid_epc" class="form-control"
                                            value="{{ request('rfid_epc') }}" placeholder="RFID EPC">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="gate_name">Floor</label>
                                        <input type="text" name="gate_name" id="gate_name" class="form-control"
                                            value="{{ request('gate_name') }}" placeholder="3RD Floor">
                                    </div>
                                </div>

                                <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label for="reader_code">Reader Code</label>
                                        <input type="text" name="reader_code" id="reader_code" class="form-control"
                                            value="{{ request('reader_code') }}" placeholder="3RD-READER-001">
                                    </div>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <div class="form-group">
                                        <label for="date_from">Date From</label>
                                        <input type="date" name="date_from" id="date_from" class="form-control"
                                            value="{{ request('date_from') }}">
                                    </div>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <div class="form-group">
                                        <label for="date_to">Date To</label>
                                        <input type="date" name="date_to" id="date_to" class="form-control"
                                            value="{{ request('date_to') }}">
                                    </div>
                                </div>

                                <div class="col-md-2 col-sm-6">
                                    <div class="form-group">
                                        <label for="per_page">Rows Per Page</label>
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
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        Apply Filters
                                    </button>

                                    <button type="submit" class="btn btn-success"
                                        formaction="{{ route('asset-rfid-scan-events.export') }}" formmethod="GET">
                                        <i class="fas fa-file-csv"></i>
                                        Export CSV
                                    </button>

                                    <a href="{{ route('asset-rfid-scan-events.index') }}" class="btn btn-default">
                                        <i class="fas fa-times"></i>
                                        Clear Filters
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fas fa-map-marked-alt"></i>
                            Asset Last Known Location
                        </h3>

                        <div class="box-tools pull-right">
                            <span class="label label-info">
                                {{ number_format($scanEvents->total()) }} record(s)
                            </span>
                        </div>
                    </div>

                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Last Scan</th>
                                    <th>Floor</th>
                                    <th>Reader Code</th>
                                    <th>Asset Name</th>
                                    <th>Asset Number</th>
                                    <th>Serial Number</th>
                                    <th>RFID EPC</th>
                                    {{-- <th>Antenna</th> --}}
                                    <th>RSSI</th>
                                    <th>Read Count</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($scanEvents as $scanEvent)
                                    <tr>
                                        <td style="white-space: nowrap;">
                                            {{ $scanEvent->scanned_at ? $scanEvent->scanned_at->format('d-m-Y h:i:s A') : '-' }}
                                        </td>

                                        <td style="white-space: nowrap;">
                                            <span class="label label-primary">
                                                {{ $scanEvent->gate_name ?: $defaultGateName }}
                                            </span>
                                        </td>

                                        <td style="white-space: nowrap;">
                                            {{ $scanEvent->reader_code ?: $defaultReaderCode }}
                                        </td>

                                        <td>{{ $scanEvent->asset_name ?: '-' }}</td>
                                        <td>
                                            @if ($scanEvent->asset_tag)
                                                <a href="#" class="rfid-history-link"
                                                    data-history-url="{{ route('asset-rfid-scan-events.history', ['scanEvent' => $scanEvent->id]) }}"
                                                    data-asset-tag="{{ $scanEvent->asset_tag }}">
                                                    {{ $scanEvent->asset_tag }}<span class="fas fa-chart-bar"
                                                        style="margin-left: 4px;"></span>
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $scanEvent->serial ?: '-' }}</td>

                                        <td style="white-space: nowrap;">
                                            @if ($scanEvent->rfid_epc)
                                                <code>{{ $scanEvent->rfid_epc }}</code>
                                            @else
                                                -
                                            @endif
                                        </td>

                                        {{-- <td>{{ $scanEvent->antenna_no ?? '-' }}</td> --}}
                                        <td>{{ $scanEvent->rssi ?? '-' }}</td>
                                        <td>{{ number_format($scanEvent->read_count ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted" style="padding: 30px;">
                                            <i class="fas fa-info-circle"></i>
                                            No RFID asset locations found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($scanEvents->hasPages())
                        <div class="box-footer clearfix">
                            <div class="pull-left">
                                Showing {{ $scanEvents->firstItem() }}
                                to {{ $scanEvents->lastItem() }}
                                of {{ $scanEvents->total() }} assets
                            </div>

                            <div class="pull-right">
                                {{ $scanEvents->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rfidHistoryModal" tabindex="-1" role="dialog"
        aria-labelledby="rfidHistoryModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="rfidHistoryModalLabel">
                        <i class="fas fa-history"></i>
                        Last 4 RFID Scans — <span id="rfidHistoryAssetTag">-</span>
                    </h4>
                </div>

                <div class="modal-body">
                    <div class="row" style="margin-bottom: 12px;">
                        <div class="col-md-3 col-sm-6">
                            <strong>Asset Name</strong>
                            <p id="rfidHistoryAssetName">-</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <strong>Asset Number</strong>
                            <p id="rfidHistoryAssetNumber">-</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <strong>Serial Number</strong>
                            <p id="rfidHistorySerial">-</p>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <strong>RFID EPC</strong>
                            <p><code id="rfidHistoryEpc">-</code></p>
                        </div>
                    </div>

                    <div class="alert alert-info" id="rfidHistoryLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        Loading scan history...
                    </div>

                    <div class="alert alert-danger" id="rfidHistoryError" style="display: none;"></div>

                    <div class="alert alert-info" id="rfidHistoryEmpty" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        No scan history is available for this asset.
                    </div>

                    <div class="table-responsive" id="rfidHistoryTableWrapper" style="display: none;">
                        <table class="table table-hover table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Scan Time</th>
                                    <th>Floor</th>
                                    <th>Reader Code</th>
                                    <th>Reader Name</th>
                                    <th>Reader IP</th>
                                    <th>Antenna</th>
                                    <th>RSSI</th>
                                    <th>Scan Type</th>
                                </tr>
                            </thead>
                            <tbody id="rfidHistoryTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('moar_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let userIsEditingFilters = false;
            let isClearingEvents = false;
            let isRefreshing = false;
            let isHistoryModalOpen = false;
            let refreshTimer = null;

            const refreshInterval = 10000;
            const clearEventsButton = document.getElementById('clearRfidEvents');
            const manualRefreshButton = document.getElementById('manualRfidRefresh');
            const historyModal = document.getElementById('rfidHistoryModal');
            const historyLoading = document.getElementById('rfidHistoryLoading');
            const historyError = document.getElementById('rfidHistoryError');
            const historyEmpty = document.getElementById('rfidHistoryEmpty');
            const historyTableWrapper = document.getElementById('rfidHistoryTableWrapper');
            const historyTableBody = document.getElementById('rfidHistoryTableBody');

            function getCsrfToken() {
                const element = document.querySelector('meta[name="csrf-token"]');
                return element ? (element.getAttribute('content') || '') : '';
            }

            function setHistoryText(elementId, value) {
                const element = document.getElementById(elementId);

                if (element) {
                    element.textContent = value || '-';
                }
            }

            function appendHistoryCell(row, value, noWrap) {
                const cell = document.createElement('td');
                cell.textContent = value === null || value === undefined || value === '' ?
                    '-' :
                    String(value);

                if (noWrap) {
                    cell.style.whiteSpace = 'nowrap';
                }

                row.appendChild(cell);
            }

            function showHistoryModal() {
                isHistoryModalOpen = true;

                if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(historyModal).modal('show');
                }
            }

            async function openHistoryPopup(trigger) {
                const historyUrl = trigger.getAttribute('data-history-url');
                const assetTag = trigger.getAttribute('data-asset-tag') || '-';

                if (!historyUrl || !historyModal) {
                    return;
                }

                setHistoryText('rfidHistoryAssetTag', assetTag);
                setHistoryText('rfidHistoryAssetName', '-');
                setHistoryText('rfidHistoryAssetNumber', assetTag);
                setHistoryText('rfidHistorySerial', '-');
                setHistoryText('rfidHistoryEpc', '-');

                historyTableBody.innerHTML = '';
                historyLoading.style.display = 'block';
                historyError.style.display = 'none';
                historyEmpty.style.display = 'none';
                historyTableWrapper.style.display = 'none';

                showHistoryModal();

                try {
                    const response = await fetch(historyUrl, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        cache: 'no-store'
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to load RFID history.');
                    }

                    setHistoryText('rfidHistoryAssetTag', data.asset.asset_tag);
                    setHistoryText('rfidHistoryAssetName', data.asset.asset_name);
                    setHistoryText('rfidHistoryAssetNumber', data.asset.asset_tag);
                    setHistoryText('rfidHistorySerial', data.asset.serial);
                    setHistoryText('rfidHistoryEpc', data.asset.rfid_epc);

                    historyLoading.style.display = 'none';

                    if (!Array.isArray(data.history) || data.history.length === 0) {
                        historyEmpty.style.display = 'block';
                        return;
                    }

                    data.history.forEach(function(item) {
                        const row = document.createElement('tr');
                        const scanType = String(item.event_type || 'LOCATION_SCAN')
                            .replace(/_/g, ' ');

                        appendHistoryCell(row, item.scanned_at, true);
                        appendHistoryCell(row, item.gate_name, true);
                        appendHistoryCell(row, item.reader_code, true);
                        appendHistoryCell(row, item.reader_name, false);
                        appendHistoryCell(row, item.reader_ip, true);
                        appendHistoryCell(row, item.antenna_no, false);
                        appendHistoryCell(row, item.rssi, false);
                        appendHistoryCell(row, scanType, true);
                        historyTableBody.appendChild(row);
                    });

                    historyTableWrapper.style.display = 'block';
                } catch (error) {
                    console.error('RFID history request failed:', error);
                    historyLoading.style.display = 'none';
                    historyError.textContent = error.message ||
                        'Unable to load RFID scan history.';
                    historyError.style.display = 'block';
                }
            }

            document.addEventListener('input', function(event) {
                if (event.target.closest('#rfidFilterForm')) {
                    userIsEditingFilters = true;
                }
            });

            document.addEventListener('change', function(event) {
                if (event.target.closest('#rfidFilterForm')) {
                    userIsEditingFilters = true;
                }
            });

            async function refreshRfidStatus(forceRefresh) {
                if (isRefreshing || isClearingEvents) {
                    return;
                }

                if (
                    !forceRefresh &&
                    (userIsEditingFilters || isHistoryModalOpen)
                ) {
                    return;
                }

                const currentContent = document.getElementById('rfidStatusContent');

                if (!currentContent) {
                    return;
                }

                isRefreshing = true;

                if (manualRefreshButton) {
                    manualRefreshButton.disabled = true;
                }

                try {
                    const url = new URL(
                        "{{ route('asset-rfid-scan-events.status') }}",
                        window.location.origin
                    );

                    const currentParams = new URLSearchParams(window.location.search);
                    currentParams.forEach(function(value, key) {
                        url.searchParams.set(key, value);
                    });

                    const response = await fetch(url.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        cache: 'no-store'
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success || !data.html) {
                        throw new Error(data.message || 'Unable to refresh RFID data.');
                    }

                    const parsedPage = new DOMParser().parseFromString(
                        data.html,
                        'text/html'
                    );

                    const refreshedContent = parsedPage.getElementById(
                        'rfidStatusContent'
                    );

                    if (!refreshedContent) {
                        throw new Error('RFID refresh content was not found.');
                    }

                    currentContent.innerHTML = refreshedContent.innerHTML;
                    userIsEditingFilters = false;
                } catch (error) {
                    console.error('RFID refresh failed:', error);
                } finally {
                    isRefreshing = false;

                    if (manualRefreshButton) {
                        manualRefreshButton.disabled = false;
                    }
                }
            }

            function scheduleNextRefresh() {
                if (refreshTimer) {
                    clearTimeout(refreshTimer);
                }

                refreshTimer = setTimeout(async function() {
                    await refreshRfidStatus(false);
                    scheduleNextRefresh();
                }, refreshInterval);
            }

            if (manualRefreshButton) {
                manualRefreshButton.addEventListener('click', function() {
                    userIsEditingFilters = false;
                    refreshRfidStatus(true);
                });
            }

            if (clearEventsButton) {
                clearEventsButton.addEventListener('click', async function() {
                    if (isClearingEvents) {
                        return;
                    }

                    const confirmed = confirm(
                        'Are you sure you want to clear all current RFID locations and scan history?'
                    );

                    if (!confirmed) {
                        return;
                    }

                    isClearingEvents = true;
                    clearEventsButton.disabled = true;
                    const originalButtonHtml = clearEventsButton.innerHTML;
                    clearEventsButton.innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Clearing...';

                    try {
                        const response = await fetch(
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
                        );

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Unable to clear RFID events.');
                        }

                        alert(data.message);
                        window.location.reload();
                    } catch (error) {
                        console.error('Clear RFID events failed:', error);
                        alert(error.message || 'Unable to clear RFID events.');
                        isClearingEvents = false;
                        clearEventsButton.disabled = false;
                        clearEventsButton.innerHTML = originalButtonHtml;
                    }
                });
            }

            document.addEventListener('click', function(event) {
                const trigger = event.target.closest('.rfid-history-link');

                if (!trigger) {
                    return;
                }

                event.preventDefault();
                openHistoryPopup(trigger);
            });

            if (window.jQuery && historyModal) {
                window.jQuery(historyModal).on('hidden.bs.modal', function() {
                    isHistoryModalOpen = false;
                });
            }

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    if (refreshTimer) {
                        clearTimeout(refreshTimer);
                        refreshTimer = null;
                    }
                    return;
                }

                refreshRfidStatus(false);
                scheduleNextRefresh();
            });

            scheduleNextRefresh();
        });
    </script>
@stop
