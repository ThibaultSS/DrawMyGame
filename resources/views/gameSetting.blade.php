<!DOCTYPE html>
<html>
<head>
    <title>Select Colors</title>

    <style>
        body{
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }

        #levelPreview{
            max-width: 1000px;
            border: 2px solid black;
            cursor: crosshair;
        }

        .selector{
            margin: 10px;
        }

        .active{
            background-color: lightgreen;
        }

        .color-box{
            width: 40px;
            height: 40px;
            border: 1px solid black;
            display: inline-block;
            vertical-align: middle;
            margin-left: 10px;
        }
    </style>
</head>
<body>

<h1>Select Colors</h1>

<p>
    Click a button, then click the corresponding color on the image.
</p>

<div>

    <button
        id="pickPlatform"
        class="selector"
    >
        Pick Platform
    </button>

    <div
        id="platformPreview"
        class="color-box"
    ></div>

    <br><br>

    <button
        id="pickGoal"
        class="selector"
    >
        Pick Goal
    </button>

    <div
        id="goalPreview"
        class="color-box"
    ></div>

    <br><br>

    <button
        id="pickPlayer"
        class="selector"
    >
        Pick Player
    </button>

    <div
        id="playerPreview"
        class="color-box"
    ></div>

</div>

<br>

<img
    id="levelPreview"
    src="{{ asset('storage/' . session('uploadedLevel')) }}"
>

<form
    action="/start-game"
    method="POST"
>
    @csrf

    <input
        type="hidden"
        name="platformColor"
        id="platformColor"
    >

    <input
        type="hidden"
        name="goalColor"
        id="goalColor"
    >

    <input
        type="hidden"
        name="playerColor"
        id="playerColor"
    >

    <br><br>

    <button type="submit">
        Start Game
    </button>

</form>

<script>

let currentSelection = null;

const buttons = document.querySelectorAll(".selector");

function activateButton(button){

    buttons.forEach(b => {
        b.classList.remove("active");
    });

    button.classList.add("active");
}

document
    .getElementById("pickPlatform")
    .addEventListener("click", () => {

        currentSelection = "platform";

        activateButton(
            document.getElementById("pickPlatform")
        );

    });

document
    .getElementById("pickGoal")
    .addEventListener("click", () => {

        currentSelection = "goal";

        activateButton(
            document.getElementById("pickGoal")
        );

    });

document
    .getElementById("pickPlayer")
    .addEventListener("click", () => {

        currentSelection = "player";

        activateButton(
            document.getElementById("pickPlayer")
        );

    });

const image =
    document.getElementById("levelPreview");

image.addEventListener("click", (event) => {

    if(!currentSelection){
        return;
    }

    const canvas =
        document.createElement("canvas");

    const ctx =
        canvas.getContext("2d");

    canvas.width =
        image.naturalWidth;

    canvas.height =
        image.naturalHeight;

    ctx.drawImage(
        image,
        0,
        0
    );

    const rect =
        image.getBoundingClientRect();

    const x =
        (event.clientX - rect.left) *
        (image.naturalWidth / rect.width);

    const y =
        (event.clientY - rect.top) *
        (image.naturalHeight / rect.height);

    const pixel =
        ctx.getImageData(
            Math.floor(x),
            Math.floor(y),
            1,
            1
        ).data;

    const hex =
        "#" +
        pixel[0].toString(16).padStart(2,"0") +
        pixel[1].toString(16).padStart(2,"0") +
        pixel[2].toString(16).padStart(2,"0");

    if(currentSelection === "platform"){

        document
            .getElementById("platformColor")
            .value = hex;

        document
            .getElementById("platformPreview")
            .style.backgroundColor = hex;
    }

    if(currentSelection === "goal"){

        document
            .getElementById("goalColor")
            .value = hex;

        document
            .getElementById("goalPreview")
            .style.backgroundColor = hex;
    }

    if(currentSelection === "player"){

        document
            .getElementById("playerColor")
            .value = hex;

        document
            .getElementById("playerPreview")
            .style.backgroundColor = hex;
    }

});

</script>

</body>
</html>