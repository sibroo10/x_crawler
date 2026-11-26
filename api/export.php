<?php
/**
 * API Export: Mengunduh data (Raw atau Processed) langsung dari MySQL ke file CSV.
 */
require_once __DIR__ . '/../config.php';

$type = $_GET['type'] ?? 'raw';
$timestamp = date('Ymd_His');

try {
    $pdo = get_db_connection();

    if ($type === 'processed') {
        $filename = "processed_tweets_{$timestamp}.csv";
        $stmt = $pdo->query("SELECT tweet_id, original_text, cleaned_text, final_text, processed_at FROM tweets_processed ORDER BY processed_at DESC");
        $headers = ['tweet_id', 'original_text', 'cleaned_text', 'final_text', 'processed_at'];
    } else {
        $filename = "raw_tweets_{$timestamp}.csv";
        $stmt = $pdo->query("SELECT tweet_id, keyword, username, created_at, full_text, crawled_at FROM tweets_raw ORDER BY crawled_at DESC");
        $headers = ['tweet_id', 'keyword', 'username', 'created_at', 'full_text', 'crawled_at'];
    }

    // Set HTTP Headers untuk download file
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Tambahkan UTF-8 BOM agar Excel menampilkan aksen dan teks UTF-8 dengan benar
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Tulis header kolom
    fputcsv($output, $headers);

    // Tulis baris data
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Gagal mengekspor data: " . $e->getMessage();
    exit;
}

