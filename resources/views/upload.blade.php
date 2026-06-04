@extends('layouts.app')
@section('title', 'Upload Level')
@section('content')

<h1>Upload your level</h1>

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