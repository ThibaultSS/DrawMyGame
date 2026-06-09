@extends('layouts.app')
@section('title', 'Community')
@section('content')

<div class="community-header">
    <h1>Community Levels</h1>
    <p>Play levels created by everyone in the DrawMyGame community.</p>
</div>

<div class="drawings-grid">
    @forelse($drawings as $drawing)
        <div class="drawing-card" onclick="window.location.href='/play/{{ $drawing->id }}'">
            <img src="{{ asset('storage/' . $drawing->image_path) }}" alt="Drawing">
            <p class="drawing-author">By {{ $drawing->user->username }}</p>
        </div>
    @empty
        <p>No levels published yet. Be the first!</p>
    @endforelse
</div>

<style>
.community-header {
    text-align: center;
    padding: 40px 0 20px;
}

.drawings-grid {
    display: grid;
    grid-template-columns: repeat(5, 200px);
    gap: 20px;
    justify-content: center;
    padding: 40px;
}

.drawing-card {
    cursor: pointer;
    border: 2px solid black;
    padding: 10px;
    transition: transform 0.2s;
    text-align: center;
}

.drawing-card:hover {
    transform: scale(1.05);
}

.drawing-card img {
    width: 200px;
    height: 200px;
    object-fit: cover;
}

.drawing-author {
    margin: 8px 0 0;
    font-size: 14px;
    color: #555;
}
</style>

@endsection