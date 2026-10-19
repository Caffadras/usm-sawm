<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fake phishing demo</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { max-width: 640px; margin: 4rem auto; padding: 0 1rem; line-height: 1.5; text-align: center; }
        .box { border: 2px solid #c62828; border-radius: .5rem; padding: 2rem; }
        h1 { color: #c62828; }
    </style>
</head>
<body>
    <div class="box">
        <h1>This is a fake phishing site</h1>
        <p>A stored XSS payload in the guest-book sent your browser here.</p>
        <p>A real attacker would send you to a page that looks like your bank
        or your e-mail and steals what you type.</p>
        <p>This page is local and harmless. It only shows what a redirect can do.</p>
        <p><a href="index.php">Back to the guest-book</a></p>
    </div>
</body>
</html>
