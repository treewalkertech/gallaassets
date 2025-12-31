@extends('layouts/default')

@section('title')
Barcode Templates
@endsection
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        

        <a href="{{ route('admin.barcode.templates.create') }}"
           class="btn btn-primary">
            + Create Barcode Template
        </a>
    </div>

    <div id="barcode-app" data-page="index"></div>

</div>
@endsection

{{-- @push('scripts') --}}
    <script src="{{ asset('js/barcode-templates.js') }}"></script>
{{-- @endpush --}}
