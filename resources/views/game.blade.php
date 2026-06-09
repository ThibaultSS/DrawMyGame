<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
@extends('layouts.app')
@section('title', 'Game')
@section('content')
    @vite('resources/js/app.js')

<script>
window.levelImage = "{{ asset('storage/' . session('uploadedLevel')) }}";
window.platformColor = "{{ session('platformColor') }}";
window.goalColor = "{{ session('goalColor') }}";
window.playerColor = "{{ session('playerColor') }}";
window.hazardColor = "{{ session('hazardColor') }}";
</script>
<style>
#loading-screen{
    position: fixed;
    inset: 0;

    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin-top: 200px;
    margin-right: 250px;
    z-index: 9999;
}

#loading-screen img{
    width: 150px;
}
</style>
<div id="loading-screen">
    <img
        src="/assets/loading.gif"
        alt="Loading..."
    >
</div>
<div class="game-body">
<div id="game-container"></div>
<div id="popup" style="display:none;">
    <div id="popup-box">
        <h1 id="popup-message"></h1>
        <div class="popupButtons">
        <button class="button-border" onclick="closePopup()">Close</button>
        <button class="button-border" onclick="location.reload()">Retry</button>
        </div>
    </div>
</div>
<div id="controls">
    @auth
    <button class="button-border" id="saveBtn">Save Drawing</button>
    @endauth
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
window.gamePaused = false;

window.levelImage =
    "{{ asset('storage/' . session('uploadedLevel')) }}";
document.getElementById('saveBtn')?.addEventListener('click', () => {
    fetch('/save-drawing', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) alert('Drawing saved!');
    });
});
function showPopup(message) {
    document.getElementById('popup-message').textContent = message;
    document.getElementById('popup').style.display = 'flex';
}
function closePopup() {

    window.gamePaused = false;

    document.getElementById('popup').style.display = 'none';
}
</script>

@endsection

