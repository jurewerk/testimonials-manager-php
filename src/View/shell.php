<?php
/**
 * Application shell. The CSRF token and base path are handed to the client
 * here so the JavaScript never has to guess either.
 *
 * @var string $csrfToken
 * @var string $basePath
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#152d28">
    <title>Testimonials Manager</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128172;</text></svg>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/assets/app.css?v=1">
</head>
<body>
<div id="app" class="app-loading">Loading…</div>

<script>
    window.APP = {
        basePath: <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>,
        csrfToken: <?= json_encode($csrfToken) ?>
    };
</script>
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/assets/app.js?v=1"></script>
</body>
</html>
