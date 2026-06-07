@extends('layouts.app')
@section('title', 'Login Page')
@section('content')

<div id="auth-box">

    <div id="login-form">
        <h1>Login</h1>
        <form method="POST" action="/login">
            @csrf
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p>No account? <span onclick="showRegister()">Register</span></p>
    </div>

    <div id="register-form" style="display:none;">
        <h2>Register</h2>
        <form method="POST" action="/register">
            @csrf
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="password_confirmation" placeholder="Confirm Password" required>
            <button type="submit">Register</button>
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