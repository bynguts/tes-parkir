<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

header('Content-Type: application/json');

// CSRF validation
csrf_verify();

$mode = $_POST['mode'] ?? '';
$date = $_POST['date'] ?? '';

if (!in_array($mode, ['by_date', 'all'])) {
    echo json_encode(['success' => false, 'message' => 'Mode tidak valid.']); exit;
}

if ($mode === 'by_date') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']); exit;
    }
    $date_label = $date;
} else {
    $date_label = 'semua';
}

$pdo->beginTransaction();
try {
    // 1. Get paid transaction IDs matching the date (or all)
    if ($mode === 'by_date') {
        $stmt = $pdo->prepare("SELECT transaction_id FROM `transaction` WHERE payment_status='paid' AND DATE(check_out_time)=?");
        $stmt->execute([$date]);
    } else {
        $stmt = $pdo->query("SELECT transaction_id FROM `transaction` WHERE payment_status='paid'");
    }
    $trx_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Delete plate_scan_log
    if ($mode === 'by_date') {
        $del_scan = $pdo->prepare("DELETE FROM plate_scan_log WHERE DATE(scan_time)=?");
        $del_scan->execute([$date]);
    } else {
        $del_scan = $pdo->query("DELETE FROM plate_scan_log");
    }
    $deleted_scans = $del_scan->rowCount();

    $deleted_trx = 0;
    if (!empty($trx_ids)) {
        $placeholders = implode(',', array_fill(0, count($trx_ids), '?'));

        // 3. Delete transaction_log
        $pdo->prepare("DELETE FROM transaction_log WHERE transaction_id IN ($placeholders)")
            ->execute($trx_ids);

        // 4. Delete tickets
        $pdo->prepare("DELETE FROM ticket WHERE transaction_id IN ($placeholders)")
            ->execute($trx_ids);

        // 5. Free slots (edge case: paid but slot still occupied)
        $pdo->prepare("UPDATE parking_slot ps
                       JOIN `transaction` t ON ps.slot_id = t.slot_id
                       SET ps.status = 'available'
                       WHERE t.transaction_id IN ($placeholders)
                         AND t.payment_status = 'paid'
                         AND ps.status = 'occupied'")
            ->execute($trx_ids);

        // 6. Delete transactions
        $stmt2 = $pdo->prepare("DELETE FROM `transaction` WHERE transaction_id IN ($placeholders)");
        $stmt2->execute($trx_ids);
        $deleted_trx = $stmt2->rowCount();
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Berhasil menghapus log: {$date_label}",
        'deleted_scans' => $deleted_scans,
        'deleted_trx'   => $deleted_trx,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("delete_logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
}
