{{-- See snipeit_modals.js for what powers this.

     Added so the Item Master's Consumable-select field (Items > Create/Edit,
     when Item Type = Consumable) has the same "+New" shortcut every other
     js-data-ajax field in this app already has (model-select, location-select,
     etc.) -- previously this was the one linkage field with no way to create
     the record it needs without leaving the Item form entirely. Deliberately
     still requires picking/creating a real Consumable rather than
     auto-creating one behind the scenes: the point is to remove the friction
     of the extra step, not to risk silently creating a second, disconnected
     stock record for something you already track under Consumables. --}}
<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h2 class="modal-title">{{ trans('admin/consumables/general.create') }}</h2>
        </div>
        <div class="modal-body">
            <form action="{{ route('api.consumables.store') }}" onsubmit="return false">
                <div class="alert alert-danger" id="modal_error_msg" style="display:none">
                </div>
                @include('modals.partials.name', ['item' => new \App\Models\Consumable(), 'required' => 'true'])

                <div class="dynamic-form-row">
                    @include('partials.forms.edit.category-select', ['translated_name' => trans('general.category'), 'fieldname' => 'category_id', 'category_type' => 'consumable', 'hide_new' => 'true'])
                </div>

                <div class="dynamic-form-row">
                    <div class="col-md-4 col-xs-12"><label for="modal-qty">{{ trans('general.quantity') }}:</label></div>
                    <div class="col-md-8 col-xs-12"><input type="text" name="qty" id="modal-qty" class="form-control" maxlength="5" required></div>
                </div>
            </form>
        </div>
        @include('modals.partials.footer')
    </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
