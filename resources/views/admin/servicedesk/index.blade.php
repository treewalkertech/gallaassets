@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/settings/general.service_desk') }}
    @parent
@stop

@section('header_right')
    <a href="{{ route('settings.index') }}" class="btn btn-primary">
        {{ trans('general.back') }}
    </a>
@stop

@section('content')

<div class="row">
    <div class="col-sm-10 col-sm-offset-1 col-md-10">

        <div class="panel box box-default">

            {{-- Header --}}
            <div class="box-header with-border">
                <h2 class="box-title">
                    <i class="fas fa-headset"></i>
                    {{ trans('admin/settings/general.service_desk') }}
                </h2>
                <p class="help-block">
                    Configure ServiceDesk Plus integration settings.
                </p>
            </div>

            {{-- Body --}}
            <div class="box-body">
                <div id="global-alert-container"></div>

                {{-- Livewire Component --}}
                @livewire('service-desk-settings-form')

            </div>

            {{-- Footer --}}
            <div class="box-footer">
                <div class="text-left col-md-6">
                    <a class="btn btn-link" href="{{ route('settings.index') }}">
                        {{ trans('button.cancel') }}
                    </a>
                </div>

                {{-- Save button lives INSIDE Livewire,
                     so footer stays clean --}}
            </div>

        </div>
    </div>
</div>

@endsection

@push('js')
<script>
    window.addEventListener('notify', event => {
        const container = document.getElementById('global-alert-container');
        if (!container) return;

        const type = event.detail.type || 'success'; // success | error | warning
        const message = event.detail.message || 'Something went wrong';

        // Map type to Bootstrap alert class
        let alertClass = 'alert-success';
        if (type === 'error') alertClass = 'alert-danger';
        if (type === 'warning') alertClass = 'alert-warning';

        container.innerHTML = `
            <div class="alert ${alertClass} fade in mb-3" role="alert">
                <strong>${message}</strong>
            </div>
        `;

        // 🔼 Scroll to top alert
        container.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // ⏳ Auto hide after 5 seconds
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    });
</script>
@endpush


