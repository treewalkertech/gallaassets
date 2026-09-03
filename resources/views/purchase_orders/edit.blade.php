@extends('layouts/edit-form', [
    'createText' => trans('admin/purchase_orders/table.create'),
    'updateText' => trans('admin/purchase_orders/table.update'),
    'helpTitle' => trans('admin/purchase_orders/table.about_purchase_orders_title'),
    'helpText' => trans('admin/purchase_orders/table.about_purchase_orders_text'),
    'topSubmit' => true,
    'formAction' => (isset($item->id)) ? route('purchase-orders.update', ['purchase_order' => $item->id]) : route('purchase-orders.store'),
])

{{-- Page content --}}
@section('inputFields')

<!-- PO Number -->
<div class="form-group {{ $errors->has('custom_po_id') ? ' has-error' : '' }}">
    {{ Form::label('custom_po_id', trans('admin/purchase_orders/table.po_number'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('custom_po_id', old('custom_po_id', $item->custom_po_id), array('class' => 'form-control', 'placeholder' => 'Leave blank to auto-generate')) }}
        {!! $errors->first('custom_po_id', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- PO Name -->
<div class="form-group {{ $errors->has('po_name') ? ' has-error' : '' }}">
    {{ Form::label('po_name', trans('admin/purchase_orders/table.po_name'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('po_name', old('po_name', $item->po_name), array('class' => 'form-control', 'required' => 'required')) }}
        {!! $errors->first('po_name', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

@include ('partials.forms.edit.supplier-select', ['translated_name' => trans('admin/purchase_orders/table.supplier'), 'fieldname' => 'supplier_id'])

@include ('partials.forms.edit.user-select', ['translated_name' => trans('admin/purchase_orders/table.requested_by'), 'fieldname' => 'requested_by'])

@include ('partials.forms.edit.user-select', ['translated_name' => trans('admin/purchase_orders/table.owner'), 'fieldname' => 'owner_id'])

@include ('partials.forms.edit.user-select', ['translated_name' => trans('admin/purchase_orders/table.approver'), 'fieldname' => 'approver_id'])

@if (!isset($item->id) || $item->linesAreEditable())
<!-- Status -->
<div class="form-group {{ $errors->has('status') ? ' has-error' : '' }}">
    {{ Form::label('status', trans('admin/purchase_orders/table.status'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::select('status', \App\Models\PurchaseOrder::CREATOR_SELECTABLE_STATUSES, old('status', $item->status ?: \App\Models\PurchaseOrder::STATUS_DRAFT), array('class' => 'form-control')) }}
        <p class="help-block">{{ trans('admin/purchase_orders/table.status_help') }}</p>
        {!! $errors->first('status', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>
@else
<!-- Status (read-only once past Draft) -->
<div class="form-group">
    {{ Form::label('status_display', trans('admin/purchase_orders/table.status'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        <p class="form-control-static">{{ $item->statusLabel() }}</p>
        <p class="help-block">{{ trans('admin/purchase_orders/table.status_locked_help') }}</p>
    </div>
</div>
@endif

@if(isset($item->id))
    <!-- Total Price - display only when editing existing PO -->
    <div class="form-group">
        {{ Form::label('total_price', trans('admin/purchase_orders/table.total_price'), ['class' => 'col-md-3 control-label']) }}

        <div class="col-md-7">
            <p class="form-control-static">
                {{ number_format($item->total_price ?? 0, 2) }}
            </p>

            <p class="help-block">
                Total price is calculated automatically from the purchase order items.
            </p>
        </div>
    </div>
@else
    <!-- New PO starts with zero total -->
    {{ Form::hidden('total_price', 0) }}
@endif

<!-- Currency Code -->
<div class="form-group {{ $errors->has('currency_code') ? ' has-error' : '' }}">
    {{ Form::label('currency_code', trans('admin/purchase_orders/table.currency_code'), array('class' => 'col-md-3 control-label')) }}
    <div class="col-md-7">
        {{ Form::text('currency_code', old('currency_code', $item->currency_code), array('class' => 'form-control', 'maxlength' => 10, 'placeholder' => 'e.g. INR, USD')) }}
        {!! $errors->first('currency_code', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
    </div>
</div>

<!-- Required Date -->
<div class="form-group {{ $errors->has('required_date') ? ' has-error' : '' }}">
   <label for="required_date" class="col-md-3 control-label">{{ trans('admin/purchase_orders/table.required_date') }}</label>
   <div class="input-group col-md-4">
        <div class="input-group date" data-provide="datepicker" data-date-clear-btn="true" data-date-format="yyyy-mm-dd" data-autoclose="true">
            <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="required_date" id="required_date" readonly value="{{ old('required_date', ($item->required_date) ? $item->required_date->format('Y-m-d') : '') }}" style="background-color:inherit">
            <span class="input-group-addon"><x-icon type="calendar" /></span>
       </div>
       {!! $errors->first('required_date', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
   </div>
</div>

@stop
