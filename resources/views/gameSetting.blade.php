<!DOCTYPE html>
<html>
<head>
    <title>Select Colors</title>
</head>
<body>

<h1>Select your colors</h1>

<img
    src="{{ asset('storage/' . $imagePath) }}"
    style="max-width:800px;"
>

<form action="/start-game" method="POST">

    @csrf

    <input
        type="hidden"
        name="imagePath"
        value="{{ $imagePath }}"
    >

    <div>
        <label>Platform Color</label>

        <input
            type="color"
            name="platformColor"
            value="#000000"
        >
    </div>

    <br>

    <div>
        <label>Goal Color</label>

        <input
            type="color"
            name="goalColor"
            value="#ff0000"
        >
    </div>

    <br>

    <div>
        <label>Player Color</label>

        <input
            type="color"
            name="playerColor"
            value="#00ff00"
        >
    </div>

    <br>

    <button type="submit">
        Start Game
    </button>

</form>

</body>
</html>