<?php
/**
 * api/ai_chat.php
 * Endpoint for SmartParking AI Assistant integration with OpenRouter
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
$s = $c['summary'];

// ── Build comprehensive readable context string ──────────────────────────
$ctxString  = "============================\n";
$ctxString .= " SISTEM: " . $c['system_name'] . "\n";
$ctxString .= " CURRENT TIME: " . $c['generated_at'] . "\n";
$ctxString .= "============================\n\n";

// [A] PERFORMANCE SNAPSHOT
$ctxString .= "[A] PERFORMANCE SNAPSHOT:\n";
$ctxString .= "TODAY:\n";
$ctxString .= "- Revenue Today: Rp " . number_format($s['revenue_today'], 0, ',', '.') . "\n";
$ctxString .= "- Transactions Today: " . $s['transactions_today'] . " vehicles\n";
$ctxString .= "- Total Entries Today: " . $s['entries_today'] . " vehicles\n";
$ctxString .= "YESTERDAY:\n";
$ctxString .= "- Revenue Yesterday: Rp " . number_format($s['revenue_yesterday'], 0, ',', '.') . "\n";
$ctxString .= "- Transactions Yesterday: " . $s['transactions_yesterday'] . " vehicles\n";
$ctxString .= "ALL-TIME:\n";
$ctxString .= "- Total Revenue: Rp " . number_format($s['all_time_revenue'], 0, ',', '.') . "\n";
$ctxString .= "- Total Transactions: " . $s['all_time_transactions'] . "\n";
$ctxString .= "- System Start Date: " . ($s['first_record_date'] ?? 'N/A') . "\n\n";

// [B] OCCUPANCY STATUS
$totalSlots = (int)($s['total_slots'] ?? 0);
$occupiedSlots = (int)($s['occupied_slots'] ?? 0);
$reservedSlots = (int)($s['reserved_slots'] ?? 0);
$availableSlots = (int)($s['available_slots'] ?? 0);
$occupancyPct = $totalSlots > 0 ? round(($occupiedSlots / $totalSlots) * 100, 1) : 0;
$utilizationPct = $totalSlots > 0 ? round((($occupiedSlots + $reservedSlots) / $totalSlots) * 100, 1) : 0;

$ctxString .= "[B] REAL-TIME OCCUPANCY:\n";
$ctxString .= "- Active Vehicles (Transactions Unpaid): " . $s['active_vehicles'] . " units\n";
$ctxString .= "- Total Slots: " . $totalSlots . " units\n";
$ctxString .= "- Occupied Slots: " . $occupiedSlots . " units\n";
$ctxString .= "- Reserved Slots: " . $reservedSlots . " units\n";
$ctxString .= "- Available Slots: " . $availableSlots . " units\n";
$ctxString .= "- Occupancy Rate (Occupied/Total): " . $occupancyPct . "%\n";
$ctxString .= "- Utilization Rate ((Occupied+Reserved)/Total): " . $utilizationPct . "%\n\n";

// [C] SLOT STATUS (Per Category & Type)
$ctxString .= "[C] PARKING SLOT BREAKDOWN:\n";
foreach ($c['slots'] as $sl) {
    $ctxString .= "- [{$sl['zone_name']} - {$sl['slot_type']}] Available:{$sl['available']} | Occupied:{$sl['occupied']} | Reserved:{$sl['reserved']}\n";
}
$ctxString .= "\n";

// [D] DAILY TREND (Last 30 Days)
$ctxString .= "[D] REVENUE TREND (LAST 30 DAYS):\n";
if (empty($c['daily_trend'])) {
    $ctxString .= "- No data recorded.\n";
} else {
    foreach ($c['daily_trend'] as $d) {
        $ctxString .= "- " . $d['date'] . ": Rp " . number_format($d['revenue'], 0, ',', '.') . " ({$d['volume']} trx | Car:{$d['cars']}, Moto:{$d['motos']})\n";
    }
}
$ctxString .= "\n";

// [E] HOURLY DISTRIBUTION (Traffic Peaks)
$ctxString .= "[E] HOURLY TRAFFIC (LAST 7 DAYS):\n";
if (empty($c['hourly_distribution'])) {
    $ctxString .= "- No data recorded.\n";
} else {
    foreach ($c['hourly_distribution'] as $h) {
        $ctxString .= "- Hour " . str_pad($h['hour'], 2, "0", STR_PAD_LEFT) . ":00 -> {$h['total_entries']} vehicles (Car:{$h['cars']} Moto:{$h['motos']})\n";
    }
}
$ctxString .= "\n";

// [F] VEHICLE STATISTICS
$ctxString .= "[F] VEHICLE TYPE STATS:\n";
foreach ($c['vehicle_stats'] as $v) {
    $ctxString .= "- {$v['vehicle_type']}: Registered:{$v['total_registered']} | Total Trx:{$v['total_count']} | Total Rev: Rp " . number_format($v['total_revenue'], 0, ',', '.') . "\n";
}
$ctxString .= "\n";

// [G] OPERATOR ACTIVITY (Last 7 Days)
$ctxString .= "[G] OPERATOR ACTIVITY (LAST 7 DAYS):\n";
foreach ($c['operator_performance'] as $op) {
    $ctxString .= "- {$op['full_name']} ({$op['shift']}): {$op['total_transactions']} trx | Rp " . number_format($op['total_revenue_handled'], 0, ',', '.') . " | Avg Duration: " . number_format($op['avg_duration_hours'], 1) . "h\n";
}
$ctxString .= "\n";

// [H] ACTIVE RESERVATIONS
$ctxString .= "[H] LATEST RESERVATIONS:\n";
if (empty($c['active_reservations'])) {
    $ctxString .= "- No active reservations.\n";
} else {
    foreach ($c['active_reservations'] as $r) {
        $ctxString .= "- [" . ($r['reservation_code'] ?? '-') . "] " . ($r['plate_number'] ?? '-') . " (" . ($r['vehicle_type'] ?? 'unknown') . ") -> Slot " . ($r['slot_number'] ?? '-') . " (" . ($r['zone'] ?? '-') . ") | From:" . ($r['reserved_from'] ?? '-') . " | Status:" . ($r['status'] ?? '-') . "\n";
    }
}
$ctxString .= "\n";

// [I] RECENT TRANSACTIONS (Last 10)
$ctxString .= "[I] RECENT TRANSACTIONS:\n";
foreach ($c['recent_transactions'] as $t) {
    $ctxString .= "- [" . ($t['ticket_code'] ?? '-') . "] " . ($t['plate_number'] ?? '-') . " (" . ($t['vehicle_type'] ?? 'unknown') . ") Slot:" . ($t['slot_number'] ?? '-') . " In:" . ($t['check_in_time'] ?? '-') . " Out:" . ($t['check_out_time'] ?? 'PARKED') . " Fee:Rp" . number_format($t['total_fee'] ?? 0, 0, ',', '.') . " Status:" . ($t['payment_status'] ?? '-') . "\n";
}
$ctxString .= "\n";

// [J] PAYMENT METHODS (Today)
$ctxString .= "[J] PAYMENT METHODS (TODAY):\n";
foreach ($c['payment_methods'] as $pm) {
    $ctxString .= "- {$pm['payment_method']}: {$pm['count']} trx | Rp " . number_format($pm['revenue'], 0, ',', '.') . "\n";
}
$ctxString .= "\n";

// [K] PARKING RATES
$ctxString .= "[K] PARKING RATES:\n";
foreach ($c['rates'] as $r) {
    $ctxString .= "- {$r['vehicle_type']}: First Hr Rp{$r['first_hour_rate']} | Next Hr Rp{$r['next_hour_rate']} | Daily Max Rp{$r['daily_max_rate']}\n";
}
$ctxString .= "\n";

// [L] AREA DEFINITION
$ctxString .= "[L] AREA DEFINITION:\n";
$ctxString .= "- Standard Regular: Open for all vehicles on a first-come, first-served basis.\n";
$ctxString .= "- Reservation Only: Exclusively for pre-booked vehicles with high-priority service.\n";

// ── 2. OPENROUTER CONFIGURATION ───────────────────────────────────────────
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

// [LEAD NOTE]: Credentials moved to .env for security.
$apiKey  = getenv('OPENROUTER_API_KEY') ?: '';
$modelId = getenv('OPENROUTER_MODEL') ?: '';

$systemPrompt = "
You are 'Cereza', the central intelligence of the SmartParking Enterprise ecosystem. 
SmartParking is an all-in-one unified parking platform designed to manage parking operations for many malls, commercial hubs, and retail giants.
For this demonstration, we are currently showcasing the operations at 'Berserk Mall', but our system is built to scale across thousands of locations.

## OUTPUT FORMAT RULES (MANDATORY):
- Use **Markdown** for all responses.
- Use headings `## Title` to divide major topics.
- Use **Markdown tables** for comparative data, trends, or recommendations.
- Use bullet lists (`-`) for brief points.
- Use `**text**` for important numbers or keywords.
- **DO NOT write overly long responses.** Focus on the most critical insights. Limit to a maximum of 400 words.
- If data is not available, state it clearly and briefly.

## TASKS:
1. Answer operational and analytical questions based on context data.
2. Provide **1-2 actionable strategic recommendations** at the end of every analysis.
3. Identify peak times, trends, and anomalies from historical data.
4. Do not hallucinate — only use data present in the context.
5. **IMPORTANT:** We no longer use 'Ground Floors' or 'Floor Levels'. All parking is categorized into two main areas: **Standard Regular** and **Reservation Only**. If a user asks about floors, explain that we have transitioned to this new high-efficiency categorization.
6. For occupancy calculations, always use the explicit **Total Slots** value from context. Never infer total capacity from other fields.

SYSTEM CONTEXT:\n" . $ctxString;

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
    $aiMsg = $resData['choices'][0]['message']['content'] ?? 'Sorry, I cannot respond at the moment.';
    echo json_encode(['response' => $aiMsg]);
}
