<?php
/**
 * API Status: Memeriksa koneksi DB, total data mentah & terproses, serta data preview.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$response = [
    'db_connected'       => false,
    'db_message'         => '',
    'raw_count'          => 0,
    'processed_count'    => 0,
    'default_token'      => defined('DEFAULT_X_TOKEN') ? DEFAULT_X_TOKEN : '',
    'raw_preview'        => [],
    'processed_preview'  => [],
    'comparison_sample'  => null,
];

try {
    $pdo = get_db_connection();
    $response['db_connected'] = true;
    $response['db_message']   = 'Koneksi MySQL terhubung dengan baik.';

    // Pastikan kolom timeslot tersedia di tabel tweets_raw (auto-create jika belum ada)
    $checkCol = $pdo->query("SHOW COLUMNS FROM tweets_raw LIKE 'timeslot'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE tweets_raw ADD COLUMN timeslot VARCHAR(50) NULL AFTER created_at");
    }

    // Hitung jumlah data mentah
    $stmtRawCount = $pdo->query("SELECT COUNT(*) AS total FROM tweets_raw");
    $response['raw_count'] = (int) ($stmtRawCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // Hitung jumlah data hasil preprocessing
    $stmtProcCount = $pdo->query("SELECT COUNT(*) AS total FROM tweets_processed");
    $response['processed_count'] = (int) ($stmtProcCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // Ambil 10 data mentah terakhir
    if ($response['raw_count'] > 0) {
        $stmtRaw = $pdo->query("SELECT tweet_id, keyword, username, created_at, timeslot, full_text, crawled_at FROM tweets_raw ORDER BY crawled_at DESC LIMIT 10");
        $response['raw_preview'] = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ambil 10 data hasil preprocessing terakhir
    if ($response['processed_count'] > 0) {
        $stmtProc = $pdo->query("SELECT tweet_id, original_text, cleaned_text, final_text, processed_at FROM tweets_processed ORDER BY processed_at DESC LIMIT 10");
        $procRows = $stmtProc->fetchAll(PDO::FETCH_ASSOC);
        $response['processed_preview'] = $procRows;

        if (!empty($procRows)) {
            $response['comparison_sample'] = $procRows[0];
        }
    }

} catch (Throwable $e) {
    $response['db_connected'] = false;
    $response['db_message']   = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);