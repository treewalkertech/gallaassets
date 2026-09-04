@extends('layouts/default')

{{-- Page title --}}
@section('title')
  {{ trans('admin/items/table.view') }} -
  {{ $item->name }}
  @parent
@stop

@section('header_right')
    @can('update', $item)
        <a href="{{ route('items.edit', $item->id) }}" class="btn btn-default pull-right">
            {{ trans('admin/items/table.update') }}</a>
    @endcan

    <a href="{{ route('items.index') }}" class="btn btn-primary text-right" style="margin-right: 10px;">{{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')

  <div class="row">
    <div class="col-md-12">
      <div class="box box-default">
        <div class="box-body">
          <ul class="list-unstyled" style="line-height: 25px; padding-bottom: 20px; padding-top: 20px;">
            <li><strong>{{ trans('admin/items/table.item_code') }}:</strong> {{ $item->item_code ?: '—' }}</li>
            <li><strong>{{ trans('admin/items/table.item_type') }}:</strong> {{ $item->typeLabel() }}</li>
            <li>
                <strong>{{ trans('admin/items/table.category') }}:</strong>
                {{ $item->category->name ?? '—' }}
            </li>
            <li>
                <strong>{{ trans('admin/items/table.manufacturer') }}:</strong>
                {{ $item->manufacturer->name ?? '—' }}
            </li>
            <li><strong>{{ trans('admin/items/table.uom') }}:</strong> {{ $item->uom ?: '—' }}</li>

            @if ($item->item_type === \App\Models\Item::TYPE_FIXED_ASSET)
                <li>
                    <strong>{{ trans('admin/items/table.asset_model') }}:</strong>
                    @if ($item->assetModel)
                        <a href="{{ route('models.show', $item->assetModel->id) }}">{{ $item->assetModel->name }}</a>
                    @else
                        <span class="text-red">Not linked yet — set this before raising a PO against this item.</span>
                    @endif
                </li>
            @elseif ($item->item_type === \App\Models\Item::TYPE_CONSUMABLE)
                <li>
                    <strong>{{ trans('admin/items/table.consumable') }}:</strong>
                    @if ($item->consumable)
                        <a href="{{ route('consumables.show', $item->consumable->id) }}">{{ $item->consumable->name }}</a>
                        ({{ $item->consumable->qty }} in stock)
                    @else
                        <span class="text-red">Not linked yet — set this before raising a PO against this item.</span>
                    @endif
                </li>
            @elseif ($item->item_type === \App\Models\Item::TYPE_LICENSE)
                <li>
                    <strong>{{ trans('admin/items/table.license') }}:</strong>
                    @if ($item->license)
                        <a href="{{ route('licenses.show', $item->license->id) }}">{{ $item->license->name }}</a>
                        ({{ $item->license->seats }} seats)
                    @else
                        <span class="text-red">Not linked yet — set this before raising a PO against this item.</span>
                    @endif
                </li>
            @endif

            <li><strong>{{ trans('admin/items/table.default_unit_cost') }}:</strong> {{ $item->default_unit_cost !== null ? number_format((float) $item->default_unit_cost, 2) : '—' }}</li>
            @if ($item->item_type === \App\Models\Item::TYPE_CONSUMABLE)
                <li><strong>{{ trans('admin/items/table.reorder_level') }}:</strong> {{ $item->reorder_level ?? '—' }}</li>
            @endif
            <li><strong>{{ trans('admin/items/table.is_active') }}:</strong> {{ $item->is_active ? 'Yes' : 'No' }}</li>
            @if ($item->notes)
                <li><strong>{{ trans('admin/items/table.notes') }}:</strong> {!! nl2br(Helper::parseEscapedMarkedownInline($item->notes)) !!}</li>
            @endif
          </ul>
        </div>
      </div>
    </div>
  </div>

  @if ($item->item_type === \App\Models\Item::TYPE_FIXED_ASSET)
    <div class="row">
      <div class="col-md-12">
        <div class="box box-default">
          <div class="box-body">
            <h2 class="box-title">{{ trans('admin/items/table.assets') }}</h2>
            <p class="text-muted">{{ trans('admin/items/table.assets_help') }}</p>

            <div class="table table-responsive">
              <table
                      data-columns="{{ \App\Presenters\AssetPresenter::dataTableLayout() }}"
                      data-cookie-id-table="itemAssetsTable"
                      data-pagination="true"
                      data-id-table="itemAssetsTable"
                      data-search="true"
                      data-side-pagination="server"
                      data-show-columns="true"
                      data-show-fullscreen="true"
                      data-show-export="true"
                      data-show-refresh="true"
                      data-sort-order="asc"
                      id="itemAssetsTable"
                      class="table table-striped snipe-table"
                      data-url="{{ route('api.assets.index', ['item_id' => $item->id]) }}"
                      data-export-options='{
                              "fileName": "export-item-{{ $item->item_code ?: $item->id }}-assets-{{ date('Y-m-d') }}",
                              "ignoreColumn": ["actions","image","change","checkbox","checkincheckout","icon"]
                              }'>
              </table>
            </div><!-- /.table-responsive -->
          </div>
        </div>
      </div>
    </div>
  @endif

@stop

@section('moar_scripts')
  @if ($item->item_type === \App\Models\Item::TYPE_FIXED_ASSET)
    @include ('partials.bootstrap-table', ['search' => true])
  @endif
@stop
