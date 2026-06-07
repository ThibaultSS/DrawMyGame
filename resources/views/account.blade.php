@extends('layouts.app')
@section('title', 'My Account')
@section('content')

<h1>My Saved Drawings</h1>

<div class="drawings-grid">
    @foreach($drawings as $drawing)
        <div class="drawing-card" onclick="window.location.href='/play/{{ $drawing->id }}'">
            <img src="{{ asset('storage/' . $drawing->image_path) }}" alt="Drawing">
        </div>
    @endforeach
</div>

<style>
.drawings-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
    padding: 40px;
}
.drawing-card {
    cursor: pointer;
    border: 2px solid black;
    padding: 10px;
    transition: transform 0.2s;
}
.drawing-card:hover {
    transform: scale(1.05);
}
.drawing-card img {
    width: 200px;
    height: 200px;
    object-fit: cover;
}
</style>

@endsection