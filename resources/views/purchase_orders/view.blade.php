@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ trans('admin/purchase_orders/table.view') }} -
  {{ $po->po_name ?: $po->custom_po_id }}
  @parent
@stop

@section('header_right')
    @can('update', $po)
        <a href="{{ route('purchase-orders.edit', $po->id) }}" class="btn btn-default pull-right">
            {{ trans('admin/purchase_orders/table.update') }}</a>
    @endcan

    <a href="{{ route('purchase-orders.index') }}" class="btn btn-primary text-right" style="margin-right: 10px;">{{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')

  <div class="row">
    <div class="col-md-9">

      <div class="nav-tabs-custom">
        <ul class="nav nav-tabs hidden-print">
          <li class="active">
            <a href="#assets" data-toggle="tab">
                <span class="hidden-lg hidden-md">
                    <x-icon type="assets" class="fa-2x" />
                </span>
                <span class="hidden-xs hidden-sm">
                    {{ trans('admin/purchase_orders/table.assets') }}
                    {!! ($po->assets->count() > 0 ) ? '<badge class="badge badge-secondary">'.number_format($po->assets->count()).'</badge>' : '' !!}
                </span>
            </a>
          </li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane active" id="assets">
            <h2 class="box-title">{{ trans('admin/purchase_orders/table.assets') }}</h2>

            <p class="text-muted">
                {{-- Once GRN Inward exists, assets received against this PO will be created here automatically,
                     one per unit, each carrying this PO's id in assets.purchase_order_id. --}}
                Assets received against this Purchase Order.
            </p>

            <div class="table table-responsive">
              <table
                      data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout() }}"
                      data-cookie-id-table="purchaseOrderAssetsTable"
                      data-pagination="true"
                      data-id-table="purchaseOrderAssetsTable"
                      data-search="true"
                      data-side-pagination="server"
                      data-show-columns="true"
                      data-show-fullscreen="true"
                      data-show-export="true"
                      data-show-refresh="true"
                      data-sort-order="asc"
                      id="purchaseOrderAssetsTable"
                      class="table table-striped snipe-table"
                      data-url="{{ route('api.assets.index', ['purchase_order_id' => $po->id]) }}"
                      data-export-options='{
                              "fileName": "export-po-{{ $po->custom_po_id ?: $po->id }}-assets-{{ date('Y-m-d') }}",
                              "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                              }'>
              </table>
            </div><!-- /.table-responsive -->
          </div><!-- /.tab-pane -->
        </div>
      </div><!--/.nav-tabs-custom-->
    </div><!--/.col-md-9-->

    <!-- side details column -->
    <div class="col-md-3">
      <ul class="list-unstyled" style="line-height: 25px; padding-bottom: 20px; padding-top: 20px;">
        <li><strong>{{ trans('admin/purchase_orders/table.po_number') }}:</strong> {{ $po->custom_po_id ?: '—' }}</li>
        <li><strong>{{ trans('admin/purchase_orders/table.status') }}:</strong> {{ $po->status_name ?: '—' }}</li>
        <li>
            <strong>{{ trans('admin/purchase_orders/table.supplier') }}:</strong>
            @if ($po->vendor)
                <a href="{{ route('suppliers.show', $po->vendor->id) }}">{{ $po->vendor->name }}</a>
            @else
                —
            @endif
        </li>
        <li>
            <strong>{{ trans('admin/purchase_orders/table.requested_by') }}:</strong>
            @if ($po->requestedBy)
                <a href="{{ route('users.show', $po->requestedBy->id) }}">{{ $po->requestedBy->present()->fullName }}</a>
            @else
                —
            @endif
        </li>
        <li>
            <strong>{{ trans('admin/purchase_orders/table.owner') }}:</strong>
            @if ($po->owner)
                <a href="{{ route('users.show', $po->owner->id) }}">{{ $po->owner->present()->fullName }}</a>
            @else
                —
            @endif
        </li>
        <li><strong>{{ trans('admin/purchase_orders/table.total_price') }}:</strong> {{ $po->currency_code }} {{ number_format((float) $po->total_price, 2) }}</li>
        <li><strong>{{ trans('admin/purchase_orders/table.created_date') }}:</strong> {{ optional($po->created_date)->format('Y-m-d') ?: '—' }}</li>
        <li><strong>{{ trans('admin/purchase_orders/table.required_date') }}:</strong> {{ optional($po->required_date)->format('Y-m-d') ?: '—' }}</li>
      </ul>
    </div><!--/col-md-3-->
  </div>

@stop

@section('moar_scripts')
  @include ('partials.bootstrap-table', [
      'exportFile' => 'purchase-orders-export',
      'search' => true
   ])
@stop
