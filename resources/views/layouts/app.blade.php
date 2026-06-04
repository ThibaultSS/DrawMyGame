<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Draw Platformer')

    </title>

    @vite([
        'resources/css/app.css',
        //'resources/js/app.js'
    ])
</head>

<body>

<header>
    <div class="header-container">
        <img src="{{ asset('assets/DrawMyGame_Logo_Lang.svg') }}" alt="DrawMyGame" id="logo">
        <div>
            <nav>
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/upload">Upload</a>
            </nav>

            <img src="{{ asset('assets/account.svg') }}" alt="Account_logo">
        </div>


    </div>
</header>

<main class="container">
    @yield('content')
</main>

<footer>
    <div class="footer-container">
        <h2>Free your creativity</h2>
        <button onclick="window.location.href='/contact'">Contact us</button>
        <p>
            © {{ date('Y') }} DrawMyGame, All Rights Reserved
        </p>
        <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
            <a href="/upload">Upload</a>
        </nav>

    </div>
</footer>

</body>
</html>