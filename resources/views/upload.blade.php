<!DOCTYPE html>
<html>
<head>
    <title>Upload Level</title>
</head>
<body>

<div class="container">

    <h1>Upload your level drawing</h1>

    <form
        action="/upload-level"
        method="POST"
        enctype="multipart/form-data"
    >

        @csrf

        <input
            type="file"
            name="levelImage"
            accept="image/*"
            required
        >

        <br>

        <button type="submit">
            Start Game
        </button>

    </form>

</div>

</body>
</html>