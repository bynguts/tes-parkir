<?php
/**
 * api/ai_chat.php
 * Endpoint for Archive AI Assistant integration with OpenRouter
 */

header('Content-Type: application/json');

// Catch all PHP errors and return them as JSON (prevents HTML error pages)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => "PHP Error [$errno]: $errstr in $errfile:$errline"]);
    exit;
});
set_exception_handler(function($e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()]);
    exit;
});

require_once '../includes/auth_guard.php';

// Security: Only allow JSON POST
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$query = $data['query'] ?? '';

if (!$query) {
    echo json_encode(['error' => 'Query is required.']);
    exit;
}

// ── 1. GATHER SYSTEM CONTEXT (REAL-TIME) ──────────────────────────────────
$context = get_ai_context_data($pdo);
$c = $context; // shortcut

// ── Build comprehensive readable context string ──────────────────────────
$ctxString  = "============================\n";
$ctxString .= " SISTEM: " . $c['system_name'] . "\n";
$ctxString .= " GENERATED: " . $c['generated_at'] . "\n";
$ctxString .= "============================\n\n";

// [A] RINGKASAN HARI INI
$ctxString .= "[A] RINGKASAN HARI INI:\n";
$ctxString .= "- Pendapatan: Rp " . number_format($c['summary']['revenue_today'], 0, ',', '.') . "\n";
$ctxString .= "- Transaksi masuk hari ini: " . $c['summary']['transactions_today'] . " kendaraan\n";
$ctxString .= "- Kendaraan masih parkir (belum bayar): " . $c['summary']['active_vehicles'] . " unit\n";
$ctxString .= "- ALL-TIME Revenue: Rp " . number_format($c['summary']['all_time_revenue'], 0, ',', '.') . "\n";
$ctxString .= "- ALL-TIME Transaksi Selesai: " . $c['summary']['all_time_paid_trx'] . " trx\n\n";

// [B] STATUS SLOT (Per Lantai & Tipe)
$ctxString .= "[B] STATUS SLOT PARKIR SAAT INI:\n";
foreach ($c['slots'] as $s) {
    $ctxString .= "- [{$s['floor_name']} - {$s['slot_type']}] Total:{$s['total']} | Tersedia:{$s['available']} | Terisi:{$s['occupied']} | Reservasi:{$s['reserved']} | Maintenance:{$s['maintenance']}\n";
}
$ctxString .= "\n";

// [C] TREN HARIAN (30 Hari)
$ctxString .= "[C] TREN PENDAPATAN HARIAN (30 HARI TERAKHIR):\n";
if (empty($c['daily_trend'])) {
    $ctxString .= "- Belum ada data.\n";
} else {
    foreach ($c['daily_trend'] as $d) {
        $ctxString .= "- " . $d['date'] . ": Rp " . number_format($d['revenue'], 0, ',', '.') . " ({$d['volume']} trx | Mobil:{$d['cars']}, Motor:{$d['motorcycles']})\n";
    }
}
$ctxString .= "\n";

// [D] DISTRIBUSI PER JAM (Peak Time - 7 Hari)
$ctxString .= "[D] DISTRIBUSI MASUK PER JAM (7 HARI TERAKHIR):\n";
if (empty($c['hourly_distribution'])) {
    $ctxString .= "- Belum ada data.\n";
} else {
    foreach ($c['hourly_distribution'] as $h) {
        $bar = str_repeat("█", min(20, (int)($h['total_entries'] / max(1, $c['summary']['all_time_paid_trx']) * 100)));
        $ctxString .= "- Jam " . str_pad($h['hour'], 2, "0", STR_PAD_LEFT) . ":00 -> {$h['total_entries']} kendaraan (Mobil:{$h['cars']} Motor:{$h['motorcycles']})\n";
    }
}
$ctxString .= "\n";

// [E] STATISTIK KENDARAAN
$ctxString .= "[E] STATISTIK KENDARAAN:\n";
foreach ($c['vehicle_stats'] as $v) {
    $ctxString .= "- {$v['vehicle_type']}: {$v['total_registered']} terdaftar | {$v['total_transactions']} trx | Revenue: Rp " . number_format($v['total_revenue'], 0, ',', '.') . "\n";
}
$ctxString .= "\n";

// [F] PERFORMA OPERATOR
$ctxString .= "[F] PERFORMA OPERATOR:\n";
foreach ($c['operator_performance'] as $op) {
    $ctxString .= "- {$op['full_name']} (Shift: {$op['shift']}): {$op['total_transactions']} trx | Revenue: Rp " . number_format($op['total_revenue_handled'], 0, ',', '.') . " | Rata-rata parkir: " . number_format($op['avg_duration_hours'], 1) . " jam\n";
}
$ctxString .= "\n";

