<?php
/**
 * API Upload: Mengunggah dataset eksternal (CSV/TSV/TXT) ke database MySQL (tweets_raw)
 * Dilengkapi fitur pengelompokan Timeslot otomatis berdasarkan waktu tweet/data.
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(600); // 10 menit batas waktu upload data besar
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config.php';

/**
 * Fungsi Helper untuk menentukan Timeslot dari string tanggal/waktu
 */
function determineTimeslot(string $dateTimeStr, string $mode = 'period'): array {
    $timestamp = strtotime($dateTimeStr);
    
    // Jika gagal parsing tanggal, gunakan waktu sekarang
    if ($timestamp === false) {
        $timestamp = time();
    }

    $formattedDate = date('Y-m-d H:i:s', $timestamp);
    $hour = (int) date('G', $timestamp); // Format 24 jam (0 - 23)

    if ($mode === 'hourly') {
        // Mode per 3 jam (contoh: 00:00 - 02:59, 03:00 - 05:59, dst.)
        $startHour = floor($hour / 3) * 3;
        $endHour   = $startHour + 2;
        $slot = sprintf('%02d:00 - %02d:59', $startHour, $endHour);
    } else {
        // Mode default: Pembagian waktu harian (Pagi, Siang, Sore, Malam)
        if ($hour >= 5 && $hour < 11) {
            $slot = 'Pagi (05:00 - 10:59)';
        } elseif ($hour >= 11 && $hour < 15) {
            $slot = 'Siang (11:00 - 14:59)';
        } elseif ($hour >= 15 && $hour < 19) {
            $slot = 'Sore (15:00 - 18:59)';
        } else {
            $slot = 'Malam (19:00 - 04:59)';
        }
    }

    return [
        'formatted_date' => $formattedDate,
        'timeslot'       => $slot
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request method POST.']);
    exit;
}

if (!isset($_FILES['dataset_file']) || $_FILES['dataset_file']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['dataset_file']['error'] ?? 'FILE_MISSING';
    echo json_encode(['success' => false, 'message' => 'File dataset tidak ditemukan atau gagal diunggah (Error code: ' . $errorCode . ').']);
    exit;
}

$uploadedFile = $_FILES['dataset_file'];
$origName     = $uploadedFile['name'];
$tmpPath      = $uploadedFile['tmp_name'];
$fileSize     = $uploadedFile['size'];

// Validasi ekstensi file
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'tsv', 'txt'])) {
    echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Harap unggah file berformat .csv, .tsv, atau .txt.']);
    exit;
}

$customKeyword = trim($_POST['dataset_keyword'] ?? '');
if (empty($customKeyword)) {
    $customKeyword = pathinfo($origName, PATHINFO_FILENAME);
}
// Bersihkan keyword agar rapi
$customKeyword = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $customKeyword);
$customKeyword = trim($customKeyword) ?: 'upload_external';

$uploadMode   = trim($_POST['upload_mode'] ?? 'append');     // 'append' atau 'replace'
$timeslotMode = trim($_POST['timeslot_mode'] ?? 'period');   // 'period' (Pagi/Siang/Sore/Malam) atau 'hourly' (per 3 jam)

