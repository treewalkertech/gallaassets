@extends('layouts/default')

@section('title')
Edit Barcode Template
@endsection

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Edit Barcode Template</h3>

        <a href="{{ route('admin.barcode.templates') }}"
           class="btn btn-secondary">
            ← Back
        </a>
    </div>

    {{-- React/JS Mount Point --}}
    <div id="barcode-app"
         data-page="edit"
         data-id="{{ $id }}">
    </div>

</div>
@endsection

<script src="{{ asset('js/barcode-templates.js') }}"></script>
