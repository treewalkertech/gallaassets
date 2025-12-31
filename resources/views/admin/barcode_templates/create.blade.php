@extends('layouts/default')

@section('title')
Create Barcode Template
@endsection

@section('content')
<div class="container-fluid">
    <h3>Create Barcode Template</h3>

    <div id="barcode-app"
         data-page="create">
    </div>
</div>
@endsection

{{-- @push('scripts') --}}
<script src="{{ asset('js/barcode-templates.js') }}"></script>
{{-- @endpush --}}
