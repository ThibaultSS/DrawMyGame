@extends('layouts.app')
@section('title', 'Home')
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
    <p>
        Grab a piece of paper and let your creativity run wild! Draw your own platformer level 
        and watch it come to life in the game. <br>Use 4 contrasting colours, for instance 
        red, yellow, blue and black. <br>The bolder and cleaner your colours are, 
        the better the game will work, so don't be shy with that marker.
        <br>Every level needs four things. First, draw your platforms, these are the surfaces your 
        player will run and jump on. <br>Think rectangles, stairs, floating islands, whatever comes 
        to mind. Second, draw a goal like a flag, door or star. <br>This is where your player needs 
        to reach to win the level. Third, spice things up with some hazards like spikes or lava 
        that the player needs to avoid. <br>And finally, draw your player character somewhere on the 
        level, a simple circle or blob is more than enough. <br>You can also do this in Paint, Illustrator
        or whatever drawingtool you use. 
    </p>
    </div>
  </div>
  <div class="explanation">
    <div>
    <h2>Take a picture</h2>
    <p>
        Once you are happy with your drawing, it is time to bring it into the game. Start by 
        taking a clear photo of your level. <br>Make sure you are in a well-lit room and crank up 
        the brightness on your phone or camera for the best result. <br>Try to hold your phone 
        directly above the drawing and keep it as flat and straight as possible. <br>The less 
        distortion, the better the game will read your colours.
        Once you have your photo, head over to the upload page and select your file. <br>The game 
        accepts all common image formats including PNG, JPG and JPEG. <br>After uploading, you will be taken to the colour 
        selection screen where you map each colour in your drawing to its role in the game. <br>From there, hit start and your level is ready to play.
    </p>
    </div>
    <div class="photo-border">
      <img src="{{ asset('assets/picture-phone.png') }}" alt="Start Drawing">
    </div>
  </div>
  <div class="explanation">
    <div class="photo-border">
      <img src="{{ asset('assets/Platform.png') }}" alt="Start Drawing">
    </div>
    <div>
    <h2>Upload & Play!</h2>
      <p>
          Use the arrow keys to move your character and jump your way to the goal while avoiding 
          any hazards you drew along the way. <br>Every level is unique because you made it, the 
          shapes, the layout, all of it comes straight from your imagination.
          <br>Want to try something different? No problem. <br>You can upload as many levels as you want 
          and even save your favorites to your account to replay them whenever you want. 
          <br>So keep drawing, keep experimenting and most importantly, enjoy the experience!
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
