<?php
/**
 * Aplikasi Web X (Twitter) Crawler & Preprocessing Teks Bahasa Indonesia
 * Interface: PHP + Bootstrap 5 + AJAX
 * Fitur: Crawling dengan Timeslot, Preprocessing Sastrawi, Modul Hapus Data, dan Modul Upload Dataset Eksternal
 */
require_once __DIR__ . '/config.php';

// Tanggal default: 7 hari lalu sampai hari ini
$defaultSince = date('Y-m-d', strtotime('-7 days'));
$defaultUntil = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X Crawler & Preprocessing Teks (PHP)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07);
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
            padding: 1rem 1.25rem;
        }
        .stat-card {
            border-left: 4px solid #3b82f6;
        }
        .stat-card.raw {
            border-left-color: #0ea5e9;
        }
        .stat-card.processed {
            border-left-color: #10b981;
        }
        .stat-card.db {
            border-left-color: #6366f1;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .btn-primary {
            background-color: #1d9bf0;
            border-color: #1d9bf0;
            font-weight: 600;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #0c8bd9;
            border-color: #0c8bd9;
        }
        .btn-success {
            font-weight: 600;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
        }
        .table-responsive {
            max-height: 420px;
            overflow-y: auto;
        }
        .comparison-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            min-height: 110px;
            font-size: 0.9rem;
            word-break: break-word;
        }
        .spinner-border-sm {
            width: 1.1rem;
            height: 1.1rem;
            margin-right: 6px;
        }
        .slot-badge-container {
            max-height: 140px;
            overflow-y: auto;
        }
        .nav-pills .nav-link {
            border-radius: 8px;
            color: #64748b;
        }
        .nav-pills .nav-link.active {
            background-color: #1d9bf0;
            color: #ffffff;
        }
    </style>
</head>
<body>

