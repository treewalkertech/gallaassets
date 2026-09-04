@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/grn/table.grns') }}
@parent
@stop

@section('header_right')
  {{-- A GRN can't be created standalone -- it always belongs to a specific
       Purchase Order (see the "Receive Goods (GRN)" button on a PO's own
       page). This just gets people to that starting point instead of
       leaving them on an index page with no way forward. --}}
  <a href="{{ route('purchase-orders.index') }}" class="btn btn-primary pull-right">{{ trans('admin/grn/table.browse_pos') }}</a>
@stop

{{-- Page content --}}
@section('content')

<div class="row">
  <div class="col-md-12">
    <p class="text-muted">{{ trans('admin/grn/table.start_from_po') }}</p>
    <div class="box box-default">
      <div class="box-body">
        <table
            data-cookie-id-table="grnTable"
            data-pagination="true"
            data-id-table="grnTable"
            data-search="true"
            data-side-pagination="server"
            data-show-columns="true"
            data-show-fullscreen="true"
            data-show-export="true"
            data-show-refresh="true"
            data-sort-order="desc"
            data-sort-name="id"
            id="grnTable"
            class="table table-striped snipe-table"
            data-url="{{ route('api.grn.index') }}"
            data-export-options='{
            "fileName": "export-grn-{{ date('Y-m-d') }}",
            "ignoreColumn": ["actions","checkbox"]
            }'>
        <thead>
          <tr>
            <th data-sortable="true" data-field="id" data-visible="false">{{ trans('admin/grn/table.id') }}</th>
            <th data-sortable="true" data-field="grn_number" data-formatter="grnLinkFormatter">{{ trans('admin/grn/table.grn_number') }}</th>
            <th data-sortable="false" data-searchable="true" data-field="purchase_order.custom_po_id">{{ trans('admin/grn/table.purchase_order') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="location">{{ trans('admin/grn/table.location') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="received_by">{{ trans('admin/grn/table.received_by') }}</th>
            <th data-sortable="true" data-field="status_label">{{ trans('admin/grn/table.status') }}</th>
            <th data-sortable="true" data-field="received_date">{{ trans('admin/grn/table.received_date') }}</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'grn-export', 'search' => true])

  {{-- See items/index.blade.php for why this is registered here rather
       than in the shared partials.bootstrap-table 'formatters' list. --}}
  <script nonce="{{ csrf_token() }}">
      window.grnLinkFormatter = genericRowLinkFormatter('grn');
  </script>
@stop
