@extends('layouts.app')
@section('title', 'Upload Level')
@section('content')

<div class="upload-title">
    <h1>Upload your level here</h1>
    <img src="{{ asset('assets/Pijlen.png') }}" alt="Pijlen" id="pijlen">
</div>
<form
    action="/upload-level"
    method="POST"
    enctype="multipart/form-data"
>
    @csrf

    <input
        type="file"
        name="levelImage"
        accept="image/*"
        required
    >

    <button class="button">
        Continue
    </button>

</form>

@endsection