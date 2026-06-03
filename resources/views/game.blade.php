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
console.log("Platform:", window.platformColor);
console.log("Goal:", window.goalColor);
console.log("Player:", window.playerColor);
</script>
<body>

<div id="game-container"></div>

<script>

window.levelImage =
    "{{ asset('storage/' . session('uploadedLevel')) }}";

</script>

</body>
</html>