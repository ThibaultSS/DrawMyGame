@extends('layouts.app')
@section('title', 'About')
@section('content')
<style>
        
        .photo-border {
        position: relative;
        display: inline-block; /* shrink wrap around the image */
        margin-top: 20px;
        padding: 10px 40px;
        }

        .photo-border img {
            display: block; /* removes bottom gap */
            heigt: auto;
            width: 500px;
        }

        .photo-border::before {
            content: "";
            position: absolute;
            inset: -8px;
            background-image: var(--border-frame, url('/assets/foto/Foto-1.png'));
            background-size: 100% 100%;
            pointer-events: none;
        }

</style>

<div class="banner-about">
  <div class="overlay">  
    <img src="{{ asset('assets/child-drawing.jpg') }}" alt="Child drawing">
  </div>
  <div class="banner-content">
    <h1>About</h1>
    <p class="banner-content-about">
I am a student at KdG University of Applied Sciences and Arts in Hoboken, where I study Multimedia & Creative Technologies. <br>DrawMyGame was developed as my bachelor's project and represents the skills and knowledge I have gained throughout my studies. <br>It combines web development, game development and image processing into a single interactive experience, making it a project I am particularly proud of.
    </p>
    <button class="button-border" onclick="window.location.href='/upload'">Try it out!</button>
  </div>
</div>

<div class="container-explanation">
  <div class="explanation">
    <div class="photo-border">
      <img src="{{ asset('assets/KDG-logo.png') }}" alt="Start Drawing">
    </div>
    <div>
    <h2>About the website</h2>
    <p>DrawMyGame is a web application that transforms simple drawings into playable platform games. <br>Instead of building levels with complex editors or coding mechanics by hand, users can sketch platforms, <br>goals and characters using colours and instantly generate a game.<br>
The project combines image processing, physics simulation and game development to make level creation accessible to anyone. <br>Whether you are experimenting with game design or simply having fun, DrawMyGame turns creativity into gameplay within seconds.
     </p>
    </div>
  </div>
  <div class="explanation">
    <div>
    <h2>The technology behind it</h2>
    <p>DrawMyGame is built using Laravel for the web application and Phaser for the game engine. <br>Uploaded images are processed pixel by pixel to identify shapes based on user-selected colours. <br>These shapes are converted into physics bodies, allowing platforms, goals, players and hazards to interact naturally within the game.

<br>This approach demonstrates how image processing techniques can be combined with modern web technologies <br>to create dynamic and interactive content directly from user-created artwork.
     </p>
    </div>
    <div class="photo-border">
      <img src="{{ asset('assets/Phaser_Logo.png') }}" alt="Start Drawing">
    </div>
  </div>
</div>

<script>
      const PHOTO_FRAMES_C = [
          '/assets/foto/Foto-1.png',
          '/assets/foto/Foto-2.png',
          '/assets/foto/Foto-3.png',
      ];

      // Preload so frames swap instantly
      PHOTO_FRAMES_C.forEach(src => new Image().src = src);

      document.querySelectorAll('.photo-border').forEach(btn => {
          let frame = 0;
          setInterval(() => {
              frame = (frame + 1) % PHOTO_FRAMES_C.length;
              btn.style.setProperty('--border-frame', `url('${PHOTO_FRAMES_C[frame]}')`);
          }, 150);
      });
      </script>
@endsection