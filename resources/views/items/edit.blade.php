@extends('layouts/edit-form', [
    'createText' => trans('admin/items/table.create'),
    'updateText' => trans('admin/items/table.update'),
    'helpTitle' => trans('admin/items/table.about_items_title'),
    'helpText' => trans('admin/items/table.about_items_text'),
    'topSubmit' => true,
    'formAction' => (isset($item->id)) ? route('items.update', ['item' => $item->id]) : route('items.store'),
])

{{-- Page content --}}
@section('inputFields')

<!-- Item Code -->
<div class="form-group {{ $errors->has('item_code') ? ' has-error' : '' }}">
    {{ Form::label('item_code', trans('admin/items/table.item_code'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('item_code', old('item_code', $item->item_code), array('class' => 'form-control', 'placeholder' => 'Leave blank to auto-generate')) }}
        {!! $errors->first('item_code', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.name', ['translated_name' => trans('admin/items/table.name')])

<!-- Description -->
<div class="form-group {{ $errors->has('description') ? ' has-error' : '' }}">
    {{ Form::label('description', trans('admin/items/table.description'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::textarea('description', old('description', $item->description), array('class' => 'form-control', 'rows' => 3)) }}
        {!! $errors->first('description', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Item Type -->
<div class="form-group {{ $errors->has('item_type') ? ' has-error' : '' }}">
    {{ Form::label('item_type', trans('admin/items/table.item_type'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::select('item_type', \App\Models\Item::ITEM_TYPES, old('item_type', $item->item_type), array('class' => 'form-control', 'id' => 'item_type_select', 'required' => 'required')) }}
        {!! $errors->first('item_type', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        <p class="help-block">Choosing a type shows the matching field below it -- that's what this item receives into when a GRN is posted against it.</p>
    </div>
</div>

{{-- Category is scoped to 'assets' categories by default. If you're cataloging a
     consumable- or license-type item and don't see the right category, use the
     category type filter on the Categories screen, or pick the closest match --
     this will be made to switch automatically with item_type in a later pass. --}}
@include ('partials.forms.edit.category-select', ['translated_name' => trans('admin/items/table.category'), 'fieldname' => 'category_id', 'category_type' => 'assets'])

@include ('partials.forms.edit.manufacturer-select', ['translated_name' => trans('admin/items/table.manufacturer'), 'fieldname' => 'manufacturer_id'])

<!-- UOM -->
<div class="form-group {{ $errors->has('uom') ? ' has-error' : '' }}">
    {{ Form::label('uom', trans('admin/items/table.uom'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('uom', old('uom', $item->uom ?: 'EA'), array('class' => 'form-control', 'maxlength' => 20)) }}
        {!! $errors->first('uom', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<div id="fulfillment_fixed_asset">
    @include ('partials.forms.edit.model-select', ['translated_name' => trans('admin/items/table.asset_model'), 'fieldname' => 'asset_model_id'])
</div>

<div id="fulfillment_consumable">
    @include ('partials.forms.edit.consumable-select', ['translated_name' => trans('admin/items/table.consumable'), 'fieldname' => 'consumable_id'])
</div>

<div id="fulfillment_license">
    @include ('partials.forms.edit.license-select', ['translated_name' => trans('admin/items/table.license'), 'fieldname' => 'license_id'])
</div>

<!-- Default Unit Cost -->
<div class="form-group {{ $errors->has('default_unit_cost') ? ' has-error' : '' }}">
    {{ Form::label('default_unit_cost', trans('admin/items/table.default_unit_cost'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('default_unit_cost', old('default_unit_cost', $item->default_unit_cost), array('class' => 'form-control')) }}
        {!! $errors->first('default_unit_cost', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Reorder Level -->
<div class="form-group {{ $errors->has('reorder_level') ? ' has-error' : '' }}">
    {{ Form::label('reorder_level', trans('admin/items/table.reorder_level'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('reorder_level', old('reorder_level', $item->reorder_level), array('class' => 'form-control')) }}
        {!! $errors->first('reorder_level', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        <p class="help-block">Consumable items only -- used later to flag low stock.</p>
    </div>
</div>

<!-- Active -->
<div class="form-group {{ $errors->has('is_active') ? ' has-error' : '' }}">
    {{ Form::label('is_active', trans('admin/items/table.is_active'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        <label class="checkbox-inline">
            {{ Form::checkbox('is_active', '1', old('is_active', $item->exists ? $item->is_active : true)) }}
        </label>
        {!! $errors->first('is_active', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.notes')

@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    // Show only the fulfillment-target field (Asset Model / Consumable / License)
    // that matches the selected item type.
    (function () {
        function syncFulfillmentFields() {
            var type = document.getElementById('item_type_select').value;
            document.getElementById('fulfillment_fixed_asset').style.display = (type === 'fixed_asset') ? '' : 'none';
            document.getElementById('fulfillment_consumable').style.display = (type === 'consumable') ? '' : 'none';
            document.getElementById('fulfillment_license').style.display = (type === 'license') ? '' : 'none';
        }
        document.getElementById('item_type_select').addEventListener('change', syncFulfillmentFields);
        syncFulfillmentFields();
    })();
</script>
@stop
