@extends('layouts.app')
@section('title', 'Upload Level')
@section('content')


<div class="upload-title">
    <h1>Upload your level here</h1>
    <img src="{{ asset('assets/Pijlen.png') }}" alt="Pijlen" id="pijlen">
</div>
<form action="/upload-level" method="POST" enctype="multipart/form-data" class="form-1">
    @csrf

    <div class="button-border">
    <label for="levelImage" class="formestyle"> Upload</label> 
    <input class="forme"
        type="file"
        name="levelImage"
        id="levelImage"
        accept="image/*"
        required
    >
    </div>
    <div id="continue" style="display:none;color:#FFFFFF">
        <button class="button">
            Continue
        </button>
    </div>

</form>
<script>
      const FRAMES_CC = [
          '/assets/button/Button-1.png',
          '/assets/button/Button-2.png',
          '/assets/button/Button-3.png',
      ];

      // Preload so frames swap instantly
      FRAMES_CC.forEach(src => new Image().src = src);

      document.querySelectorAll('.button-border').forEach(btn => {
          let frame = 0;
          setInterval(() => {
              frame = (frame + 1) % FRAMES_CC.length;
              btn.style.setProperty('--border-frame', `url('${FRAMES_CC[frame]}')`);
          }, 150);
      });
      document.getElementById('levelImage').addEventListener('change', function() {
    const fileInfo = document.getElementById('continue');
    
    if (this.files.length > 0) {
        fileInfo.style.display = 'block';
        this.closest('form').submit();
    }
});

      </script>
@endsection