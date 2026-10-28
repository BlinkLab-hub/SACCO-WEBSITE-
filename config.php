<?php
// Database and app settings.
// For local testing use 'sqlite' (no server needed).
// On a live PHP host (cPanel, etc.) create a MySQL/MariaDB database, then set driver to 'mysql'.

return [
    'driver' => 'sqlite',
    'sqlite_path' => __DIR__ . '/database/yesg.sqlite',
    'mysql' => [
        'host' => 'localhost',
        'name' => 'yesg',
        'user' => 'yesg_user',
        'pass' => 'change-this-password',
    ],
    'shares_unit' => 10000, // UGX per share
];
