@extends('layouts.app')
@section('title', 'Login')
@section('content')

<div id="auth-box">

    <div class="form-account" id="login-form">
        <h1>Login</h1>
        <form method="POST" action="/login">
            @csrf
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            @error('password')
                <p style="color: red; font-size: 14px;">{{ $message }}</p>
            @enderror
                        @error('email')
                <p style="color: red; font-size: 14px;">{{ $message }}</p>
            @enderror
            <button class="button-border" type="submit">Login</button>
        </form>
        <p>No account? <span onclick="showRegister()">Register</span></p>
    </div>

    <div class="form-account" id="register-form" style="display:none;">
        <h1>Register</h1>
        <form method="POST" action="/register">
            @csrf
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="password_confirmation" placeholder="Confirm Password" required>
            @error('username')
                <p style="color: red; font-size: 14px;">{{ $message }}</p>
            @enderror
            @error('email')
                <p style="color: red; font-size: 14px;">{{ $message }}</p>
            @enderror
            @error('password')
                <p style="color: red; font-size: 14px;">{{ $message }}</p>
            @enderror
            <button class="button-border" type="submit">Register</button>
        </form>
        <p>Already have an account? <span onclick="showLogin()">Login</span></p>
    </div>
</div>

<script>
function showRegister() {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('register-form').style.display = 'block';
}
function showLogin() {
    document.getElementById('register-form').style.display = 'none';
    document.getElementById('login-form').style.display = 'block';
}
</script>

@endsection