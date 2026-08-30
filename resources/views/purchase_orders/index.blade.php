@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/purchase_orders/table.purchase_orders') }}
@parent
@stop

{{-- Page content --}}
@section('content')

@section('header_right')
  @can('create', \App\Models\PurchaseOrder::class)
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary pull-right"> {{ trans('general.create') }}</a>
  @endcan
@stop

<div class="row">
  <div class="col-md-12">
    <div class="box box-default">
      <div class="box-body">
        <table
            data-cookie-id-table="purchaseOrdersTable"
            data-pagination="true"
            data-id-table="purchaseOrdersTable"
            data-search="true"
            data-side-pagination="server"
            data-show-columns="true"
            data-show-fullscreen="true"
            data-show-export="true"
            data-show-refresh="true"
            data-sort-order="desc"
            data-sort-name="created_date"
            id="purchaseOrdersTable"
            class="table table-striped snipe-table"
            data-url="{{ route('api.purchase-orders.index') }}"
            data-export-options='{
            "fileName": "export-purchase-orders-{{ date('Y-m-d') }}",
            "ignoreColumn": ["actions","checkbox"]
            }'>
        <thead>
          <tr>
            <th data-sortable="true" data-field="id" data-visible="false">{{ trans('admin/purchase_orders/table.id') }}</th>
            <th data-sortable="true" data-field="custom_po_id" data-formatter="purchaseOrdersLinkFormatter">{{ trans('admin/purchase_orders/table.po_number') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="po_name">{{ trans('admin/purchase_orders/table.po_name') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="vendor">{{ trans('admin/purchase_orders/table.supplier') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="requested_by">{{ trans('admin/purchase_orders/table.requested_by') }}</th>
            <th data-sortable="true" data-field="status_label">{{ trans('admin/purchase_orders/table.status') }}</th>
            <th data-sortable="true" data-field="total_price">{{ trans('admin/purchase_orders/table.total_price') }}</th>
            <th data-sortable="true" data-field="created_date">{{ trans('admin/purchase_orders/table.created_date') }}</th>
            <th data-switchable="false" data-formatter="purchaseOrdersActionsFormatter" data-searchable="false" data-sortable="false" data-field="actions">{{ trans('table.actions') }}</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
  </div>
</div>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'purchase-orders-export', 'search' => true])
@stop
