<?php
/**
 * Konfigurasi Koneksi Database MySQL & Helper Lingkungan (PHP)
 */

// Helper untuk membaca file .env
function load_env($filePath = __DIR__ . '/.env') {
    $variables = [];
    if (file_exists($filePath)) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Hapus tanda kutip jika ada
                $value = trim($value, "\"'");
                $variables[$key] = $value;
            }
        }
    }
    return $variables;
}

// Muat variabel dari .env
$env = load_env();

// Di Windows, '127.0.0.1' jauh lebih cepat dan tidak mengalami delay timeout IPv6 dibanding 'localhost'
$dbHostRaw = $env['DB_HOST'] ?? '127.0.0.1';
if ($dbHostRaw === 'localhost') {
    $dbHostRaw = '127.0.0.1';
}

define('DB_HOST', $dbHostRaw);
define('DB_PORT', $env['DB_PORT'] ?? '3306');
define('DB_USER', $env['DB_USER'] ?? 'root');
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? 'x_crawler');
define('DEFAULT_X_TOKEN', $env['X_AUTH_TOKEN'] ?? '');

/**
 * Mendapatkan koneksi PDO ke MySQL dengan auto-create database & tabel.
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // 1. Hubungkan ke MySQL server terlebih dahulu (tanpa memilih database) dengan timeout 3 detik agar tidak hanging
        $dsnServer = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
        $pdo = new PDO($dsnServer, DB_USER, DB_PASSWORD, [
            PDO::ATTR_TIMEOUT            => 3,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // 2. Buat database jika belum ada
        $dbNameSafe = str_replace('`', '``', DB_NAME);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `{$dbNameSafe}`;");

        // 3. Pastikan tabel tweets_raw dan tweets_processed ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tweets_raw (
                tweet_id VARCHAR(64) NOT NULL,
                keyword VARCHAR(255) NOT NULL,
                username VARCHAR(255) DEFAULT NULL,
                created_at VARCHAR(100) DEFAULT NULL,
                full_text TEXT NOT NULL,
                crawled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tweet_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tweets_processed (
                tweet_id VARCHAR(64) NOT NULL,
                original_text TEXT NOT NULL,
                cleaned_text TEXT NOT NULL,
                final_text TEXT NOT NULL,
                processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tweet_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Gagal terhubung ke MySQL: " . $e->getMessage());
    }
}

/**
 * Mendeteksi path Python executable (mengutamakan .venv lokal).
 */
function get_python_executable() {
    $candidates = [
        __DIR__ . '/.venv/Scripts/python.exe',
        __DIR__ . '/venv/Scripts/python.exe',
        'C:/Users/HP/Downloads/x-crawler/.venv/Scripts/python.exe',
        'C:/Users/HP/AppData/Local/Programs/Python/Python313/python.exe',
        __DIR__ . '/.venv/bin/python',
    ];
    foreach ($candidates as $cand) {
        if (file_exists($cand)) {
            return $cand;
        }
    }
    return 'python';
}

