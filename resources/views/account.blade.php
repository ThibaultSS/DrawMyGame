@extends('layouts.app')
@section('title', 'My Account')
@section('content')


<div id="account-container">
<h1>My Saved Drawings</h1>

<div class="drawings-grid">
    @foreach($drawings as $drawing)
    <div class="drawing-card">
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
</div>




@endsection