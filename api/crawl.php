<?php
/**
 * API Crawl: Menjalankan crawling data X (Twitter) via Tweet Harvest CLI.
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(600); // 10 menit batas waktu proses crawling

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request method POST.']);
    exit;
}

// Baca data dari POST atau JSON Body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$token   = trim($input['token'] ?? DEFAULT_X_TOKEN);
$keyword = trim($input['keyword'] ?? '');
$since   = trim($input['since'] ?? '');
$until   = trim($input['until'] ?? '');
$limit   = intval($input['limit'] ?? 50);

// Validasi
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'X Auth Token wajib diisi.']);
    exit;
}
if (empty($keyword)) {
    echo json_encode(['success' => false, 'message' => 'Keyword pencarian tidak boleh kosong.']);
    exit;
}
if (empty($since)) {
    echo json_encode(['success' => false, 'message' => 'Tanggal mulai (Since) wajib diisi.']);
    exit;
}
if (empty($until)) {
    echo json_encode(['success' => false, 'message' => 'Tanggal akhir (Until) wajib diisi.']);
    exit;
}
if (strtotime($since) > strtotime($until)) {
    echo json_encode(['success' => false, 'message' => 'Tanggal Since tidak boleh lebih besar dari tanggal Until.']);
    exit;
}
if ($limit < 10) {
    $limit = 10;
}
if ($limit > 1000) {
    $limit = 1000;
}

// Pastikan DB sudah terhubung dan database/tabel ada
try {
    get_db_connection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database bermasalah: ' . $e->getMessage()]);
    exit;
}

$pythonBin  = get_python_executable();
$scriptPath = realpath(__DIR__ . '/../cli_crawl.py');
$workingDir = realpath(__DIR__ . '/..');

if (!file_exists($scriptPath)) {
    echo json_encode(['success' => false, 'message' => 'File backend runner (cli_crawl.py) tidak ditemukan.']);
    exit;
}

$command = sprintf(
    '"%s" "%s" --keyword %s --since %s --until %s --limit %d --token %s',
    $pythonBin,
    $scriptPath,
    escapeshellarg($keyword),
    escapeshellarg($since),
    escapeshellarg($until),
    $limit,
    escapeshellarg($token)
);

$descriptorspec = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"], // stderr
];

$process = proc_open($command, $descriptorspec, $pipes, $workingDir);

if (!is_resource($process)) {
    echo json_encode(['success' => false, 'message' => 'Gagal membuka proses sistem untuk menjalankan crawler.']);
    exit;
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$exitCode = proc_close($process);

// Filter token dari pesan stderr/stdout jika ada
$sanitizedStdout = str_replace($token, '********', $stdout);
$sanitizedStderr = str_replace($token, '********', $stderr);

// Cari JSON dari output Python
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
    $errMsg = trim($sanitizedStderr) ?: trim($sanitizedStdout);
    echo json_encode([
        'success'   => false,
        'message'   => 'Crawler selesai dengan kode: ' . $exitCode . '. Pesan: ' . ($errMsg ?: 'Tidak ada pesan output.'),
        'stdout'    => $sanitizedStdout,
        'exit_code' => $exitCode,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

