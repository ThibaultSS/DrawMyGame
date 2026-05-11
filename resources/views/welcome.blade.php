<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body>

<div id="controls">

    <label>
        Platform Color
        <input type="color" id="platformColor" value="#00ff00">
    </label>

    <label>
        Player Size
        <input type="range" id="playerScale" min="0.5" max="3" step="0.1" value="1">
    </label>

    <label>
        World Speed
        <input type="range" id="worldSpeed" min="100" max="1000" step="10" value="200">
    </label>

    <label>
        Jump Height
        <input type="range" id="jumpHeight" min="-1000" max="-100" step="10" value="-500">
    </label>

</div>

<div id="game-container"></div>

<script>
    window.gameSettings = {
        platformColor: 0x00ff00,
        playerScale: 1,
        worldSpeed: 200,
        jumpHeight: -500
    };

    document.getElementById('platformColor').addEventListener('input', (e) => {
        window.gameSettings.platformColor = parseInt(
            e.target.value.replace('#', '0x')
        );
    });

    document.getElementById('playerScale').addEventListener('input', (e) => {
        window.gameSettings.playerScale = parseFloat(e.target.value);
    });

    document.getElementById('worldSpeed').addEventListener('input', (e) => {
        window.gameSettings.worldSpeed = parseInt(e.target.value);
    });

    document.getElementById('jumpHeight').addEventListener('input', (e) => {
        window.gameSettings.jumpHeight = parseInt(e.target.value);
    });
</script>

</body>