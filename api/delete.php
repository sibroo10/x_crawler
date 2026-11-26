<?php
/**
 * API Delete: Menghapus data dari database MySQL (Data Mentah, Preprocessed, atau Semua)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request method POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$target  = trim($input['target'] ?? '');
$tweetId = trim($input['tweet_id'] ?? '');
$keyword = trim($input['keyword'] ?? '');

if (empty($target)) {
    echo json_encode(['success' => false, 'message' => 'Target penghapusan data tidak ditentukan.']);
    exit;
}

try {
    $pdo = get_db_connection();

    switch ($target) {
        case 'raw':
            if (!empty($keyword)) {
                $stmt = $pdo->prepare("DELETE FROM tweets_raw WHERE keyword = :kw");
                $stmt->execute([':kw' => $keyword]);
                $count = $stmt->rowCount();
                $msg = "Berhasil menghapus {$count} data mentah untuk keyword '{$keyword}'.";
            } else {
                $pdo->exec("TRUNCATE TABLE tweets_raw");
                $msg = "Seluruh data mentah di tabel tweets_raw berhasil dikosongkan.";
            }
            break;

        case 'processed':
            $pdo->exec("TRUNCATE TABLE tweets_processed");
            $msg = "Seluruh data hasil preprocessing di tabel tweets_processed berhasil dikosongkan.";
            break;

        case 'all':
            $pdo->exec("TRUNCATE TABLE tweets_raw");
            $pdo->exec("TRUNCATE TABLE tweets_processed");
            $msg = "Seluruh data (Mentah & Preprocessed) di MySQL berhasil dikosongkan (Database di-reset).";
            break;

        case 'single_raw':
            if (empty($tweetId)) {
                echo json_encode(['success' => false, 'message' => 'Tweet ID wajib disertakan.']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM tweets_raw WHERE tweet_id = :id");
            $stmt->execute([':id' => $tweetId]);
            $msg = "Tweet ID {$tweetId} berhasil dihapus dari data mentah.";
            break;

        case 'single_processed':
            if (empty($tweetId)) {
                echo json_encode(['success' => false, 'message' => 'Tweet ID wajib disertakan.']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM tweets_processed WHERE tweet_id = :id");
            $stmt->execute([':id' => $tweetId]);
            $msg = "Tweet ID {$tweetId} berhasil dihapus dari data preprocessed.";
            break;

        default:
            echo json_encode(['success' => false, 'message' => "Target '{$target}' tidak valid."]);
            exit;
    }

    // Ambil jumlah data terkini setelah dihapus
    $rawCount  = (int) $pdo->query("SELECT COUNT(*) FROM tweets_raw")->fetchColumn();
    $procCount = (int) $pdo->query("SELECT COUNT(*) FROM tweets_processed")->fetchColumn();

    echo json_encode([
        'success'         => true,
        'message'         => $msg,
        'raw_count'       => $rawCount,
        'processed_count' => $procCount,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus data: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

