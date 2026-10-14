<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>{{ $title }} - Belz Family Tree</title>

    <meta property="og:site_name" content="Belz Family Tree" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:url" content="{{ $redirectUrl }}" />
    <meta property="og:image" content="{{ $imageUrl }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:image" content="{{ $imageUrl }}" />

    <meta http-equiv="refresh" content="0;url={{ $redirectUrl }}" />
    <script>window.location.replace(@json($redirectUrl));</script>
</head>
<body>
    <p>Taking you to <a href="{{ $redirectUrl }}">Belz Family Tree</a>&hellip;</p>
</body>
</html>
