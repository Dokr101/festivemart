<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Site Constants ────────────────────────────────────────────────
define('DB_HOST',  'localhost');
define('DB_USER',  'root');
define('DB_PASS',  '');
define('DB_NAME',  'festivemart');
define('SITE_URL', 'http://localhost/festivemart');
define('SITE_NAME','FestiVmart');

// ── PDO Connection ────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#0a0a1a;color:#ff4757;text-align:center;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;">
        <h1 style="font-size:3rem;margin-bottom:10px;">🎪</h1>
        <h2 style="color:#fff;">Database Connection Failed</h2>
        <p>Please ensure XAMPP MySQL is running and import <code>database/festivemart.sql</code> first.</p>
        <small style="color:#888">'.htmlspecialchars($e->getMessage()).'</small>
    </div>');
}
