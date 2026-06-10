@extends('layouts.app')
@section('title', 'Level specifics')
@section('content')

    <style>
        body{
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }

        #levelPreview{
            max-width: 1000px;
            cursor: crosshair;
        }

        .selector{
            margin: 10px;
        }

        .selector-group{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:10px;
    border-radius:8px;
    cursor:pointer;
    transition:0.2s;
    margin-bottom: 20px;
    margin-top: 8px;
}

.selector-group.active{
    border:2px solid black;
}

.color-box{
    width:40px;
    height:40px;
    border:1px solid black;
}
        .photo-border {
    position: relative;
    display: inline-block;
}

.photo-border::before {
    content: "";
    position: absolute;
    inset: -8px;
    background-image: var(--border-frame, url('/assets/foto-klein/Foto-1-klein.png'));
    background-size: 100% 100%;
    pointer-events: none;
    z-index: 1;
}
    </style>

<h1>Select Colors</h1>
<p>Click a button, then click the corresponding color on the image.</p>
<div id="platformGroup" class="selector-group">
    <button id="pickPlatform" class="selector">
        Pick Platform
    </button>
    <div id="platformPreview" class="color-box"></div>
</div>

<div id="goalGroup" class="selector-group">
    <button id="pickGoal" class="selector">
        Pick Goal
    </button>
    <div id="goalPreview" class="color-box"></div>
</div>

<div id="playerGroup" class="selector-group">
    <button id="pickPlayer" class="selector">
        Pick Player
    </button>
    <div id="playerPreview" class="color-box"></div>
</div>

<div id="hazardGroup" class="selector-group">
    <button id="pickHazard" class="selector">
        Pick Hazard
    </button>
    <div id="hazardPreview" class="color-box"></div>
</div>
<br>

<div class="photo-border" style="padding: 10px; padding-bottom: 20px; padding-left:20px;">
    <img id="levelPreview"src="{{ asset('storage/' . session('uploadedLevel')) }}">
</div>

<form action="/start-game" method="POST">
    @csrf

    <input type="hidden" name="platformColor" id="platformColor">
    <input type="hidden" name="goalColor" id="goalColor">
    <input type="hidden" name="playerColor" id="playerColor">
    <input type="hidden" name= "hazardColor" id="hazardColor">
    <button type="submit" class= "button-border">
        Start Game
    </button>
</form>



<script>
let currentSelection = null;
const buttons = document.querySelectorAll(".selector");
const groups = document.querySelectorAll(".selector-group");

function activateGroup(groupId){
    groups.forEach(group => {
        group.classList.remove("active");
    });
    document.getElementById(groupId).classList.add("active");
}

document.getElementById("platformGroup").addEventListener("click", () => {
    currentSelection = "platform";
    activateGroup("platformGroup");
});

document.getElementById("goalGroup").addEventListener("click", () => {
    currentSelection = "goal";
    activateGroup("goalGroup");
});

document.getElementById("playerGroup").addEventListener("click", () => {
    currentSelection = "player";
    activateGroup("playerGroup");
});
document.getElementById("hazardGroup").addEventListener("click", () => {
    currentSelection = "hazard";
    activateGroup("hazardGroup");
});

const image = document.getElementById("levelPreview");
image.addEventListener("click", (event) => {
    if(!currentSelection){
        return;
    }
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    canvas.width = image.naturalWidth;
    canvas.height = image.naturalHeight;
    ctx.drawImage(image,0,0);

    const rect = image.getBoundingClientRect();
    const x = (event.clientX - rect.left) * (image.naturalWidth / rect.width);
    const y = (event.clientY - rect.top) * (image.naturalHeight / rect.height);

    const pixel =ctx.getImageData(Math.floor(x), Math.floor(y), 1, 1).data;

    const hex =
        "#" +
        pixel[0].toString(16).padStart(2,"0") +
        pixel[1].toString(16).padStart(2,"0") +
        pixel[2].toString(16).padStart(2,"0");

    if(currentSelection === "platform"){
        document.getElementById("platformColor").value = hex;
        document.getElementById("platformPreview").style.backgroundColor = hex;
    }

    if(currentSelection === "goal"){
        document.getElementById("goalColor").value = hex;
        document.getElementById("goalPreview").style.backgroundColor = hex;
    }

    if(currentSelection === "player"){
        document.getElementById("playerColor").value = hex;
        document.getElementById("playerPreview").style.backgroundColor = hex;
    }
    if(currentSelection === "hazard"){
    document.getElementById("hazardColor").value = hex;
    document.getElementById("hazardPreview").style.backgroundColor = hex;
}
});

const PHOTO_FRAMES = [
    '/assets/foto-klein/Foto-1-klein.png',
    '/assets/foto-klein/Foto-2-klein.png',
    '/assets/foto-klein/Foto-3-klein.png',
];

PHOTO_FRAMES.forEach(src => new Image().src = src);
document.querySelectorAll('.photo-border').forEach(el => {
    let frame = 0;
    setInterval(() => {
        frame = (frame + 1) % PHOTO_FRAMES.length;
        el.style.setProperty('--border-frame', `url('${PHOTO_FRAMES[frame]}')`);
    }, 1000);
});

</script>

@endsection