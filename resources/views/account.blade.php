@extends('layouts.app')
@section('title', 'My Account')
@section('content')


<div id="account-container">

<h1>My Saved Drawings</h1>

<div class="drawings-grid">
    @foreach($drawings as $drawing)
        <div class="drawing-card">

            <form method="POST" action="/drawing/{{ $drawing->id }}/publish" class="publish-form">
                @csrf
                <button type="submit" class="publish-btn {{ $drawing->published ? 'published' : '' }}">
                    {{ $drawing->published ? '✓ Published' : 'Publish' }}
                </button>
            </form>

            <img src="{{ asset('storage/' . $drawing->image_path) }}"
                alt="Drawing"
                onclick="window.location.href='/play/{{ $drawing->id }}'">

            <form method="POST" action="/drawing/{{ $drawing->id }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="delete-btn">✕</button>
            </form>

        </div>
    @endforeach
</div>
<form method="POST" action="/logout">
    @csrf
    <button class="button-border" type="submit">Logout</button>
</form>
</div>





@endsection