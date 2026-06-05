@extends('layouts.app')
@section('title', 'Home Page')
@section('content')
<style>
        .button-border {
            position: relative;
            padding: 10px 40px;
            border: none;
            cursor: pointer;
            margin-top: 20px;
            text-shadow: none;
            transition: text-shadow 0.2s;
        }

        .button-border::before {
            content: "";
            position: absolute;
            inset: -8px;
            background-image: var(--border-frame, url('/assets/button/Button-1.png'));
            background-size: 100% 100%;
            pointer-events: none;
        }
        .button-border:hover {
          text-shadow: 0 0 1px black, 0 0 0.5px black;
        }
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
<div class="banner">

    <video autoplay muted loop playsinline>
      <source src="{{ asset('assets/Banner_video.mp4') }}" type="video/mp4">
      Your browser does not support the video tag.
    </video>
  <div class="overlay">  </div>

  <div class="banner-content">
    <h1>Upload your drawing!</h1>
    <p>Draw your own picture and play it! <br>Make platforms, your own characters and deadly spikes.
    <br>Your creativity is the limit. <br>Try it now!</p>
    <button class="button-border" onclick="window.location.href='/upload'">Upload</button>
      <script>
      const FRAMES = [
          '/assets/button/Button-1.png',
          '/assets/button/Button-2.png',
          '/assets/button/Button-3.png',
      ];

      // Preload so frames swap instantly
      FRAMES.forEach(src => new Image().src = src);

      document.querySelectorAll('.button-border').forEach(btn => {
          let frame = 0;
          setInterval(() => {
              frame = (frame + 1) % FRAMES.length;
              btn.style.setProperty('--border-frame', `url('${FRAMES[frame]}')`);
          }, 150);
      });
      </script>
  </div>
</div>




<div class="container-explanation">
  <div class="explanation">
    <div class="photo-border">
      <img src="{{ asset('assets/pencils.jpg') }}" alt="Start Drawing">
    </div>
    <div>
    <h2>Start Drawing!</h2>
    <p>You can make any level you want on a piece of paper! <br>
     Just take some colors that are visually very different, the clearer the better. <br> 
     Your imagination is your limit. You can draw rectangle platforms, maybe some stairs.<br>
     Draw a endgoal, maybe a flag or a door. This is where the player needs to go to win the level.<br>
     To make it more fun, you can also draw some hazards like spikes or lava. <br>
     And finally, don't forget to draw the player! A simple circle is enough. <br>
     </p>
    </div>
  </div>
  <div class="explanation">
    <div>
    <h2>Take a picture</h2>
    <p>After you are done drawing your level, it is time to take a picture. <br>
     Be sure you are in a bright room and put the brightness on your phone high. <br> 
     After you've taken the picture you can upload it on this website.<br>
     Png's, jpgs name it. Every file works.<br>
     </p>
    </div>
    <div class="photo-border">
      <img src="{{ asset('assets/picture-phone.jpg') }}" alt="Start Drawing">
    </div>
  </div>
  <div class="explanation">
    <div class="photo-border">
      <img src="{{ asset('assets/Platform.png') }}" alt="Start Drawing">
    </div>
    <div>
    <h2>Upload & Play!</h2>
    <p>To play your level you need to click on the upload button and submit your photo. <br>
     After that you select what colors are your platfroms, hazards, endgoal and character.<br> 
     You press play game and that's it! You can now play your own drawing!<br>
     Want to make another one? No problem, you can upload as many levels as you want in DrawMyGame.<br>
     Enjoy the experience!<br>
     </p>
    </div>
  </div>
</div>



<script>
      const PHOTO_FRAMES = [
          '/assets/foto/Foto-1.png',
          '/assets/foto/Foto-2.png',
          '/assets/foto/Foto-3.png',
      ];

      // Preload so frames swap instantly
      PHOTO_FRAMES.forEach(src => new Image().src = src);

      document.querySelectorAll('.photo-border').forEach(btn => {
          let frame = 0;
          setInterval(() => {
              frame = (frame + 1) % PHOTO_FRAMES.length;
              btn.style.setProperty('--border-frame', `url('${PHOTO_FRAMES[frame]}')`);
          }, 150);
      });
      </script>

@endsection
