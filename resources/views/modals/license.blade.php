{{-- See snipeit_modals.js for what powers this.

     Same reasoning as modals/consumable.blade.php: gives the Item Master's
     License-select field (Items > Create/Edit, when Item Type = License) a
     "+New" shortcut matching every other js-data-ajax field in this app,
     without changing the underlying design decision that an Item still has
     to link to a real License record rather than one being silently
     auto-created for it. --}}
<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h2 class="modal-title">{{ trans('admin/licenses/form.create') }}</h2>
        </div>
        <div class="modal-body">
            <form action="{{ route('api.licenses.store') }}" onsubmit="return false">
                <div class="alert alert-danger" id="modal_error_msg" style="display:none">
                </div>
                @include('modals.partials.name', ['item' => new \App\Models\License(), 'required' => 'true'])

                <div class="dynamic-form-row">
                    @include('partials.forms.edit.category-select', ['translated_name' => trans('general.category'), 'fieldname' => 'category_id', 'category_type' => 'license', 'hide_new' => 'true'])
                </div>

                <div class="dynamic-form-row">
                    <div class="col-md-4 col-xs-12"><label for="modal-seats">{{ trans('admin/licenses/form.seats') }}:</label></div>
                    <div class="col-md-8 col-xs-12"><input type="text" name="seats" id="modal-seats" class="form-control" minlength="1" required></div>
                </div>
            </form>
        </div>
        @include('modals.partials.footer')
    </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
