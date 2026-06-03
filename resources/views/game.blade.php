<!DOCTYPE html>
<html>
<head>
    <title>Game</title>
    @vite('resources/js/app.js')
</head>
<script>

window.levelImage =
    "{{ asset('storage/' . session('uploadedLevel')) }}";

window.platformColor =
    "{{ session('platformColor') }}";

window.goalColor =
    "{{ session('goalColor') }}";

window.playerColor =
    "{{ session('playerColor') }}";

</script>
<body>
<div id="controls">

    <label>
        Speed
        <input
            type="range"
            id="speedSlider"
            min="1"
            max="20"
            value="5"
        >
    </label>

    <label>
        Jump Height
        <input
            type="range"
            id="jumpSlider"
            min="5"
            max="30"
            value="10"
        >
    </label>

</div>

<div id="game-container"></div>

<script>

window.levelImage =
    "{{ asset('storage/' . session('uploadedLevel')) }}";

</script>

</body>
</html>