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
<style>
        .button-border {
            cursor: pointer;
            position: relative;
            padding: 10px 40px;
            border: none;
            cursor: pointer;
            margin-top: 40px;
            text-shadow: none;
            transition: text-shadow 0.2s;
            font-size: 20px;

        }
        .button-border * {
    cursor: pointer;
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
</style>

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

            <img src="{{ asset('assets/account.svg') }}" alt="Account_logo" id="account-logo">
        </div>


    </div>
</header>

<main class="container">
    @yield('content')
</main>

<footer>
    <div class="footer-container">
        <img src="{{ asset('assets/Rectangle.png') }}" alt="Layout" id="rectangle">
        <div>
            <p id="footer-title">Free <br>your <br>creativity</p>
            <button class="button-border" onclick="window.location.href='/contact'">Contact us</button>
        </div>
        <div id="copyright">
            <p>
                © {{ date('Y') }} DrawMyGame, All Rights Reserved
            </p>
        </div>

        <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
            <a href="/upload">Upload</a>
            <a href="/contact">Contact</a>
        </nav>

    </div>
</footer>
<script>
      const FRAMES_C = [
          '/assets/button/Button-1.png',
          '/assets/button/Button-2.png',
          '/assets/button/Button-3.png',
      ];

      // Preload so frames swap instantly
      FRAMES_C.forEach(src => new Image().src = src);

      document.querySelectorAll('.button-border').forEach(btn => {
          let frame = 0;
          setInterval(() => {
              frame = (frame + 1) % FRAMES_C.length;
              btn.style.setProperty('--border-frame', `url('${FRAMES_C[frame]}')`);
          }, 150);
      });
      </script>

</body>
</html>