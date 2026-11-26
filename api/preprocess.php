<?php
/**
 * API Preprocess: Menjalankan pembersihan, tokenizing, stopword removal, dan stemming Sastrawi.
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(900); // 15 menit batas waktu proses preprocessing

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request method POST.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmtCount = $pdo->query("SELECT COUNT(*) AS total FROM tweets_raw");
    $rawCount = (int) ($stmtCount->fetch()['total'] ?? 0);

    if ($rawCount === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada data mentah di database. Silakan lakukan Tarik Data terlebih dahulu.',
        ]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database bermasalah: ' . $e->getMessage()]);
    exit;
}

$pythonBin  = get_python_executable();
$scriptPath = realpath(__DIR__ . '/../cli_preprocess.py');
$workingDir = realpath(__DIR__ . '/..');

if (!file_exists($scriptPath)) {
    echo json_encode(['success' => false, 'message' => 'File backend runner (cli_preprocess.py) tidak ditemukan.']);
    exit;
}

$command = sprintf('"%s" "%s"', $pythonBin, $scriptPath);

$descriptorspec = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"], // stderr
];

$process = proc_open($command, $descriptorspec, $pipes, $workingDir);

if (!is_resource($process)) {
    echo json_encode(['success' => false, 'message' => 'Gagal membuka proses sistem untuk menjalankan preprocessing.']);
    exit;
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$exitCode = proc_close($process);

// Parse JSON dari output
$jsonResult = null;
$lines = explode("\n", trim($stdout));
for ($i = count($lines) - 1; $i >= 0; $i--) {
    $parsed = json_decode(trim($lines[$i]), true);
    if (is_array($parsed) && isset($parsed['success'])) {
        $jsonResult = $parsed;
        break;
    }
}

if ($jsonResult !== null) {
    echo json_encode($jsonResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $errMsg = trim($stderr) ?: trim($stdout);
    echo json_encode([
        'success'   => false,
        'message'   => 'Preprocessing selesai dengan error (kode: ' . $exitCode . '). Info: ' . ($errMsg ?: 'Tidak ada pesan error.'),
        'stdout'    => $stdout,
        'exit_code' => $exitCode,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

