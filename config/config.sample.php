<?php
/**
 * Copy this file to config.php and fill in your Hostinger MySQL details.
 * hPanel → Databases → MySQL Databases
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u123456789_tracker',
        'user' => 'u123456789_admin',
        'pass' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Project Tracker',
        'url' => 'https://yourdomain.com', // no trailing slash
        'timezone' => 'Asia/Manila',
    ],
];
