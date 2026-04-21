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

// [A] TODAY'S SUMMARY
$ctxString .= "[A] TODAY'S SUMMARY:\n";
$ctxString .= "- Revenue: Rp " . number_format($c['summary']['revenue_today'], 0, ',', '.') . "\n";
$ctxString .= "- Entries today: " . $c['summary']['transactions_today'] . " vehicles\n";
$ctxString .= "- Currently parked (unpaid): " . $c['summary']['active_vehicles'] . " units\n";
$ctxString .= "- ALL-TIME Revenue: Rp " . number_format($c['summary']['all_time_revenue'], 0, ',', '.') . "\n";
$ctxString .= "- ALL-TIME Transactions: " . $c['summary']['all_time_paid_trx'] . " trx\n\n";

// [B] SLOT STATUS (Per Floor & Type)
$ctxString .= "[B] CURRENT PARKING SLOT STATUS:\n";
foreach ($c['slots'] as $s) {
    $ctxString .= "- [{$s['floor_name']} - {$s['slot_type']}] Total:{$s['total']} | Available:{$s['available']} | Occupied:{$s['occupied']} | Reserved:{$s['reserved']} | Maintenance:{$s['maintenance']}\n";
}
$ctxString .= "\n";

// [C] DAILY TREND (Last 30 Days)
$ctxString .= "[C] DAILY REVENUE TREND (LAST 30 DAYS):\n";
if (empty($c['daily_trend'])) {
    $ctxString .= "- No data recorded.\n";
} else {
    foreach ($c['daily_trend'] as $d) {
        $ctxString .= "- " . $d['date'] . ": Rp " . number_format($d['revenue'], 0, ',', '.') . " ({$d['volume']}/trx | Car:{$d['cars']}, Moto:{$d['motos']})\n";
    }
}
$ctxString .= "\n";

// [D] HOURLY DISTRIBUTION (Peak Time - 7 Days)
$ctxString .= "[D] HOURLY ENTRY DISTRIBUTION (LAST 7 DAYS):\n";
if (empty($c['hourly_distribution'])) {
    $ctxString .= "- No data recorded.\n";
} else {
    foreach ($c['hourly_distribution'] as $h) {
        $bar = str_repeat("█", min(20, (int)($h['total_entries'] / max(1, $c['summary']['all_time_paid_trx']) * 100)));
        $ctxString .= "- Hour " . str_pad($h['hour'], 2, "0", STR_PAD_LEFT) . ":00 -> {$h['total_entries']} vehicles (Car:{$h['cars']} Moto:{$h['motos']})\n";
    }
}
$ctxString .= "\n";

// [E] VEHICLE STATISTICS
$ctxString .= "[E] VEHICLE STATISTICS:\n";
foreach ($c['vehicle_stats'] as $v) {
    $ctxString .= "- {$v['vehicle_type']}: {$v['total_registered']} registered | {$v['total_count']} trx | Revenue: Rp " . number_format($v['total_revenue'], 0, ',', '.') . "\n";
}
$ctxString .= "\n";

// [F] OPERATOR PERFORMANCE
$ctxString .= "[F] OPERATOR PERFORMANCE:\n";
foreach ($c['operator_performance'] as $op) {
    $ctxString .= "- {$op['full_name']} (Shift: {$op['shift']}): {$op['total_transactions']} trx | Revenue: Rp " . number_format($op['total_revenue_handled'], 0, ',', '.') . " | Average duration: " . number_format($op['avg_duration_hours'], 1) . " hours\n";
}
$ctxString .= "\n";

// [G] RESERVATIONS
$ctxString .= "[G] ACTIVE RESERVATIONS:\n";
if (empty($c['active_reservations'])) {
    $ctxString .= "- No active reservations.\n";
} else {
    foreach ($c['active_reservations'] as $r) {
        $ctxString .= "- [{$r['reservation_code']}] {$r['plate_number']} ({$r['vehicle_type']}) -> Slot {$r['slot_number']} ({$r['floor_name']}) | {$r['reserved_from']} to {$r['reserved_until']} | Status: {$r['status']}\n";
    }
}
$ctxString .= "\n";

// [H] LAST 10 TRANSACTIONS
$ctxString .= "[H] LAST 10 TRANSACTIONS:\n";
foreach ($c['recent_transactions'] as $t) {
    $ctxString .= "- [{$t['ticket_code']}] {$t['plate_number']} ({$t['vehicle_type']}) Slot:{$t['slot_number']} In:{$t['check_in_time']} Out:" . ($t['check_out_time'] ?? 'parked') . " Fee:Rp" . number_format($t['total_fee'] ?? 0, 0, ',', '.') . " Status:{$t['payment_status']} Op:{$t['operator']}\n";
}
$ctxString .= "\n";

// [I] PAYMENT METHODS
$ctxString .= "[I] PAYMENT METHOD BREAKDOWN:\n";
foreach ($c['payment_methods'] as $pm) {
    $ctxString .= "- {$pm['payment_method']}: {$pm['count']} trx | Rp " . number_format($pm['revenue'], 0, ',', '.') . "\n";
}
$ctxString .= "\n";

// [J] GATE SCAN LOG (24 Hours)
$ctxString .= "[J] GATE SCANNER ACTIVITY:\n";
if (empty($c['gate_log'])) {
    $ctxString .= "- No scan activity.\n";
} else {
    foreach ($c['gate_log'] as $g) {
        $ctxString .= "- Gate {$g['scan_type']} ({$g['gate_action']}): {$g['count']} scans\n";
    }
}
$ctxString .= "\n";

// [K] PARKING RATES
$ctxString .= "[K] PARKING RATES:\n";
foreach ($c['rates'] as $r) {
    $ctxString .= "- {$r['vehicle_type']}: First hour Rp{$r['first_hour_rate']} | Next hour Rp{$r['next_hour_rate']} | Max/day Rp{$r['daily_max_rate']}\n";
}
$ctxString .= "\n";

// [L] FLOOR CAPACITY
$ctxString .= "[L] TOTAL CAPACITY PER FLOOR:\n";
foreach ($c['floors'] as $f) {
    $ctxString .= "- {$f['floor_name']} ({$f['floor_code']}): {$f['total_car_slots']} car slots + {$f['total_motorcycle_slots']} moto slots\n";
}

// ── 2. OPENROUTER CONFIGURATION ───────────────────────────────────────────
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

// [LEAD NOTE]: Credentials moved to .env for security.
$apiKey  = getenv('OPENROUTER_API_KEY') ?: '';
$modelId = getenv('OPENROUTER_MODEL') ?: "google/gemini-2.0-flash-001"; // Default model if not set

$systemPrompt = "
You are 'Cereza', an exclusive intelligent assistant for SmartParking Enterprise.

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