// [G] RESERVASI
$ctxString .= "[G] RESERVASI AKTIF:\n";
if (empty($c['active_reservations'])) {
    $ctxString .= "- Tidak ada reservasi aktif.\n";
} else {
    foreach ($c['active_reservations'] as $r) {
        $ctxString .= "- [{$r['reservation_code']}] {$r['plate_number']} ({$r['vehicle_type']}) -> Slot {$r['slot_number']} ({$r['floor_name']}) | {$r['reserved_from']} s/d {$r['reserved_until']} | Status: {$r['status']}\n";
    }
}
$ctxString .= "\n";

// [H] 10 TRANSAKSI TERAKHIR
$ctxString .= "[H] 10 TRANSAKSI TERAKHIR:\n";
foreach ($c['recent_transactions'] as $t) {
    $ctxString .= "- [{$t['ticket_code']}] {$t['plate_number']} ({$t['vehicle_type']}) Slot:{$t['slot_number']} In:{$t['check_in_time']} Out:" . ($t['check_out_time'] ?? 'masih parkir') . " Fee:Rp" . number_format($t['total_fee'] ?? 0, 0, ',', '.') . " Status:{$t['payment_status']} Op:{$t['operator']}\n";
}
$ctxString .= "\n";

// [I] METODE PEMBAYARAN
$ctxString .= "[I] BREAKDOWN METODE PEMBAYARAN:\n";
foreach ($c['payment_methods'] as $pm) {
    $ctxString .= "- {$pm['payment_method']}: {$pm['count']} trx | Rp " . number_format($pm['revenue'], 0, ',', '.') . "\n";
}
$ctxString .= "\n";

// [J] GATE SCAN LOG (24 Jam)
$ctxString .= "[J] AKTIVITAS GATE SCANNER (24 JAM TERAKHIR):\n";
if (empty($c['gate_log_24h'])) {
    $ctxString .= "- Tidak ada aktivitas scan.\n";
} else {
    foreach ($c['gate_log_24h'] as $g) {
        $ctxString .= "- Gate {$g['scan_type']}: {$g['total_scans']} scan | Cocok:{$g['matched']} | Dibuka:{$g['opened']} | Ditolak:{$g['rejected']}\n";
    }
}
$ctxString .= "\n";

// [K] TARIF PARKIR
$ctxString .= "[K] TARIF PARKIR:\n";
foreach ($c['rates'] as $r) {
    $ctxString .= "- {$r['vehicle_type']}: Jam pertama Rp{$r['first_hour_rate']} | Jam berikutnya Rp{$r['next_hour_rate']} | Maks/hari Rp{$r['daily_max_rate']}\n";
}
$ctxString .= "\n";

// [L] KAPASITAS LANTAI
$ctxString .= "[L] KAPASITAS TOTAL PER LANTAI:\n";
foreach ($c['floors'] as $f) {
    $ctxString .= "- {$f['floor_name']} ({$f['floor_code']}): {$f['total_car_slots']} slot mobil + {$f['total_motorcycle_slots']} slot motor\n";
}

// ── 2. OPENROUTER CONFIGURATION ───────────────────────────────────────────
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

// [LEAD NOTE]: Paste your OpenRouter API Key and preferred Model ID here.
$apiKey  = "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";
$modelId = "nvidia/nemotron-3-super-120b-a12b:free"; // Ganti dengan model pilihan Anda (e.g., 'anthropic/claude-3.5-sonnet')

$systemPrompt = "
Anda adalah 'Archive AI', asisten cerdas eksklusif untuk SmartParking Enterprise.

## ATURAN FORMAT OUTPUT (WAJIB):
- Gunakan **Markdown** untuk semua respons.
- Gunakan heading `## Judul` untuk membagi topik besar.
- Gunakan **tabel Markdown** untuk data perbandingan, tren, atau rekomendasi.
- Gunakan bullet list (`-`) untuk poin-poin singkat.
- Gunakan `**teks**` untuk angka penting atau kata kunci.
- **JANGAN menulis respons yang terlalu panjang.** Fokus pada insight paling penting. Batasi maksimal 400 kata.
- Jika data tidak tersedia, katakan dengan jelas dan singkat.

## TUGAS:
1. Jawab pertanyaan operasional dan analitik berdasarkan data konteks.
2. Berikan **1-2 rekomendasi strategis** yang actionable di akhir setiap analisis.
3. Identifikasi peak time, tren, dan anomali dari data historis.
4. Jangan berhalusinasi — hanya gunakan data yang ada di konteks.

KONTEKS SISTEM:\n" . $ctxString;

$payload = [
    "model" => $modelId, 
    "messages" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $query]
    ],
    "temperature" => 0.7
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json",
    "HTTP-Referer: http://localhost/parking_system", // Required by OpenRouter
    "X-Title: SmartParking Enterprise"
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'cURL Error: ' . $err]);
} else {
    $resData = json_decode($response, true);
    $aiMsg = $resData['choices'][0]['message']['content'] ?? 'Maaf, saya tidak bisa merespon saat ini.';
    echo json_encode(['response' => $aiMsg]);
}