<!-- Banner Peringatan jika file dibuka langsung tanpa server web -->
<div id="file-protocol-warning" class="alert alert-danger m-3 d-none shadow-sm" role="alert">
    <h5 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Peringatan: Halaman dibuka secara langsung (file://)</h5>
    <p class="mb-1">Aplikasi berbasis PHP <strong>wajib dijalankan melalui web server</strong>, tidak bisa dibuka dengan klik dua kali pada file <code>index.php</code> langsung di browser.</p>
    <hr>
    <p class="mb-0"><strong>Solusi Praktis:</strong> Jalankan file <strong><code>run_web.bat</code></strong> (klik dua kali) di folder ini, atau jalankan perintah <code>php -S localhost:8000</code> di PowerShell, lalu akses <a href="http://localhost:8000" class="alert-link fw-bold">http://localhost:8000</a>.</p>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="bi bi-twitter-x fs-4 text-info"></i>
            <span>X Crawler & Preprocessing Teks</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span id="db-badge" class="badge bg-secondary d-flex align-items-center gap-1 py-2 px-3">
                <span class="spinner-grow spinner-grow-sm" role="status"></span>
                <span>Memeriksa Database...</span>
            </span>

            <!-- Dropdown Modul Hapus Data Global -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-trash3-fill me-1"></i> Modul Hapus Data
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><h6 class="dropdown-header text-uppercase">Pilihan Pembersihan</h6></li>
                    <li>
                        <button class="dropdown-item text-danger" onclick="confirmDelete('raw', 'Kosongkan Seluruh Data Mentah (tweets_raw)')">
                            <i class="bi bi-eraser me-2"></i> Kosongkan Data Mentah
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item text-danger" onclick="confirmDelete('processed', 'Kosongkan Seluruh Data Preprocessing (tweets_processed)')">
                            <i class="bi bi-eraser me-2"></i> Kosongkan Data Preprocessing
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item text-danger fw-bold" onclick="confirmDelete('all', 'RESET TOTAL: Kosongkan Seluruh Database MySQL (Mentah & Preprocessed)')">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i> Reset Database Total
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="container-fluid px-4 py-4">

    <!-- Ringkasan Statistik -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card raw p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Data Mentah di MySQL</div>
                        <h2 class="fw-bold mb-0 mt-1" id="stat-raw-count">0</h2>
                        <span class="text-muted small">Tabel: <code>tweets_raw</code></span>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="bi bi-cloud-arrow-down-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card processed p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Data Preprocessed di MySQL</div>
                        <h2 class="fw-bold mb-0 mt-1" id="stat-proc-count">0</h2>
                        <span class="text-muted small">Tabel: <code>tweets_processed</code></span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-file-earmark-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card db p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Status Sistem</div>
                        <h5 class="fw-bold mb-0 mt-2" id="stat-system-status">Menghubungkan...</h5>
                        <span class="text-muted small" id="stat-token-status">X Token: Siap</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-cpu-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dua Kotak Utama: Input Data (Crawl/Upload) & Preprocessing -->
    <div class="row g-4 mb-4">

        <!-- KOTAK 1: INPUT DATA MENTAH (CRAWL X ATAU UPLOAD DATASET) -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                    <ul class="nav nav-pills card-header-pills small" id="inputMethodTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-3 fw-semibold" id="tab-crawl-btn" data-bs-toggle="pill" data-bs-target="#tab-crawl-pane" type="button" role="tab" onclick="updateInputBadge('Tweet Harvest')">
                                <i class="bi bi-search me-1"></i> Tarik Data (Crawl X)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-3 fw-semibold" id="tab-upload-btn" data-bs-toggle="pill" data-bs-target="#tab-upload-pane" type="button" role="tab" onclick="updateInputBadge('Upload Dataset')">
                                <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Dataset Eksternal
                            </button>
                        </li>
                    </ul>
                    <span class="badge bg-primary-subtle text-primary" id="input-mode-badge">Tweet Harvest</span>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="inputMethodTabContent">

                        <!-- TAB A: CRAWLING X (TWEET HARVEST) -->
                        <div class="tab-pane fade show active" id="tab-crawl-pane" role="tabpanel">
                            <form id="crawl-form">
                                <!-- X Auth Token -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">
                                        X Auth Token
                                        <span class="text-muted fw-normal">(dari cookie auth_token browser)</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" id="crawl-token" class="form-control" placeholder="Masukkan token X..." required>
                                        <button type="button" class="btn btn-outline-secondary" id="toggle-token-btn" title="Lihat/Sembunyikan Token">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Keyword -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Keyword Pencarian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                        <input type="text" id="crawl-keyword" class="form-control" placeholder="Contoh: prabowo, ikn, pemilu, hujan" required>
                                    </div>
                                </div>

                                <!-- Rentang Tanggal -->
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold small">Tanggal Mulai (Since)</label>
                                        <input type="date" id="crawl-since" class="form-control" value="<?= htmlspecialchars($defaultSince) ?>" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold small">Tanggal Akhir (Until)</label>
                                        <input type="date" id="crawl-until" class="form-control" value="<?= htmlspecialchars($defaultUntil) ?>" required>
                                    </div>
                                </div>

                                <!-- Limit Tweet -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold small d-flex justify-content-between">
                                        <span>Limit Tweet</span>
                                        <span class="text-muted" id="limit-badge">50 tweet</span>
                                    </label>
                                    <input type="range" class="form-range" id="crawl-limit" min="10" max="500" step="10" value="50" oninput="document.getElementById('limit-badge').innerText = this.value + ' tweet'">
                                    <div class="d-flex justify-content-between text-muted" style="font-size: 11px;">
                                        <span>10</span>
                                        <span>100</span>
                                        <span>250</span>
                                        <span>500</span>
                                    </div>
                                </div>

                                <!-- Tombol Submit Crawling -->
                                <div class="d-grid">
                                    <button type="submit" id="btn-tarik-data" class="btn btn-primary">
                                        <span class="btn-text"><i class="bi bi-search me-1"></i> Tarik Data</span>
                                        <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm" role="status"></span> Sedang Crawling Tweet...</span>
                                    </button>
                                </div>
                            </form>

                            <!-- Alert Notifikasi Crawl -->
                            <div id="crawl-alert" class="alert d-none mt-3 mb-0" role="alert"></div>
                        </div>

                        <!-- TAB B: MODUL UPLOAD DATASET EKSTERNAL -->
                        <div class="tab-pane fade" id="tab-upload-pane" role="tabpanel">
                            <form id="upload-form" enctype="multipart/form-data">
                                <!-- Pilih File Dataset -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">
                                        Pilih File Dataset
                                        <span class="text-muted fw-normal">(.csv, .tsv, .txt)</span>
                                    </label>
                                    <input type="file" id="upload-file-input" class="form-control" accept=".csv,.tsv,.txt" required>
                                    <small class="text-muted d-block mt-1" style="font-size: 11.5px;">
                                        <i class="bi bi-magic me-1 text-primary"></i> Otomatis mengenali kolom teks: <code>full_text</code>, <code>text</code>, <code>tweet</code>, <code>content</code>, <code>isi</code>, <code>komentar</code>, dll.
                                    </small>
                                </div>

                                <!-- Label / Keyword Dataset -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">
                                        Label / Nama Topik Dataset
                                        <span class="text-muted fw-normal">(disimpan ke kolom <code>keyword</code>)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-tag-fill"></i></span>
                                        <input type="text" id="upload-keyword-input" class="form-control" placeholder="Contoh: dataset_sentimen, survei_2026">
                                    </div>
                                    <small class="text-muted" style="font-size: 11px;">Jika dikosongkan, nama file akan otomatis dijadikan label keyword.</small>
                                </div>

                                <!-- Mode Penyimpanan -->
                                <div class="mb-4 p-3 bg-light rounded-3 border">
                                    <label class="form-label fw-semibold small mb-2"><i class="bi bi-database me-1 text-primary"></i> Mode Penyimpanan ke MySQL:</label>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="upload_mode" id="mode-append" value="append" checked>
                                        <label class="form-check-label small" for="mode-append">
                                            <strong>Tambahkan (Append):</strong> Gabungkan dataset ini dengan data mentah yang sudah ada di database.
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="upload_mode" id="mode-replace" value="replace">
                                        <label class="form-check-label small text-danger" for="mode-replace">
                                            <strong>Ganti Baru (Replace):</strong> Kosongkan data mentah lama terlebih dahulu, lalu masukkan dataset ini.
                                        </label>
                                    </div>
                                </div>

                                <!-- Tombol Submit Upload -->
                                <div class="d-grid">
                                    <button type="submit" id="btn-upload-dataset" class="btn btn-primary" style="background-color: #0284c7; border-color: #0284c7;">
                                        <span class="btn-text"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Unggah & Simpan ke MySQL</span>
                                        <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm" role="status"></span> Sedang Mengunggah & Memproses Dataset...</span>
                                    </button>
                                </div>
                            </form>

                            <!-- Alert Notifikasi Upload -->
                            <div id="upload-alert" class="alert d-none mt-3 mb-0" role="alert"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- KOTAK 2: PREPROCESSING TEKS -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-funnel-fill text-success fs-5"></i>
                        <span>2. KOTAK PREPROCESSING TEKS</span>
                    </div>
                    <span class="badge bg-success-subtle text-success">Sastrawi Pipeline</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Pipeline Preprocessing mencakup:
                            <ol class="mb-0 ps-3 mt-1">
                                <li><strong>Cleaning & Case Folding:</strong> Hapus URL, @mention, tanda baca/simbol, huruf kecil.</li>
                                <li><strong>Tokenizing:</strong> Pemisahan kata per kata.</li>
                                <li><strong>Stopword Removal:</strong> Hapus kata hubung/kata umum (kamus Sastrawi).</li>
                                <li><strong>Stemming:</strong> Mengembalikan ke bentuk kata dasar (algoritma Sastrawi).</li>
                            </ol>
                        </div>

                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">Data Mentah Siap Diproses:</div>
                                    <small class="text-muted">Diambil langsung dari tabel <code>tweets_raw</code> (hasil crawl atau upload)</small>
                                </div>
                                <span class="badge bg-primary fs-6 px-3 py-2" id="raw-available-badge">0 Data</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <!-- Tombol Jalankan Preprocessing -->
                        <div class="d-grid mb-2">
                            <button type="button" id="btn-run-preprocess" class="btn btn-success">
                                <span class="btn-text"><i class="bi bi-lightning-charge-fill me-1"></i> Jalankan Preprocessing</span>
                                <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm" role="status"></span> Sedang Memproses Teks Sastrawi...</span>
                            </button>
                        </div>

                        <!-- Alert Notifikasi Preprocess -->
                        <div id="proc-alert" class="alert d-none mt-3 mb-0" role="alert"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bagian Data Viewer & Preview Tabel -->
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs card-header-tabs" id="dataTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw-pane" type="button" role="tab">
                        <i class="bi bi-table me-1"></i> Data Mentah (Raw)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="proc-tab" data-bs-toggle="tab" data-bs-target="#proc-pane" type="button" role="tab">
                        <i class="bi bi-check2-circle me-1"></i> Hasil Preprocessing
                    </button>
                </li>
            </ul>

            <!-- Tombol Aksi (Unduh & Hapus Data) -->
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <!-- Tombol Download CSV -->
                <a href="api/export.php?type=raw" id="btn-download-raw" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Unduh CSV Mentah
                </a>
                <a href="api/export.php?type=processed" id="btn-download-proc" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download me-1"></i> Unduh CSV Preprocessed
                </a>

                <!-- Tombol Hapus per Tab -->
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCurrentTabData()">
                    <i class="bi bi-trash3 me-1"></i> Kosongkan Tab Ini
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="tab-content" id="dataTabContent">

                <!-- PANE 1: DATA MENTAH -->
                <div class="tab-pane fade show active" id="raw-pane" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i> Menampilkan hingga 10 data mentah terakhir</small>
                        <button class="btn btn-sm text-danger text-decoration-none" onclick="confirmDelete('raw', 'Kosongkan Seluruh Data Mentah')">
                            <i class="bi bi-trash"></i> Hapus Semua Data Mentah
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 15%;">Tweet / Data ID</th>
                                    <th style="width: 15%;">Username / Author</th>
                                    <th style="width: 15%;">Tanggal</th>
                                    <th style="width: 15%;">Timeslot</th>
                                    <th style="width: 48%;">Isi Teks Mentah</th>
                                    <th style="width: 7%; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="raw-table-body">
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                        Belum ada data mentah. Silakan lakukan Tarik Data atau Upload Dataset.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PANE 2: HASIL PREPROCESSING -->
                <div class="tab-pane fade" id="proc-pane" role="tabpanel">
                    <!-- Kartu Perbandingan Contoh Teks -->
                    <div id="comparison-card" class="card bg-light mb-4 d-none border">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bi bi-sliders me-1"></i> Contoh Perbandingan Tahapan Preprocessing:</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="fw-semibold small text-muted">1. Teks Asli (Original):</label>
                                    <div class="comparison-box" id="comp-original">-</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="fw-semibold small text-primary">2. Teks Bersih (Cleaned & Case Folded):</label>
                                    <div class="comparison-box text-primary" id="comp-cleaned">-</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="fw-semibold small text-success">3. Teks Akhir (Stopword & Stemming):</label>
                                    <div class="comparison-box text-success fw-semibold" id="comp-final">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i> Menampilkan hingga 10 data preprocessing terakhir</small>
                        <button class="btn btn-sm text-danger text-decoration-none" onclick="confirmDelete('processed', 'Kosongkan Seluruh Data Preprocessing')">
                            <i class="bi bi-trash"></i> Hapus Semua Data Preprocessing
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 15%;">Tweet / Data ID</th>
                                    <th style="width: 25%;">Teks Asli</th>
                                    <th style="width: 25%;">Teks Bersih</th>
                                    <th style="width: 28%;">Teks Akhir (Stemmed)</th>
                                    <th style="width: 7%; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="proc-table-body">
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                        Belum ada data preprocessing. Silakan jalankan Preprocessing.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Modal Konfirmasi Hapus Data -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Data</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="deleteModalMessage">Apakah Anda yakin ingin menghapus data ini?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Tindakan penghapusan ini bersifat permanen dan tidak dapat dibatalkan.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <span class="btn-text"><i class="bi bi-trash me-1"></i> Ya, Hapus Sekarang</span>
                    <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm"></span> Menghapus...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script Frontend Interaktif -->
<script>
    // State aplikasi
    let appState = {
        dbConnected: false,
        rawCount: 0,
        procCount: 0,
        activeTab: 'raw',
        pendingDelete: null,
    };

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    // Catat tab data viewer yang sedang aktif
    document.getElementById('raw-tab').addEventListener('shown.bs.tab', () => appState.activeTab = 'raw');
    document.getElementById('proc-tab').addEventListener('shown.bs.tab', () => appState.activeTab = 'processed');

    // Update Input Mode Badge
    function updateInputBadge(text) {
        document.getElementById('input-mode-badge').innerText = text;
    }

    // Toggle lihat/sembunyikan token
    document.getElementById('toggle-token-btn').addEventListener('click', function() {
        const input = document.getElementById('crawl-token');
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });

    // Cek jika halaman dibuka langsung dengan protokol file://
    if (window.location.protocol === 'file:') {
        const fileWarn = document.getElementById('file-protocol-warning');
        if (fileWarn) fileWarn.classList.remove('d-none');
    }

    // Muat Status dan Data saat pertama kali halaman terbuka (dengan timeout 5 detik agar tidak hanging)
    async function refreshStatus() {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);

        try {
            const res = await fetch('api/status.php', { signal: controller.signal });
            clearTimeout(timeoutId);
            const data = await res.json();

            appState.dbConnected = data.db_connected;
            appState.rawCount = data.raw_count || 0;
            appState.procCount = data.processed_count || 0;

            // Update badge DB
            const dbBadge = document.getElementById('db-badge');
            if (data.db_connected) {
                dbBadge.className = 'badge bg-success d-flex align-items-center gap-1 py-2 px-3';
                dbBadge.innerHTML = '<i class="bi bi-database-check"></i> MySQL Terhubung';
                document.getElementById('stat-system-status').innerText = 'Online';
                document.getElementById('stat-system-status').className = 'fw-bold mb-0 mt-2 text-success';
            } else {
                dbBadge.className = 'badge bg-danger d-flex align-items-center gap-1 py-2 px-3';
                dbBadge.innerHTML = '<i class="bi bi-database-x"></i> MySQL Terputus';
                document.getElementById('stat-system-status').innerText = 'Offline';
                document.getElementById('stat-system-status').className = 'fw-bold mb-0 mt-2 text-danger';
            }

            // Update statistik
            document.getElementById('stat-raw-count').innerText = appState.rawCount.toLocaleString();
            document.getElementById('stat-proc-count').innerText = appState.procCount.toLocaleString();
            document.getElementById('raw-available-badge').innerText = appState.rawCount.toLocaleString() + ' Data';

            // Set token default jika input masih kosong
            const tokenInput = document.getElementById('crawl-token');
            if (!tokenInput.value && data.default_token) {
                tokenInput.value = data.default_token;
            }

            // Render preview tabel mentah
            renderRawTable(data.raw_preview || []);

            // Render preview tabel preprocessing
            renderProcTable(data.processed_preview || []);

            // Render perbandingan sampel jika ada
            if (data.comparison_sample) {
                document.getElementById('comparison-card').classList.remove('d-none');
                document.getElementById('comp-original').innerText = data.comparison_sample.original_text || '-';
                document.getElementById('comp-cleaned').innerText = data.comparison_sample.cleaned_text || '-';
                document.getElementById('comp-final').innerText = data.comparison_sample.final_text || '-';
            } else {
                document.getElementById('comparison-card').classList.add('d-none');
            }

        } catch (err) {
            clearTimeout(timeoutId);
            console.error('Gagal mengambil status:', err);

            const dbBadge = document.getElementById('db-badge');
            if (dbBadge) {
                dbBadge.className = 'badge bg-warning text-dark d-flex align-items-center gap-1 py-2 px-3';
                dbBadge.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Status Server Tidak Merespon';
            }
            const statSys = document.getElementById('stat-system-status');
            if (statSys) {
                statSys.innerText = 'Offline';
                statSys.className = 'fw-bold mb-0 mt-2 text-warning';
            }
        }
    }

    function renderRawTable(rows) {
        const tbody = document.getElementById('raw-table-body');
        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                        Belum ada data mentah. Silakan lakukan Tarik Data atau Upload Dataset.
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr>
                <td><code>${escapeHtml(r.tweet_id || '')}</code></td>
                <td><span class="badge bg-light text-dark border">@${escapeHtml(r.username || '-')}</span></td>
                <td><small class="text-muted">${escapeHtml(r.created_at || r.crawled_at || '-')}</small></td>
                <td style="font-size: 0.9rem;">${escapeHtml(r.full_text || '')}</td>
                <td style="text-align: center;">
                    <button class="btn btn-sm btn-outline-danger" title="Hapus Tweet Ini" onclick="confirmDelete('single_raw', 'Hapus tweet mentah ID: ${escapeHtml(r.tweet_id)}', '${escapeHtml(r.tweet_id)}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderRawTable(rows) {
        const tbody = document.getElementById('raw-table-body');
        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                        Belum ada data mentah. Silakan lakukan Tarik Data atau Upload Dataset.
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr>
                <td><code>${escapeHtml(r.tweet_id || '')}</code></td>
                <td><span class="badge bg-light text-dark border">@${escapeHtml(r.username || '-')}</span></td>
                <td><small class="text-muted text-nowrap">${escapeHtml(r.created_at || r.crawled_at || '-')}</small></td>
                <td>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle text-nowrap">
                        ${escapeHtml(r.timeslot || '-')}
                    </span>
                </td>
                <td style="font-size: 0.9rem;">${escapeHtml(r.full_text || '')}</td>
                <td style="text-align: center;">
                    <button class="btn btn-sm btn-outline-danger" title="Hapus Tweet Ini" onclick="confirmDelete('single_raw', 'Hapus tweet mentah ID: ${escapeHtml(r.tweet_id)}', '${escapeHtml(r.tweet_id)}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Modal Konfirmasi Hapus
    function confirmDelete(target, description, tweetId = '') {
        appState.pendingDelete = { target, tweetId };
        document.getElementById('deleteModalMessage').innerHTML = `Apakah Anda yakin ingin: <br><strong>${escapeHtml(description)}</strong>?`;
        deleteModal.show();
    }

    function deleteCurrentTabData() {
        if (appState.activeTab === 'raw') {
            confirmDelete('raw', 'Kosongkan Seluruh Data Mentah (tweets_raw)');
        } else {
            confirmDelete('processed', 'Kosongkan Seluruh Data Preprocessing (tweets_processed)');
        }
    }

    // Eksekusi Hapus Data via API
    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        if (!appState.pendingDelete) return;

        const btn = document.getElementById('confirmDeleteBtn');
        btn.disabled = true;
        btn.querySelector('.btn-text').classList.add('d-none');
        btn.querySelector('.btn-spinner').classList.remove('d-none');

        try {
            const res = await fetch('api/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(appState.pendingDelete)
            });
            const data = await res.json();

            deleteModal.hide();
            await refreshStatus();

            alert(data.message || 'Data berhasil dihapus.');

        } catch (err) {
            alert('Gagal menghapus data: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').classList.remove('d-none');
            btn.querySelector('.btn-spinner').classList.add('d-none');
            appState.pendingDelete = null;
        }
    });

    // Handler Submit Form Crawling (Tarik Data X)
    document.getElementById('crawl-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btn-tarik-data');
        const alertBox = document.getElementById('crawl-alert');

        const token = document.getElementById('crawl-token').value.trim();
        const keyword = document.getElementById('crawl-keyword').value.trim();
        const since = document.getElementById('crawl-since').value;
        const until = document.getElementById('crawl-until').value;
        const limit = document.getElementById('crawl-limit').value;

        // UI Loading State
        btn.disabled = true;
        btn.querySelector('.btn-text').classList.add('d-none');
        btn.querySelector('.btn-spinner').classList.remove('d-none');
        alertBox.className = 'alert alert-info mt-3 mb-0';
        alertBox.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> Sedang menjalankan Tweet Harvest untuk keyword <strong>"${escapeHtml(keyword)}"</strong>... Proses ini dapat memakan waktu beberapa saat.`;
        alertBox.classList.remove('d-none');

        try {
            const response = await fetch('api/crawl.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token,
                    keyword,
                    since,
                    until,
                    limit
                })
            });

            const result = await response.json();

            if (result.success) {
                alertBox.className = 'alert alert-success mt-3 mb-0';
                alertBox.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${escapeHtml(result.message)}`;

                // Buka tab raw
                const rawTabBtn = new bootstrap.Tab(document.getElementById('raw-tab'));
                rawTabBtn.show();
            } else {
                alertBox.className = 'alert alert-danger mt-3 mb-0';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + escapeHtml(result.message).replace(/\n/g, '<br>');
            }

            // Perbarui data tabel & statistik
            await refreshStatus();

        } catch (err) {
            alertBox.className = 'alert alert-danger mt-3 mb-0';
            alertBox.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Terjadi kesalahan jaringan atau server: ' + escapeHtml(err.message);
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').classList.remove('d-none');
            btn.querySelector('.btn-spinner').classList.add('d-none');
        }
    });

    // Handler Submit Form Upload Dataset Eksternal
    document.getElementById('upload-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('upload-file-input');
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Harap pilih file dataset terlebih dahulu.');
            return;
        }

        const btn = document.getElementById('btn-upload-dataset');
        const alertBox = document.getElementById('upload-alert');

        const file = fileInput.files[0];
        const keyword = document.getElementById('upload-keyword-input').value.trim();
        const mode = document.querySelector('input[name="upload_mode"]:checked').value;

        const formData = new FormData();
        formData.append('dataset_file', file);
        formData.append('dataset_keyword', keyword);
        formData.append('upload_mode', mode);

        btn.disabled = true;
        btn.querySelector('.btn-text').classList.add('d-none');
        btn.querySelector('.btn-spinner').classList.remove('d-none');
        alertBox.className = 'alert alert-info mt-3 mb-0';
        alertBox.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Sedang mengunggah dan memetakan dataset <strong>"' + escapeHtml(file.name) + '"</strong> ke MySQL...';
        alertBox.classList.remove('d-none');

        try {
            const response = await fetch('api/upload.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alertBox.className = 'alert alert-success mt-3 mb-0';
                alertBox.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${escapeHtml(result.message)}<br><small class="text-muted"><i class="bi bi-info-circle me-1"></i> Kolom teks terdeteksi: <code>${escapeHtml(result.detected_column)}</code> | Label: <code>${escapeHtml(result.keyword)}</code></small>`;

                // Reset file input
                fileInput.value = '';

                // Buka tab raw data
                const rawTabBtn = new bootstrap.Tab(document.getElementById('raw-tab'));
                rawTabBtn.show();
            } else {
                alertBox.className = 'alert alert-danger mt-3 mb-0';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + escapeHtml(result.message);
            }

            await refreshStatus();

        } catch (err) {
            alertBox.className = 'alert alert-danger mt-3 mb-0';
            alertBox.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Terjadi kesalahan saat mengunggah dataset: ' + escapeHtml(err.message);
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').classList.remove('d-none');
            btn.querySelector('.btn-spinner').classList.add('d-none');
        }
    });

    // Handler Jalankan Preprocessing
    document.getElementById('btn-run-preprocess').addEventListener('click', async function() {
        const btn = document.getElementById('btn-run-preprocess');
        const alertBox = document.getElementById('proc-alert');

        if (appState.rawCount === 0) {
            alertBox.className = 'alert alert-warning mt-3 mb-0';
            alertBox.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Tidak ada data mentah di database. Silakan lakukan Tarik Data atau Upload Dataset terlebih dahulu.';
            alertBox.classList.remove('d-none');
            return;
        }

        // UI Loading State
        btn.disabled = true;
        btn.querySelector('.btn-text').classList.add('d-none');
        btn.querySelector('.btn-spinner').classList.remove('d-none');
        alertBox.className = 'alert alert-info mt-3 mb-0';
        alertBox.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Sedang menjalankan Pipeline Preprocessing (Cleaning, Case Folding, Tokenizing, Stopword Removal, Stemming Sastrawi)...';
        alertBox.classList.remove('d-none');

        try {
            const response = await fetch('api/preprocess.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const result = await response.json();

            if (result.success) {
                alertBox.className = 'alert alert-success mt-3 mb-0';
                alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + escapeHtml(result.message);
                // Buka tab preprocessing
                const procTabBtn = new bootstrap.Tab(document.getElementById('proc-tab'));
                procTabBtn.show();
            } else {
                alertBox.className = 'alert alert-danger mt-3 mb-0';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + escapeHtml(result.message);
            }

            // Perbarui data tabel & statistik
            await refreshStatus();

        } catch (err) {
            alertBox.className = 'alert alert-danger mt-3 mb-0';
            alertBox.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Terjadi kesalahan jaringan atau server: ' + escapeHtml(err.message);
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').classList.remove('d-none');
            btn.querySelector('.btn-spinner').classList.add('d-none');
        }
    });

    // Inisialisasi awal
    refreshStatus();
</script>

</body>
</html>
