@extends('layouts/edit-form', [
    'createText' => trans('admin/grn/table.create'),
    'helpTitle' => trans('admin/grn/table.about_grn_title'),
    'helpText' => trans('admin/grn/table.about_grn_text'),
    'topSubmit' => true,
    'formAction' => route('purchase-orders.grn.store', ['purchase_order_id' => $po->id]),
])

{{-- Page content --}}
@section('inputFields')

<div class="form-group">
    <label class="col-md-3 control-label">{{ trans('admin/grn/table.purchase_order') }}</label>
    <div class="col-md-7">
        <p class="form-control-static">
            {{ $po->custom_po_id ?: '—' }}@if ($po->po_name) &mdash; {{ $po->po_name }} @endif
        </p>
    </div>
</div>

@include ('partials.forms.edit.location-select', ['translated_name' => trans('admin/grn/table.location'), 'fieldname' => 'location_id'])

@include ('partials.forms.edit.status-select', ['translated_name' => trans('admin/grn/table.default_status'), 'fieldname' => 'default_status_id'])
<div class="form-group">
    <div class="col-md-7 col-md-offset-3">
        <p class="help-block">{{ trans('admin/grn/table.default_status_help') }}</p>
    </div>
</div>

<!-- Received Date -->
<div class="form-group {{ $errors->has('received_date') ? ' has-error' : '' }}">
   <label for="received_date" class="col-md-3 control-label">{{ trans('admin/grn/table.received_date') }}</label>
   <div class="input-group col-md-4">
        <div class="input-group date" data-provide="datepicker" data-date-clear-btn="true" data-date-format="yyyy-mm-dd" data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="received_date" id="received_date" readonly value="{{ old('received_date', date('Y-m-d')) }}" style="background-color:inherit">
            <span class="input-group-addon"><x-icon type="calendar" /></span>
       </div>
       {!! $errors->first('received_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
   </div>
</div>

<!-- Notes -->
<div class="form-group {{ $errors->has('notes') ? ' has-error' : '' }}">
    {{ Form::label('notes', trans('admin/grn/table.notes'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::textarea('notes', old('notes'), array('class' => 'form-control', 'rows' => 3)) }}
        {!! $errors->first('notes', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@stop