try {
    $pdo = get_db_connection();

    // Jika mode 'replace', kosongkan tabel tweets_raw terlebih dahulu
    if ($uploadMode === 'replace') {
        $pdo->exec("TRUNCATE TABLE tweets_raw");
    }

    // Deteksi Delimiter (koma, titik koma, atau tab)
    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        throw new Exception("Tidak dapat membaca file yang diunggah.");
    }

    $sampleLine = fgets($handle);
    if ($sampleLine === false || trim($sampleLine) === '') {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'File dataset kosong.']);
        exit;
    }

    // Hapus BOM UTF-8 jika ada di awal file
    if (str_starts_with($sampleLine, "\xEF\xBB\xBF")) {
        $sampleLine = substr($sampleLine, 3);
    }

    $delimiters = [',', ';', "\t", '|'];
    $delimiterCounts = [];
    foreach ($delimiters as $d) {
        $delimiterCounts[$d] = count(str_getcsv($sampleLine, $d));
    }
    arsort($delimiterCounts);
    $detectedDelimiter = key($delimiterCounts);

    // Dapatkan header kolom
    $headers = str_getcsv($sampleLine, $detectedDelimiter);
    $headersLower = array_map(function($h) {
        return strtolower(trim($h, " \t\n\r\0\x0B\"'"));
    }, $headers);

    // Cari indeks kolom yang relevan
    $textIndex     = null;
    $idIndex       = null;
    $userIndex     = null;
    $timeIndex     = null;
    $timeslotIndex = null;

    $textCandidates     = ['full_text', 'text', 'tweet_text', 'tweet', 'content', 'isi', 'komentar', 'review', 'pesan', 'clean_text', 'raw_text', 'kalimat', 'sentence'];
    $idCandidates       = ['tweet_id', 'id_str', 'id', 'no', 'nomor', 'conversation_id_str', 'index'];
    $userCandidates     = ['username', 'user', 'user_name', 'screen_name', 'author', 'nama', 'pengguna', 'penulis'];
    $timeCandidates     = ['created_at', 'tweet_created_at', 'date', 'tanggal', 'timestamp', 'waktu', 'datetime'];
    $timeslotCandidates = ['timeslot', 'time_slot', 'slot_waktu', 'sesi'];

    foreach ($headersLower as $idx => $hName) {
        if ($textIndex === null && in_array($hName, $textCandidates)) {
            $textIndex = $idx;
        }
        if ($idIndex === null && in_array($hName, $idCandidates)) {
            $idIndex = $idx;
        }
        if ($userIndex === null && in_array($hName, $userCandidates)) {
            $userIndex = $idx;
        }
        if ($timeIndex === null && in_array($hName, $timeCandidates)) {
            $timeIndex = $idx;
        }
        if ($timeslotIndex === null && in_array($hName, $timeslotCandidates)) {
            $timeslotIndex = $idx;
        }
    }

    // Jika kolom teks tidak ditemukan berdasarkan nama kandidat, gunakan kolom dengan teks terpanjang
    if ($textIndex === null) {
        $secondLine = fgets($handle);
        if ($secondLine !== false) {
            $secondRow = str_getcsv($secondLine, $detectedDelimiter);
            $maxLen = -1;
            foreach ($secondRow as $idx => $val) {
                if (strlen($val) > $maxLen) {
                    $maxLen = strlen($val);
                    $textIndex = $idx;
                }
            }
        }
    }

    if ($textIndex === null) {
        fclose($handle);
        echo json_encode([
            'success' => false,
            'message' => 'Kolom teks tidak ditemukan dalam dataset. Pastikan file memiliki kolom seperti: full_text, text, tweet, content, atau komentar.'
        ]);
        exit;
    }

    // Rewind file ke awal dan lewati baris header
    rewind($handle);
    fgetcsv($handle, 0, $detectedDelimiter);

    // Simpan salinan file ke data/raw/
    $rawDir = __DIR__ . '/../data/raw';
    if (!is_dir($rawDir)) {
        mkdir($rawDir, 0777, true);
    }
    $timestamp = date('Ymd_His');
    $safeOrigName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
    $archiveFilename = "uploaded_{$timestamp}_{$safeOrigName}";
    $archivePath = $rawDir . '/' . $archiveFilename;
    copy($tmpPath, $archivePath);

    // Siapkan Prepared Statement MySQL (dengan kolom timeslot)
    $stmt = $pdo->prepare("
        INSERT INTO tweets_raw (tweet_id, keyword, username, created_at, timeslot, full_text, crawled_at)
        VALUES (:tweet_id, :keyword, :username, :created_at, :timeslot, :full_text, :crawled_at)
        ON DUPLICATE KEY UPDATE
            keyword = VALUES(keyword),
            username = VALUES(username),
            created_at = VALUES(created_at),
            timeslot = VALUES(timeslot),
            full_text = VALUES(full_text),
            crawled_at = VALUES(crawled_at)
    ");

    $pdo->beginTransaction();

    $importedCount    = 0;
    $rowIdx           = 0;
    $nowStr           = date('Y-m-d H:i:s');
    $timeslotSummary  = []; // Menyimpan statistik jumlah data per timeslot

    while (($row = fgetcsv($handle, 0, $detectedDelimiter)) !== false) {
        $rowIdx++;
        if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
            continue;
        }

        $textVal = trim($row[$textIndex] ?? '');
        if (empty($textVal)) {
            continue;
        }

        // Tentukan ID
        if ($idIndex !== null && !empty(trim($row[$idIndex] ?? ''))) {
            $tId = trim($row[$idIndex]);
        } else {
            $tId = "up_{$timestamp}_{$rowIdx}";
        }

        // Tentukan Username
        $uName = ($userIndex !== null && !empty(trim($row[$userIndex] ?? ''))) ? trim($row[$userIndex]) : 'dataset_user';

        // Tentukan Waktu & Timeslot
        $rawTime = ($timeIndex !== null && !empty(trim($row[$timeIndex] ?? ''))) ? trim($row[$timeIndex]) : $nowStr;
        $parsedTime = determineTimeslot($rawTime, $timeslotMode);
        $cTime = $parsedTime['formatted_date'];

        // Jika di CSV sudah ada kolom timeslot eksplisit, gunakan itu; jika tidak, pakai hasil kalkulasi otomatis
        if ($timeslotIndex !== null && !empty(trim($row[$timeslotIndex] ?? ''))) {
            $slotVal = trim($row[$timeslotIndex]);
        } else {
            $slotVal = $parsedTime['timeslot'];
        }

        // Hitung distribusi timeslot untuk ditampilkan di response JSON
        $timeslotSummary[$slotVal] = ($timeslotSummary[$slotVal] ?? 0) + 1;

        $stmt->execute([
            ':tweet_id'   => $tId,
            ':keyword'    => $customKeyword,
            ':username'   => $uName,
            ':created_at' => $cTime,
            ':timeslot'   => $slotVal,
            ':full_text'  => $textVal,
            ':crawled_at' => $nowStr,
        ]);

        $importedCount++;

        // Commit berkala tiap 1000 baris agar memory efisien
        if ($importedCount % 1000 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }

    $pdo->commit();
    fclose($handle);

    // Urutkan ringkasan timeslot
    ksort($timeslotSummary);

    // Ambil total data terbaru di DB
    $totalRaw = (int) $pdo->query("SELECT COUNT(*) FROM tweets_raw")->fetchColumn();

    echo json_encode([
        'success'            => true,
        'message'            => "Berhasil mengimpor {$importedCount} baris data dari '{$origName}' ke MySQL.",
        'imported_count'     => $importedCount,
        'total_raw_db'       => $totalRaw,
        'keyword'            => $customKeyword,
        'detected_column'    => $headers[$textIndex] ?? "Kolom ke-{$textIndex}",
        'detected_time_col'  => $timeIndex !== null ? ($headers[$timeIndex] ?? "Kolom ke-{$timeIndex}") : 'Default (Waktu Upload)',
        'timeslot_summary'   => $timeslotSummary,
        'archive_file'       => $archiveFilename,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengimpor dataset: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}