<?php
/**
 * api/ai_chat.php
 * Endpoint for Parkhere AI Assistant integration with OpenRouter
 * ULTIMATE ENTERPRISE VERSION (WITH MEMORY FIX) 🚀
 */

header('Content-Type: application/json');
session_start(); // Wajib di paling atas sebelum logic apapun

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

// ── GET USER IDENTITY (Allows Cereza to know who is logged in) ──
$userName = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'staff';

// Security: Only allow JSON POST
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$query = $data['query'] ?? '';
$history = $data['history'] ?? [];

if (!$query) {
    echo json_encode(['error' => 'Query is required.']);
    exit;
}

// ── 1. GATHER SYSTEM CONTEXT (WITH 60-SECOND CACHE) ───────────────────────
if (!isset($_SESSION['ai_context_cache']) || (time() - $_SESSION['ai_context_time'] > 60)) {
    $_SESSION['ai_context_cache'] = get_ai_context_data($pdo);
    $_SESSION['ai_context_time'] = time();
}
$context = $_SESSION['ai_context_cache'];
$c = $context; 
$s = $c['summary'];

// ── Calculate Day-Over-Day Revenue Growth ──
$revToday = (float)$s['revenue_today'];
$revYesterday = (float)$s['revenue_yesterday'];
$growthPct = 0;
if ($revYesterday > 0) {
    $growthPct = round((($revToday - $revYesterday) / $revYesterday) * 100, 1);
}
$growthStr = ($growthPct > 0 ? "+{$growthPct}%" : "{$growthPct}%");

// ── Build comprehensive readable context string ──────────────────────────
$ctxString  = "============================\n";
$ctxString .= " SYSTEM: " . $c['system_name'] . "\n";
$ctxString .= " CURRENT TIME: " . $c['generated_at'] . "\n";
$ctxString .= "============================\n\n";

// [A] PERFORMANCE SNAPSHOT
$ctxString .= "[A] PERFORMANCE SNAPSHOT:\n";
$ctxString .= "DAY-OVER-DAY GROWTH: Revenue is " . $growthStr . " compared to yesterday.\n\n";
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
    $ctxString .= "- {$r['vehicle_type']}: First Hr Rp{$r['first_hour_rate']} | Next Hr Rp{$r['next_hour_rate']} | Lost Ticket Fine Rp{$r['lost_ticket_fine']}\n";
}
$ctxString .= "\n";

// [L] AREA DEFINITION
$ctxString .= "[L] AREA DEFINITION:\n";
$ctxString .= "- Standard Regular: Open for all vehicles on a first-come, first-served basis.\n";
$ctxString .= "- Reservation Only: Exclusively for pre-booked vehicles with high-priority service.\n\n";

// [M] SYSTEM TOPOLOGY & UI NAVIGATION (With Active Deep Links)
$ctxString .= "[M] WEBSITE NAVIGATION & FEATURES (SITEMAP):\n";
$ctxString .= "- Dashboard (Main) -> URL: index.php\n";
$ctxString .= "- Gate Entry Simulator (Operations) -> URL: gate_simulator.php\n";
$ctxString .= "- Gate Exit (Operations) -> URL: gate_exit.php\n";
$ctxString .= "- Active Vehicles (Operations) -> URL: dashboard.php\n";
$ctxString .= "- Public Reservation (Customer Portal) -> URL: reserve.php\n";
$ctxString .= "- Security Scan Logs (Operations) -> URL: scan_log.php\n";
$ctxString .= "- Revenue & Analytics (Reports) -> URL: dashboard_revenue.php\n";
$ctxString .= "- Master Rates (Admin) -> URL: admin_rates.php\n";
$ctxString .= "- Master Slots (Admin) -> URL: admin_slots.php\n\n";

// [N] ANOMALY FLAGS (Auto-generated)
$alerts = [];
if ($occupancyPct > 85) $alerts[] = "⚠️ HIGH OCCUPANCY: " . $occupancyPct . "%";
if ($growthPct < -50) $alerts[] = "🔴 REVENUE DROP: " . $growthStr . " vs yesterday";
if ($s['active_vehicles'] == 0 && date('H') > 9) $alerts[] = "⚠️ Zero vehicles during business hours";

