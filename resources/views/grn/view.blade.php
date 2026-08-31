@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ trans('admin/grn/table.view') }} -
  {{ $grn->grn_number }}
  @parent
@stop

@section('header_right')
    @if ($grn->status === \App\Models\Grn::STATUS_DRAFT)
        @can('post', $grn)
            <form method="POST" action="{{ route('grn.post', $grn->id) }}" style="display:inline-block; margin-right: 10px;">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('{{ trans('admin/grn/table.post_confirm') }}')">{{ trans('admin/grn/table.post') }}</button>
            </form>
        @endcan
        @can('update', $grn)
            <form method="POST" action="{{ route('grn.cancel', $grn->id) }}" style="display:inline-block; margin-right: 10px;">
                @csrf
                <button type="submit" class="btn btn-danger" onclick="return confirm('{{ trans('admin/grn/table.cancel_confirm') }}')">{{ trans('admin/grn/table.cancel') }}</button>
            </form>
        @endcan
    @endif

    <a href="{{ route('purchase-orders.show', $grn->purchase_order_id) }}" class="btn btn-default pull-right" style="margin-right: 10px;">{{ trans('admin/purchase_orders/table.view') }}</a>
    <a href="{{ route('grn.index') }}" class="btn btn-primary text-right" style="margin-right: 10px;">{{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')

  <div class="row">
    <div class="col-md-9">

      <div class="nav-tabs-custom">
        <ul class="nav nav-tabs hidden-print">
          <li class="active">
            <a href="#lines" data-toggle="tab">
                <span class="hidden-xs hidden-sm">
                    {{ trans('admin/grn/table.line_items') }}
                    {!! ($grn->lines->count() > 0 ) ? '<badge class="badge badge-secondary">'.number_format($grn->lines->count()).'</badge>' : '' !!}
                </span>
            </a>
          </li>
          @if ($grn->status === \App\Models\Grn::STATUS_POSTED)
          <li>
            <a href="#assets" data-toggle="tab">
                <span class="hidden-xs hidden-sm">
                    {{ trans('admin/grn/table.assets_created') }}
                    {!! ($grn->assets->count() > 0 ) ? '<badge class="badge badge-secondary">'.number_format($grn->assets->count()).'</badge>' : '' !!}
                </span>
            </a>
          </li>
          @endif
        </ul>

        <div class="tab-content">
          <div class="tab-pane active" id="lines">
            <h2 class="box-title">{{ trans('admin/grn/table.line_items') }}</h2>

            <table class="table table-striped">
              <thead>
                <tr>
                  <th>{{ trans('admin/grn/table.item') }}</th>
                  <th>{{ trans('admin/grn/table.qty_received') }}</th>
                  <th>{{ trans('admin/grn/table.unit_cost') }}</th>
                  <th>{{ trans('admin/grn/table.serials') }}</th>
                  @if ($grn->linesAreEditable())
                    <th>{{ trans('table.actions') }}</th>
                  @endif
                </tr>
              </thead>
              <tbody>
                @forelse ($grn->lines as $line)
                  <tr>
                    <td>{{ $line->item?->name ?: '—' }}</td>
                    <td>{{ $line->qty_received }}</td>
                    <td>{{ $line->unit_cost !== null ? number_format((float) $line->unit_cost, 2) : '(PO line cost)' }}</td>
                    <td>{{ !empty($line->serials) ? implode(', ', $line->serials) : '—' }}</td>
                    @if ($grn->linesAreEditable())
                      <td>
                        <form method="POST" action="{{ route('grn.lines.destroy', ['grn_id' => $grn->id, 'line_id' => $line->id]) }}" style="display:inline">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ trans('general.delete_confirm', ['item' => trans('admin/grn/table.line_items')]) }}')"><x-icon type="delete" /></button>
                        </form>
                      </td>
                    @endif
                  </tr>
                @empty
                  <tr><td colspan="5">{{ trans('admin/grn/table.no_line_items') }}</td></tr>
                @endforelse
              </tbody>
            </table>

            @if ($grn->linesAreEditable())
              @can('update', $grn)
                <hr>
                <h4>{{ trans('admin/grn/table.add_line_item') }}</h4>

                @if ($openLines->isEmpty())
                  <p class="text-muted">{{ trans('admin/grn/table.no_open_lines') }}</p>
                @else
                  <form method="POST" action="{{ route('grn.lines.store', ['grn_id' => $grn->id]) }}" class="form-horizontal">
                    @csrf
                    <div class="form-group">
                      <label class="col-md-2 control-label">{{ trans('admin/grn/table.po_line') }}</label>
                      <div class="col-md-6">
                        <select name="purchase_order_line_id" class="form-control" required>
                          <option value="">{{ trans('general.select') }}</option>
                          @foreach ($openLines as $openLine)
                            <option value="{{ $openLine->id }}">
                                {{ $openLine->item?->name ?: '#'.$openLine->item_id }}
                                ({{ trans('admin/grn/table.qty_remaining') }}: {{ $openLine->qty_ordered - $openLine->qty_received }} {{ trans('admin/grn/table.qty_ordered') }}: {{ $openLine->qty_ordered }}, {{ trans('admin/grn/table.qty_already_received') }}: {{ $openLine->qty_received }})
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <label class="col-md-1 control-label">{{ trans('admin/grn/table.qty_received') }}</label>
                      <div class="col-md-2">
                        <input type="number" name="qty_received" class="form-control" min="1" value="1" required>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-2 control-label">{{ trans('admin/grn/table.unit_cost') }}</label>
                      <div class="col-md-3">
                        <input type="number" step="0.01" name="unit_cost" class="form-control" min="0" placeholder="{{ trans('admin/grn/table.unit_cost_help') }}">
                      </div>
                      <label class="col-md-2 control-label">{{ trans('admin/grn/table.serials') }}</label>
                      <div class="col-md-5">
                        <textarea name="serials" class="form-control" rows="2" placeholder="{{ trans('admin/grn/table.serials_help') }}"></textarea>
                      </div>
                    </div>
                    <div class="form-group">
                      <div class="col-md-offset-2 col-md-4">
                        <button type="submit" class="btn btn-primary">{{ trans('admin/grn/table.add_line_item') }}</button>
                      </div>
                    </div>
                  </form>
                @endif
              @endcan
            @else
              <p class="text-muted">{{ trans('admin/grn/table.lines_locked_help') }}</p>
            @endif
          </div><!-- /.tab-pane -->

          @if ($grn->status === \App\Models\Grn::STATUS_POSTED)
          <div class="tab-pane" id="assets">
            <h2 class="box-title">{{ trans('admin/grn/table.assets_created') }}</h2>

            {{-- This tab used to embed the full Assets-index table
                 (AssetPresenter::dataTableLayout(), ~20 columns: RFID,
                 barcode, requestable, checked-out-to, etc.) via the
                 general-purpose Assets API. Nearly all of that is either
                 blank or irrelevant right after a receipt, and it forced
                 a wide horizontally-scrolling table for what's usually a
                 handful of rows. Swapped for a small, purpose-built table
                 -- same plain <table> style as the Line Items tab above --
                 showing only what's useful to confirm right after posting:
                 the name, tag, model/category, serial, and status of each
                 unit this GRN just created. --}}
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>{{ trans('admin/hardware/form.name') }}</th>
                  <th>{{ trans('admin/hardware/table.asset_tag') }}</th>
                  <th>{{ trans('admin/hardware/form.model') }}</th>
                  <th>{{ trans('general.category') }}</th>
                  <th>{{ trans('admin/hardware/form.serial') }}</th>
                  <th>{{ trans('admin/hardware/table.status') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($grn->assets as $asset)
                  <tr>
                    <td><a href="{{ route('hardware.show', ['hardware' => $asset->id]) }}">{{ $asset->name ?: $asset->asset_tag }}</a></td>
                    <td>{{ $asset->asset_tag }}</td>
                    <td>{{ $asset->model?->name ?: '—' }}</td>
                    <td>{{ $asset->model?->category?->name ?: '—' }}</td>
                    <td>{{ $asset->serial ?: '—' }}</td>
                    <td>{{ $asset->assetstatus?->name ?: '—' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6">{{ trans('admin/grn/table.no_line_items') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div><!-- /.tab-pane -->
          @endif
        </div>
      </div><!--/.nav-tabs-custom-->
    </div><!--/.col-md-9-->

    <!-- side details column -->
    <div class="col-md-3">
      <ul class="list-unstyled" style="line-height: 25px; padding-bottom: 20px; padding-top: 20px;">
        <li><strong>{{ trans('admin/grn/table.grn_number') }}:</strong> {{ $grn->grn_number ?: '—' }}</li>
        <li><strong>{{ trans('admin/grn/table.status') }}:</strong> {{ $grn->statusLabel() }}</li>
        <li>
            <strong>{{ trans('admin/grn/table.purchase_order') }}:</strong>
            <a href="{{ route('purchase-orders.show', $grn->purchase_order_id) }}">{{ $grn->purchaseOrder->custom_po_id ?: '#'.$grn->purchase_order_id }}</a>
        </li>
        <li>
            <strong>{{ trans('admin/grn/table.location') }}:</strong>
            {{ $grn->location?->name ?: '—' }}
        </li>
        <li>
            <strong>{{ trans('admin/grn/table.default_status') }}:</strong>
            {{ $grn->defaultStatus?->name ?: trans('general.select').' ('.trans('admin/grn/table.default_status_help').')' }}
        </li>
        <li>
            <strong>{{ trans('admin/grn/table.received_by') }}:</strong>
            @if ($grn->receivedBy)
                <a href="{{ route('users.show', $grn->receivedBy->id) }}">{{ $grn->receivedBy->present()->fullName }}</a>
            @else
                —
            @endif
        </li>
        <li><strong>{{ trans('admin/grn/table.received_date') }}:</strong> {{ optional($grn->received_date)->format('Y-m-d') ?: '—' }}</li>
        @if ($grn->posted_at)
          <li><strong>{{ trans('admin/grn/table.posted_at') }}:</strong> {{ $grn->posted_at->format('Y-m-d H:i') }}</li>
        @endif
        @if ($grn->notes)
          <li><strong>{{ trans('admin/grn/table.notes') }}:</strong> {{ $grn->notes }}</li>
        @endif
      </ul>
    </div><!--/col-md-3-->
  </div>

@stop

@section('moar_scripts')
  @include ('partials.bootstrap-table', [
      'exportFile' => 'grn-export',
      'search' => true
   ])
@stop
