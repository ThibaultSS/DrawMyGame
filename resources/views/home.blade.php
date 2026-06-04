@extends('layouts.app')
@section('title', 'Home Page')
@section('content')

<div class="banner">
  <video autoplay muted loop playsinline>
    <source src="{{ asset('assets/Banner_video.mp4') }}" type="video/mp4">
    Your browser does not support the video tag.
  </video>

  <div class="banner-content">
    <h1>Welcome</h1>
    <p>Your text goes here.</p>
  </div>
</div>





@endsection