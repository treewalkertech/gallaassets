@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/inventory/table.inventory') }}
@parent
@stop

{{-- Page content --}}
@section('content')

<div class="row">
  <div class="col-md-12">
    <div class="box box-default">
      <div class="box-body">
        <p class="text-muted">{{ trans('admin/inventory/table.about_text') }}</p>

        <table
            data-cookie-id-table="inventoryTable"
            data-pagination="true"
            data-id-table="inventoryTable"
            data-search="true"
            data-side-pagination="server"
            data-show-columns="true"
            data-show-fullscreen="true"
            data-show-export="true"
            data-show-refresh="true"
            data-sort-order="asc"
            data-sort-name="name"
            id="inventoryTable"
            class="table table-striped snipe-table"
            data-url="{{ route('api.inventory.index') }}"
            data-export-options='{
            "fileName": "export-inventory-{{ date('Y-m-d') }}",
            "ignoreColumn": ["actions","checkbox"]
            }'>
        <thead>
          <tr>
            <th data-sortable="true" data-field="id" data-visible="false">{{ trans('admin/items/table.id') }}</th>
            <th data-sortable="true" data-field="item_code" data-formatter="inventoryItemLinkFormatter">{{ trans('admin/inventory/table.item_code') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="name" data-formatter="inventoryItemLinkFormatter">{{ trans('admin/inventory/table.name') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="asset_model" data-formatter="modelsLinkObjFormatter">{{ trans('admin/inventory/table.asset_model') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="category" data-formatter="categoriesLinkObjFormatter">{{ trans('admin/inventory/table.category') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="manufacturer" data-formatter="manufacturersLinkObjFormatter">{{ trans('admin/inventory/table.manufacturer') }}</th>
            <th data-sortable="true" data-field="total_received">{{ trans('admin/inventory/table.total_received') }}</th>
            <th data-sortable="true" data-field="discarded_count" data-formatter="inventoryDiscardedFormatter">{{ trans('admin/inventory/table.discarded_lost') }}</th>
            <th data-sortable="true" data-field="available_count" data-formatter="inventoryAvailableFormatter">{{ trans('admin/inventory/table.available') }}</th>
            <th data-sortable="true" data-field="reorder_level">{{ trans('admin/inventory/table.reorder_level') }}</th>
          </tr>
        </thead>
      </table>
      </div>
    </div>
  </div>
</div>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'inventory-export', 'search' => true])

  {{-- Same reasoning as items/index.blade.php: 'inventory' isn't in
       partials.bootstrap-table's own shared 'formatters' array (a Snipe-IT
       core file used by ~19 other modules), so the formatters this table's
       columns reference are registered directly here. There's no dedicated
       "view inventory row" page -- each Item Name/Code links back to that
       Item's own detail page instead, reusing the 'items' route the same
       way items/index.blade.php's own link formatter does. --}}
  <script nonce="{{ csrf_token() }}">
      window.inventoryItemLinkFormatter = genericRowLinkFormatter('items');

      window.inventoryAvailableFormatter = function (value, row) {
          var cls = row.low_stock ? 'label label-danger' : 'label label-success';
          var title = row.low_stock ? '{{ trans('admin/inventory/table.low_stock') }}' : '';
          return '<span class="' + cls + '" title="' + title + '">' + value + '</span>';
      };

      window.inventoryDiscardedFormatter = function (value, row) {
          if (!value) {
              return value;
          }
          return '<span class="label label-default">' + value + '</span>';
      };
  </script>
@stop
