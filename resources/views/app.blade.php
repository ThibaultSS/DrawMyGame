<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- site.css, not app.css: the rebuilt pages do not inherit the old stylesheet. --}}
    @vite(['resources/css/site.css', 'resources/js/app.js'])

    <x-inertia::head />
</head>
<body>
    <x-inertia::app />
</body>
</html>
