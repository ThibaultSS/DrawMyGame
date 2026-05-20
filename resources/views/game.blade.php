<!DOCTYPE html>
<html>
<head>
    <title>Game</title>
    @vite('resources/js/app.js')
</head>

<body>

<div id="game-container"></div>

<script>

window.levelImage =
    "{{ asset('storage/' . session('uploadedLevel')) }}";

</script>

</body>
</html>