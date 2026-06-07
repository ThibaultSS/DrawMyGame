@extends('layouts.app')
@section('title', 'Upload Level')
@section('content')
    @vite('resources/js/app.js')

<script>
window.levelImage = "{{ asset('storage/' . session('uploadedLevel')) }}";
window.platformColor = "{{ session('platformColor') }}";
window.goalColor = "{{ session('goalColor') }}";
window.playerColor = "{{ session('playerColor') }}";
window.hazardColor = "{{ session('hazardColor') }}";
</script>

<div class="game-body">
<div id="game-container"></div>

<div id="controls">
    <label>
        <input type="range" id="speedSlider" min="1" max="20"value="5">
        Speed
    </label>

    <label>
        <input type="range" id="jumpSlider" min="5" max="30"value="10">
        Jump Height
    </label>
    <button onclick="history.back()" id="backButton" class="button-border">Go Back</button>
</div>

</div>

<script>
window.levelImage =
    "{{ asset('storage/' . session('uploadedLevel')) }}";
</script>

@endsection

