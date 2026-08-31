@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/items/table.items') }}
@parent
@stop

{{-- Page content --}}
@section('content')

@section('header_right')
  @can('create', \App\Models\Item::class)
    <a href="{{ route('items.create') }}" class="btn btn-primary pull-right"> {{ trans('general.create') }}</a>
  @endcan
@stop

<div class="row">
  <div class="col-md-12">
    <div class="box box-default">
      <div class="box-body">
        <table
            data-cookie-id-table="itemsTable"
            data-pagination="true"
            data-id-table="itemsTable"
            data-search="true"
            data-side-pagination="server"
            data-show-columns="true"
            data-show-fullscreen="true"
            data-show-export="true"
            data-show-refresh="true"
            data-sort-order="asc"
            data-sort-name="name"
            id="itemsTable"
            class="table table-striped snipe-table"
            data-url="{{ route('api.items.index') }}"
            data-export-options='{
            "fileName": "export-items-{{ date('Y-m-d') }}",
            "ignoreColumn": ["actions","checkbox"]
            }'>
        <thead>
          <tr>
            <th data-sortable="true" data-field="id" data-visible="false">{{ trans('admin/items/table.id') }}</th>
            <th data-sortable="true" data-field="item_code" data-formatter="itemsLinkFormatter">{{ trans('admin/items/table.item_code') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="name" data-formatter="itemsLinkFormatter">{{ trans('admin/items/table.name') }}</th>
            <th data-sortable="true" data-field="item_type_label">{{ trans('admin/items/table.item_type') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="category" data-formatter="categoriesLinkObjFormatter">{{ trans('admin/items/table.category') }}</th>
            <th data-sortable="true" data-searchable="true" data-field="manufacturer" data-formatter="manufacturersLinkObjFormatter">{{ trans('admin/items/table.manufacturer') }}</th>
            <th data-sortable="true" data-field="default_unit_cost">{{ trans('admin/items/table.default_unit_cost') }}</th>
            <th data-sortable="true" data-field="is_active">{{ trans('admin/items/table.is_active') }}</th>
            <th data-switchable="false" data-formatter="itemsActionsFormatter" data-searchable="false" data-sortable="false" data-field="actions">{{ trans('table.actions') }}</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
  </div>
</div>
@stop

@section('moar_scripts')
@include ('partials.bootstrap-table', ['exportFile' => 'items-export', 'search' => true])

  {{-- partials.bootstrap-table only auto-generates {module}LinkFormatter /
       {module}ActionsFormatter globals for the modules listed in its own
       'formatters' array. 'items' isn't in that shared list (it's a Snipe-IT
       core file used by ~19 other modules), so we register the two
       formatters this table's data-formatter attributes reference directly,
       using the same factory functions the shared file itself uses. --}}
  <script nonce="{{ csrf_token() }}">
      window.itemsLinkFormatter = genericRowLinkFormatter('items');
      window.itemsActionsFormatter = genericActionsFormatter('items');
  </script>
@stop
