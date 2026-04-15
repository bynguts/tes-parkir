<?php
/**
 * includes/functions.php — Shared utility functions
 * [3NF FIX] get_slot_summary tidak perlu diubah karena hanya query parking_slot langsung.
 *           Namun fungsi lain yang menyebut floor varchar perlu diperhatikan.
 */

// ── CSRF ──────────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token.']));
    }
}

// ── Fee calculation ───────────────────────────────────────────────────────
function calculate_fee(int $minutes, float $first_rate, float $next_rate, float $max_rate): array {
    $minutes = max(1, $minutes);
    $hours   = max(1, (int)ceil($minutes / 60));
    $fee     = $hours <= 1
        ? $first_rate
        : $first_rate + ($hours - 1) * $next_rate;
    $fee = min($fee, $max_rate);
    return [
        'hours'          => $hours,
        'duration_hours' => round($minutes / 60, 2),
        'total_fee'      => $fee,
    ];
}

// ── Ticket code generator ─────────────────────────────────────────────────
function generate_ticket_code(PDO $pdo): string {
    do {
        $code = 'TKT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare("SELECT ticket_id FROM ticket WHERE ticket_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ── Reservation code generator ────────────────────────────────────────────
function generate_reservation_code(PDO $pdo): string {
    do {
        $code = 'RES-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare("SELECT reservation_id FROM reservation WHERE reservation_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ── Guest plate generator ─────────────────────────────────────────────────
function generate_guest_plate(PDO $pdo): string {
    do {
        $plate = 'GUEST-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt  = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
        $stmt->execute([$plate]);
    } while ($stmt->fetch());
    return $plate;
}

// ── Format currency ───────────────────────────────────────────────────────
function fmt_idr(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// ── Slot availability summary ─────────────────────────────────────────────
// [3NF NOTE] Fungsi ini hanya query slot_type & status dari parking_slot,
//            tidak membutuhkan JOIN ke floor — tidak ada perubahan diperlukan.
function get_slot_summary(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT slot_type,
               SUM(status = 'available') AS avail,
               COUNT(*) AS total
        FROM parking_slot
        GROUP BY slot_type
    ");
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['slot_type']] = [
            'avail' => (int)$row['avail'],
            'total' => (int)$row['total'],
        ];
    }
    return $result;
}

// ── Role check ────────────────────────────────────────────────────────────
function require_role(string ...$roles): void {
    $user_role = $_SESSION['role'] ?? '';
    if (!in_array($user_role, $roles, true)) {
        http_response_code(403);
        header('Location: index.php?error=forbidden');
        exit;
    }
}

// ── Attendance helpers ────────────────────────────────────────────────────
function is_on_duty(): bool {
    if (($_SESSION['role'] ?? '') === 'superadmin') return true;
    return !empty($_SESSION['staff_id']);
}

function get_active_attendance(PDO $pdo, ?string $filter_type = null): array {
    $where = "WHERE sa.is_active = 1";
    if ($filter_type) {
        $where .= " AND o.staff_type = " . $pdo->quote($filter_type);
    }
    
    return $pdo->query("
        SELECT sa.*, o.full_name, o.staff_type, o.shift, au.username
        FROM shift_attendance sa
        JOIN operator o ON sa.staff_id = o.operator_id
        JOIN admin_users au ON sa.user_id = au.user_id
        $where
        ORDER BY o.staff_type ASC, sa.check_in_time DESC
    ")->fetchAll();
}
