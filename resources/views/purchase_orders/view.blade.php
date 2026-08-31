@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ trans('admin/purchase_orders/table.view') }} -
  {{ $po->po_name ?: $po->custom_po_id }}
  @parent
@stop

@section('header_right')
    @can('update', $po)
        @if ($po->status === \App\Models\PurchaseOrder::STATUS_DRAFT || $po->status === null)
            <form method="POST" action="{{ route('purchase-orders.submit', $po->id) }}" style="display:inline-block; margin-right: 10px;">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('{{ trans('admin/purchase_orders/table.submit_confirm') }}')">{{ trans('admin/purchase_orders/table.submit_for_approval') }}</button>
            </form>
        @endif
    @endcan

    @can('approve', $po)
        @if ($po->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
            <form method="POST" action="{{ route('purchase-orders.approve', $po->id) }}" style="display:inline-block; margin-right: 10px;">
                @csrf
                <button type="submit" class="btn btn-success">{{ trans('admin/purchase_orders/table.approve') }}</button>
            </form>
            <form method="POST" action="{{ route('purchase-orders.reject', $po->id) }}" style="display:inline-block; margin-right: 10px;">
                @csrf
                <button type="submit" class="btn btn-danger" onclick="return confirm('{{ trans('admin/purchase_orders/table.reject_confirm') }}')">{{ trans('admin/purchase_orders/table.reject') }}</button>
            </form>
        @endif
    @endcan

    @can('create', \App\Models\Grn::class)
        @if ($po->canReceiveGrn())
            <a href="{{ route('purchase-orders.grn.create', $po->id) }}" class="btn btn-success" style="margin-right: 10px;">
                {{ trans('admin/grn/table.receive_goods') }}</a>
        @endif
    @endcan

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

      @if ($po->status === \App\Models\PurchaseOrder::STATUS_DRAFT || $po->status === null)
        <p class="text-muted">{{ trans('admin/purchase_orders/table.draft_help') }}</p>
      @elseif ($po->status === \App\Models\PurchaseOrder::STATUS_PENDING_APPROVAL)
        <p class="text-muted">{{ trans('admin/purchase_orders/table.pending_approval_help', ['approver' => $po->approver?->present()->fullName ?: 'an approver']) }}</p>
      @endif

      <div class="nav-tabs-custom">
        <ul class="nav nav-tabs hidden-print">
          <li class="active">
            <a href="#lines" data-toggle="tab">
                <span class="hidden-xs hidden-sm">
                    {{ trans('admin/purchase_orders/table.line_items') }}
                    {!! ($po->lines->count() > 0 ) ? '<badge class="badge badge-secondary">'.number_format($po->lines->count()).'</badge>' : '' !!}
                </span>
            </a>
          </li>
          <li>
            <a href="#grns" data-toggle="tab">
                <span class="hidden-xs hidden-sm">
                    {{ trans('admin/grn/table.goods_receipts') }}
                    {!! ($po->grns->count() > 0 ) ? '<badge class="badge badge-secondary">'.number_format($po->grns->count()).'</badge>' : '' !!}
                </span>
            </a>
          </li>
          <li>
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
          <div class="tab-pane active" id="lines">
            <h2 class="box-title">{{ trans('admin/purchase_orders/table.line_items') }}</h2>

            <table class="table table-striped">
              <thead>
                <tr>
                  <th>{{ trans('admin/purchase_orders/table.item') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.description') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.qty_ordered') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.qty_received') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.unit_cost') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.discount_amount') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.tax_amount') }}</th>
                  <th>{{ trans('admin/purchase_orders/table.line_total') }}</th>
                  @if ($po->linesAreEditable())
                    <th>{{ trans('table.actions') }}</th>
                  @endif
                </tr>
              </thead>
              <tbody>
                @forelse ($po->lines as $line)
                  <tr>
                    <td>{{ $line->item?->name ?: '—' }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->qty_ordered }}</td>
                    <td>{{ $line->qty_received }}</td>
                    <td>{{ number_format((float) $line->unit_cost, 2) }}</td>
                    <td>{{ number_format((float) $line->discount_amount, 2) }}</td>
                    <td>{{ number_format((float) $line->tax_amount, 2) }}</td>
                    <td>{{ number_format((float) $line->line_total, 2) }}</td>
                    @if ($po->linesAreEditable())
                      <td>
                        <form method="POST" action="{{ route('purchase-orders.lines.destroy', ['purchase_order_id' => $po->id, 'line_id' => $line->id]) }}" style="display:inline">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ trans('general.delete_confirm', ['item' => trans('admin/purchase_orders/table.line_items')]) }}')"><x-icon type="delete" /></button>
                        </form>
                      </td>
                    @endif
                  </tr>
                @empty
                  <tr><td colspan="9">{{ trans('admin/purchase_orders/table.no_line_items') }}</td></tr>
                @endforelse
              </tbody>
            </table>

            @if ($po->linesAreEditable())
              @can('update', $po)
                <hr>
                <h4>{{ trans('admin/purchase_orders/table.add_line_item') }}</h4>
                <form method="POST" action="{{ route('purchase-orders.lines.store', ['purchase_order_id' => $po->id]) }}" class="form-horizontal add-line-item-form">
                  @csrf
                  <div class="form-group">
                    <label class="col-md-2 control-label">{{ trans('admin/purchase_orders/table.item') }}</label>
                    <div class="col-md-6">
                      <select class="js-data-ajax" data-endpoint="items" data-placeholder="{{ trans('general.select') }}" name="item_id" style="width: 100%" required>
                        <option value="" role="option">{{ trans('general.select') }}</option>
                      </select>
                    </div>
                    <label class="col-md-2 control-label">{{ trans('admin/purchase_orders/table.qty_ordered') }}</label>
                    <div class="col-md-2">
                      <input type="number" name="qty_ordered" class="form-control" min="1" value="1" required>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="col-md-2 control-label">{{ trans('admin/purchase_orders/table.description') }}</label>
                    <div class="col-md-6">
                      <input type="text" name="description" class="form-control">
                    </div>
                    <label class="col-md-2 control-label">{{ trans('admin/purchase_orders/table.unit_cost') }}</label>
                    <div class="col-md-2">
                      <input type="number" step="0.01" name="unit_cost" class="form-control" min="0" required>
                    </div>
                  </div>
                  <div class="form-group">
                    <div class="col-md-offset-2 col-md-2">
                      <label class="control-label">{{ trans('admin/purchase_orders/table.discount_amount') }}</label>
                      <input type="number" step="0.01" name="discount_amount" class="form-control" min="0">
                    </div>
                    <div class="col-md-2">
                      <label class="control-label">{{ trans('admin/purchase_orders/table.tax_amount') }}</label>
                      <input type="number" step="0.01" name="tax_amount" class="form-control" min="0">
                    </div>
                  </div>
                  <div class="form-group">
                    <div class="col-md-offset-2 col-md-4">
                      <button type="submit" class="btn btn-primary">{{ trans('admin/purchase_orders/table.add_line_item') }}</button>
                    </div>
                  </div>
                </form>

                {{-- The narrow columns these number fields used to sit in (col-md-1)
                     left barely enough room to see a typed value past the browser's
                     native up/down spinner -- widened above, and this drops the
                     spinner entirely so the full number is always visible. --}}
                <style nonce="{{ csrf_token() }}">
                    .add-line-item-form input[type="number"] {
                        -moz-appearance: textfield;
                    }
                    .add-line-item-form input[type="number"]::-webkit-outer-spin-button,
                    .add-line-item-form input[type="number"]::-webkit-inner-spin-button {
                        -webkit-appearance: none;
                        margin: 0;
                    }
                </style>
              @endcan
            @else
              <p class="text-muted">{{ trans('admin/purchase_orders/table.lines_locked_help') }}</p>
            @endif
          </div><!-- /.tab-pane -->

          <div class="tab-pane" id="grns">
            <h2 class="box-title">{{ trans('admin/grn/table.goods_receipts') }}</h2>

            <table class="table table-striped">
              <thead>
                <tr>
                  <th>{{ trans('admin/grn/table.grn_number') }}</th>
                  <th>{{ trans('admin/grn/table.status') }}</th>
                  <th>{{ trans('admin/grn/table.received_by') }}</th>
                  <th>{{ trans('admin/grn/table.received_date') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($po->grns as $poGrn)
                  <tr>
                    <td><a href="{{ route('grn.show', $poGrn->id) }}">{{ $poGrn->grn_number ?: '#'.$poGrn->id }}</a></td>
                    <td>{{ $poGrn->statusLabel() }}</td>
                    <td>{{ $poGrn->receivedBy?->present()->fullName ?: '—' }}</td>
                    <td>{{ optional($poGrn->received_date)->format('Y-m-d') ?: '—' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4">{{ trans('admin/grn/table.no_grns_yet') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div><!-- /.tab-pane -->

          <div class="tab-pane" id="assets">
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
        <li><strong>{{ trans('admin/purchase_orders/table.status') }}:</strong> {{ $po->statusLabel() }}</li>
        <li>
            <strong>{{ trans('admin/purchase_orders/table.approver') }}:</strong>
            @if ($po->approver)
                <a href="{{ route('users.show', $po->approver->id) }}">{{ $po->approver->present()->fullName }}</a>
            @else
                —
            @endif
            @if ($po->approved_at)
                <br><span class="text-muted">{{ trans('admin/purchase_orders/table.'.($po->approval_status === 'rejected' ? 'rejected_on' : 'approved_on')) }} {{ $po->approved_at->format('Y-m-d') }}</span>
            @endif
        </li>
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