$ctxString .= "[N] SYSTEM ALERTS:\n";
$ctxString .= empty($alerts) ? "- All systems nominal.\n" : implode("\n", array_map(fn($a) => "- $a", $alerts)) . "\n";

// ── 2. OPENROUTER CONFIGURATION ───────────────────────────────────────────
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

// [LEAD NOTE]: Credentials moved to .env for security.
$apiKey  = getenv('OPENROUTER_API_KEY') ?: '';
$modelId = getenv('OPENROUTER_MODEL') ?: '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'AI service not configured.']);
    exit;
}

$systemPrompt = "
You are 'Cereza', a Senior Business Intelligence and System Co-Pilot for Parkhere Enterprise.
You are currently assisting: **{$userName}** (Role: **" . strtoupper($userRole) . "**).

## BEHAVIORAL DIRECTIVES:
1. **Dynamic Tone:** Be conversational and highly helpful for simple queries. Switch to a seasoned, authoritative Executive Data Analyst tone ONLY when asked to analyze complex data, revenue, or trends.
2. **Actionable Intelligence:** When analyzing data or trends, ALWAYS end your response with 1-2 specific business recommendations (e.g., operator shift adjustments, rate changes, traffic handling).
3. **Variance Analysis:** Highlight the 'DAY-OVER-DAY GROWTH' percentage prominently when discussing today's revenue.
4. **No Hallucinations:** Base all logic, math, and predictions strictly on the provided context.
5. **System Co-Pilot (Deep Linking):** If the user asks how to perform an action, where to find a feature, or requests navigation, guide them by providing a CLICKABLE Markdown link using the exact URL from the [M] WEBSITE NAVIGATION data (e.g., 'Please navigate to the [Master Rates](admin_rates.php) menu').
6. **No Robotic Fillers:** ABSOLUTELY DO NOT use repetitive, robotic, or filler phrases like 'You can access' or 'Here is the information'. Provide the links and answers directly and naturally.
7. **Natural Interaction:** DO NOT start every message with a formal greeting or the user's name. Address the user by name ONLY at the very beginning of a new session or when providing a significant executive summary. Keep follow-up replies direct and conversational.

## VISUAL FORMATTING RULES:
To ensure your responses are easy to read, follow these rules:
- **Spacing:** ALWAYS use double line breaks (`\\n\\n`) between paragraphs. Never create walls of text.
- **Flexible Structure:** Use Markdown headings (`###`) and emojis ONLY when generating analytical reports. Respond conversationally for simple chats.
- **Bullet Points:** Use standard bullet points (`-`) when listing items, steps, or recommendations.
- **Emphasis:** Use **bold** text to highlight key numbers (e.g., **Rp 5.000.000** or **+15%**) or critical terms.
- **Language:** Always respond naturally in English, as this is an enterprise-grade system.

SYSTEM CONTEXT:\n" . $ctxString;

// ── 3. BUILD MESSAGES WITH MEMORY ─────────────────────────────────────────
$messages = [
    ["role" => "system", "content" => $systemPrompt]
];

// Inject past history into AI's brain
if (is_array($history)) {
    foreach ($history as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            $messages[] = [
                "role" => $msg['role'],
                "content" => $msg['content']
            ];
        }
    }
}

// Append the new current query
$messages[] = ["role" => "user", "content" => $query];

$payload = [
    "model" => $modelId, 
    "messages" => $messages, // Pakai variabel $messages yang sudah digabung dengan history
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
    "X-Title: Parkhere Enterprise"
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'cURL Error: ' . $err]);
} else {
    $resData = json_decode($response, true);
    $aiMsg = $resData['choices'][0]['message']['content'] ?? 'Sorry, I am currently unable to process data at this moment.';
    echo json_encode(['response' => $aiMsg]);
}