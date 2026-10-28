<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

start_session();
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged out | YESG</title>
    <link rel="stylesheet" href="CSS/app.css">
</head>
<body class="center-body" data-page="logout">
    <section class="center-card">
        <img src="IMAGES/logo.jpg" alt="YESG logo" id="logo-large">
        <h1>You have been logged out</h1>
        <p class="muted">Thank you for saving with YESG. Your session has been closed.</p>
        <a class="btn primary" href="index.php">Back to login</a>
    </section>
    <script src="JS/app.js"></script>
</body>
</html>
