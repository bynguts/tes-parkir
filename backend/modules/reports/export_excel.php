<?php
/**
 * SmartParking — Professional Excel Export
 * Format: SpreadsheetML (XML) — Multi-Sheet, Production-Grade Report
 */
// Buffer ALL output so accidental whitespace/BOM from includes won't corrupt the XML
ob_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/connection.php';

// ─── Helpers ─────────────────────────────────────────────────────────────────
function xe($s): string { return htmlspecialchars((string)$s, ENT_XML1, 'UTF-8'); }
function idr(float $n): string { return 'Rp '.number_format($n, 0, ',', '.'); }
function pct(float $v, float $t): string { return $t > 0 ? round($v/$t*100,1).'%' : '0%'; }

// Cell helpers
function cS($v, $sid='', $merge=0): string {
    $m = $merge > 0 ? " ss:MergeAcross=\"$merge\"" : '';
    $s = $sid ? " ss:StyleID=\"$sid\"" : '';
    return "<Cell$s$m><Data ss:Type=\"String\">".xe($v)."</Data></Cell>";
}
function cN($v, $sid=''): string {
    $s = $sid ? " ss:StyleID=\"$sid\"" : '';
    return "<Cell$s><Data ss:Type=\"Number\">".floatval($v)."</Data></Cell>";
}
function cE($sid='', $merge=0): string {
    $m = $merge > 0 ? " ss:MergeAcross=\"$merge\"" : '';
    $s = $sid ? " ss:StyleID=\"$sid\"" : '';
    return "<Cell$s$m/>";
}
function rH($h, ...$cells): string { return "<Row ss:Height=\"$h\">".implode('', $cells)."</Row>\n"; }
function rD(...$cells): string { return "<Row ss:Height=\"22\">".implode('', $cells)."</Row>\n"; }
function rSp($h=8): string { return "<Row ss:Height=\"$h\"/>\n"; }

// ─── Queries ──────────────────────────────────────────────────────────────────
$totals = $pdo->query("
    SELECT COUNT(*) AS grand_total,
           SUM(v.vehicle_type='car') AS total_cars,
           SUM(v.vehicle_type='motorcycle') AS total_motorcycles,
           SUM(t.total_fee) AS grand_revenue,
           SUM(CASE WHEN v.vehicle_type='car' THEN t.total_fee ELSE 0 END) AS rev_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS rev_motorcycle,
           AVG(t.duration_hours) AS avg_duration, AVG(t.total_fee) AS avg_fee,
           MAX(t.total_fee) AS max_fee,
           SUM(CASE WHEN t.payment_method='cash' THEN 1 ELSE 0 END) AS pay_cash,
           SUM(CASE WHEN t.payment_method='card' THEN 1 ELSE 0 END) AS pay_card,
           SUM(CASE WHEN t.payment_method='e-wallet' THEN 1 ELSE 0 END) AS pay_ewallet
    FROM `transaction` t JOIN vehicle v ON t.vehicle_id=v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
")->fetch();

$daily = $pdo->query("
    SELECT CAST(t.check_out_time AS DATE) AS trx_date,
           DAYNAME(t.check_out_time) AS day_name,
           SUM(v.vehicle_type='car') AS cars, SUM(v.vehicle_type='motorcycle') AS motorcycles,
           COUNT(*) AS total_vehicles,
           SUM(CASE WHEN v.vehicle_type='car' THEN t.total_fee ELSE 0 END) AS rev_car,
           SUM(CASE WHEN v.vehicle_type='motorcycle' THEN t.total_fee ELSE 0 END) AS rev_moto,
           SUM(t.total_fee) AS total_revenue, AVG(t.total_fee) AS avg_fee, MAX(t.total_fee) AS max_fee
    FROM `transaction` t JOIN vehicle v ON t.vehicle_id=v.vehicle_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
    GROUP BY CAST(t.check_out_time AS DATE), DAYNAME(t.check_out_time)
    ORDER BY trx_date DESC
")->fetchAll();

$transactions = $pdo->query("
    SELECT t.transaction_id, t.ticket_code, v.plate_number, v.vehicle_type, v.owner_name, v.owner_phone,
           ps.slot_number, f.floor_name, o.full_name AS operator_name, o.shift,
           t.check_in_time, t.check_out_time, ROUND(t.duration_hours,2) AS duration_hours,
           t.payment_method, t.total_fee
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id=v.vehicle_id JOIN parking_slot ps ON t.slot_id=ps.slot_id
    JOIN floor f ON ps.floor_id=f.floor_id JOIN operator o ON t.operator_id=o.operator_id
    WHERE t.payment_status='paid' AND t.check_out_time IS NOT NULL
    ORDER BY t.check_out_time DESC LIMIT 3000
")->fetchAll();

$active = $pdo->query("
    SELECT t.ticket_code, v.plate_number, v.vehicle_type, v.owner_name,
           ps.slot_number, f.floor_name, o.full_name AS operator_name, t.check_in_time,
           TIMESTAMPDIFF(MINUTE, t.check_in_time, NOW()) AS minutes_elapsed
    FROM `transaction` t
    JOIN vehicle v ON t.vehicle_id=v.vehicle_id JOIN parking_slot ps ON t.slot_id=ps.slot_id
    JOIN floor f ON ps.floor_id=f.floor_id JOIN operator o ON t.operator_id=o.operator_id
    WHERE t.payment_status='unpaid' AND t.check_out_time IS NULL ORDER BY t.check_in_time
")->fetchAll();

$slots = $pdo->query("
    SELECT f.floor_code, f.floor_name, ps.slot_type,
           COUNT(*) AS total, SUM(ps.status='available') AS avail,
           SUM(ps.status='occupied') AS occ, SUM(ps.status='reserved') AS res,
           SUM(ps.status='maintenance') AS maint
    FROM parking_slot ps JOIN floor f ON ps.floor_id=f.floor_id
    GROUP BY f.floor_id,f.floor_code,f.floor_name,ps.slot_type ORDER BY f.floor_id,ps.slot_type
")->fetchAll();

$ops = $pdo->query("
    SELECT o.full_name, o.shift, o.phone,
           COUNT(t.transaction_id) AS total_trx,
           COALESCE(SUM(t.total_fee),0) AS rev,
           COALESCE(AVG(t.total_fee),0) AS avg_fee,
           COALESCE(SUM(v.vehicle_type='car'),0) AS cars,
           COALESCE(SUM(v.vehicle_type='motorcycle'),0) AS motos,
           MAX(t.check_in_time) AS last_act
    FROM operator o
    LEFT JOIN `transaction` t ON o.operator_id=t.operator_id AND t.payment_status='paid' AND t.check_out_time IS NOT NULL
    LEFT JOIN vehicle v ON t.vehicle_id=v.vehicle_id
    GROUP BY o.operator_id,o.full_name,o.shift,o.phone ORDER BY rev DESC
")->fetchAll();

$hours = $pdo->query("
    SELECT HOUR(check_in_time) AS hr, COUNT(*) AS total,
           SUM(v.vehicle_type='car') AS cars, SUM(v.vehicle_type='motorcycle') AS motos
    FROM `transaction` t JOIN vehicle v ON t.vehicle_id=v.vehicle_id
    GROUP BY HOUR(check_in_time) ORDER BY hr
")->fetchAll();

$reservations = $pdo->query("
    SELECT r.reservation_code, v.plate_number, v.vehicle_type, v.owner_name,
           ps.slot_number, f.floor_name, r.reserved_from, r.reserved_until, r.status, r.created_at
    FROM reservation r
    JOIN vehicle v ON r.vehicle_id=v.vehicle_id JOIN parking_slot ps ON r.slot_id=ps.slot_id
    JOIN floor f ON ps.floor_id=f.floor_id ORDER BY r.created_at DESC LIMIT 500
")->fetchAll();

$rates = $pdo->query("SELECT * FROM parking_rate ORDER BY vehicle_type")->fetchAll();

// ─── KPIs ─────────────────────────────────────────────────────────────────────
$totalSlots   = (int)$pdo->query("SELECT COUNT(*) FROM parking_slot")->fetchColumn();
$occupiedNow  = count($active);
$occupancyPct = $totalSlots > 0 ? round($occupiedNow/$totalSlots*100,1) : 0;
$totalDays    = count($daily);
$avgDaily     = $totalDays > 0 ? (float)($totals['grand_revenue']??0)/$totalDays : 0;
$exportDate   = date('l, d F Y  ·  H:i').' WIB';
$bestDay = null; $bestRev = 0;
foreach ($daily as $d) { if ((float)$d['total_revenue']>$bestRev) { $bestRev=(float)$d['total_revenue']; $bestDay=$d; } }
$grandEntries = array_sum(array_column($hours,'total'));

// ─── HTTP Headers ─────────────────────────────────────────────────────────────
ob_end_clean(); // Discard any accidental output from includes before sending XML
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="SmartParking_Report_'.date('Ymd_Hi').'.xls"');
header('Cache-Control: max-age=0');

echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<?mso-application progid="Excel.Sheet"?>'."\n";
// ─── Workbook ─────────────────────────────────────────────────────────────────
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";

echo '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
 <Title>SmartParking Performance Report</Title>
 <Author>SmartParking System</Author>
</DocumentProperties>'."\n";

// ═══════════════════════════════════════════════════
//  STYLES
// ═══════════════════════════════════════════════════
echo '<Styles>'."\n";
// Default style is required by strict Excel parsers
echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/><Interior ss:Pattern="None"/><NumberFormat/><Protection/></Style>'."\n";

$B   = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders>';
$B2  = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#F59E0B"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#475569"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#475569"/></Borders>';
$B2b = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#93C5FD"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/></Borders>';
$B2g = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#86EFAC"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#15803D"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#15803D"/></Borders>';
$B2a = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#FCD34D"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B45309"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B45309"/></Borders>';
$B2t = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#5EEAD4"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0F766E"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0F766E"/></Borders>';
$B2p = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#C4B5FD"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#5B21B6"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#5B21B6"/></Borders>';

$styles = [
  // brand
  'sBrand'  => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="18" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="3" ss:Color="#F59E0B"/></Borders>',
  'sBrandS' => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#94A3B8"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/>',
  // section headers
  'sSecD'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/>',
  'sSecB'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1D4ED8" ss:Pattern="Solid"/>',
  'sSecG'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#15803D" ss:Pattern="Solid"/>',
  'sSecA'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#B45309" ss:Pattern="Solid"/>',
  'sSecT'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F766E" ss:Pattern="Solid"/>',
  'sSecP'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#6D28D9" ss:Pattern="Solid"/>',
  // col headers
  'sHdr'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\" ss:WrapText=\"1\"/><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#F1F5F9\"/><Interior ss:Color=\"#334155\" ss:Pattern=\"Solid\"/>$B2",
  'sHdrB'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\" ss:WrapText=\"1\"/><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#DBEAFE\"/><Interior ss:Color=\"#1E40AF\" ss:Pattern=\"Solid\"/>$B2b",
  'sHdrG'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\" ss:WrapText=\"1\"/><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#DCFCE7\"/><Interior ss:Color=\"#14532D\" ss:Pattern=\"Solid\"/>$B2g",
  'sHdrA'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\" ss:WrapText=\"1\"/><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#FEF3C7\"/><Interior ss:Color=\"#92400E\" ss:Pattern=\"Solid\"/>$B2a",
  'sHdrT'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\" ss:WrapText=\"1\"/><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#CCFBF1\"/><Interior ss:Color=\"#0F4D47\" ss:Pattern=\"Solid\"/>$B2t",
  'sHdrP'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\" ss:WrapText=\"1\"/><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#EDE9FE\"/><Interior ss:Color=\"#4C1D95\" ss:Pattern=\"Solid\"/>$B2p",
  // data left
  'sD'      => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sDa'     => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  'sDb'     => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sDba'    => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  // data center
  'sDc'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sDca'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  'sDcb'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sDcba'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  // money/right
  'sM'      => "<Alignment ss:H=\"Right\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#15803D\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sMa'     => "<Alignment ss:H=\"Right\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#15803D\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  'sMg'     => "<Alignment ss:H=\"Right\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Bold=\"1\" ss:Color=\"#B45309\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sMga'    => "<Alignment ss:H=\"Right\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Bold=\"1\" ss:Color=\"#B45309\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  // total row
  'sTot'    => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/>',
  'sTotC'   => '<Alignment ss:H="Center" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/>',
  'sTotM'   => '<Alignment ss:H="Right" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#F59E0B"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/>',
  // subtotal
  'sSub'    => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#E2E8F0\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"2\" ss:Color=\"#94A3B8\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"2\" ss:Color=\"#94A3B8\"/></Borders>",
  'sSubC'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#E2E8F0\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"2\" ss:Color=\"#94A3B8\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"2\" ss:Color=\"#94A3B8\"/></Borders>",
  'sSubM'   => "<Alignment ss:H=\"Right\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Bold=\"1\" ss:Color=\"#B45309\"/><Interior ss:Color=\"#E2E8F0\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"2\" ss:Color=\"#94A3B8\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"2\" ss:Color=\"#94A3B8\"/></Borders>",
  // KPI cards
  'sKL'     => "<Alignment ss:H=\"Left\" ss:V=\"Bottom\"/><Font ss:FontName=\"Calibri\" ss:Size=\"8\" ss:Bold=\"1\" ss:Color=\"#64748B\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#334155\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKV'     => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"15\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#334155\"/><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKLg'    => "<Alignment ss:H=\"Left\" ss:V=\"Bottom\"/><Font ss:FontName=\"Calibri\" ss:Size=\"8\" ss:Bold=\"1\" ss:Color=\"#15803D\"/><Interior ss:Color=\"#F0FDF4\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#16A34A\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKVg'    => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"15\" ss:Bold=\"1\" ss:Color=\"#15803D\"/><Interior ss:Color=\"#F0FDF4\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#16A34A\"/><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKLa'    => "<Alignment ss:H=\"Left\" ss:V=\"Bottom\"/><Font ss:FontName=\"Calibri\" ss:Size=\"8\" ss:Bold=\"1\" ss:Color=\"#B45309\"/><Interior ss:Color=\"#FFFBEB\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#F59E0B\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKVa'    => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"15\" ss:Bold=\"1\" ss:Color=\"#B45309\"/><Interior ss:Color=\"#FFFBEB\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#F59E0B\"/><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKLb'    => "<Alignment ss:H=\"Left\" ss:V=\"Bottom\"/><Font ss:FontName=\"Calibri\" ss:Size=\"8\" ss:Bold=\"1\" ss:Color=\"#1D4ED8\"/><Interior ss:Color=\"#EFF6FF\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#1D4ED8\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  'sKVb'    => "<Alignment ss:H=\"Left\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"15\" ss:Bold=\"1\" ss:Color=\"#1D4ED8\"/><Interior ss:Color=\"#EFF6FF\" ss:Pattern=\"Solid\"/><Borders><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"3\" ss:Color=\"#1D4ED8\"/><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#E2E8F0\"/></Borders>",
  // badges
  'sBcar'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#1E40AF\"/><Interior ss:Color=\"#DBEAFE\" ss:Pattern=\"Solid\"/>$B",
  'sBcara'  => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#1E40AF\"/><Interior ss:Color=\"#EFF6FF\" ss:Pattern=\"Solid\"/>$B",
  'sBmot'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#15803D\"/><Interior ss:Color=\"#DCFCE7\" ss:Pattern=\"Solid\"/>$B",
  'sBmota'  => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#15803D\"/><Interior ss:Color=\"#F0FDF4\" ss:Pattern=\"Solid\"/>$B",
  'sBav'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#065F46\"/><Interior ss:Color=\"#D1FAE5\" ss:Pattern=\"Solid\"/>$B",
  'sBoc'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#991B1B\"/><Interior ss:Color=\"#FEE2E2\" ss:Pattern=\"Solid\"/>$B",
  'sBres'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#5B21B6\"/><Interior ss:Color=\"#EDE9FE\" ss:Pattern=\"Solid\"/>$B",
  'sBmnt'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#92400E\"/><Interior ss:Color=\"#FEF3C7\" ss:Pattern=\"Solid\"/>$B",
  'sBmor'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#92400E\"/><Interior ss:Color=\"#FEF9C3\" ss:Pattern=\"Solid\"/>$B",
  'sBaft'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#1E40AF\"/><Interior ss:Color=\"#DBEAFE\" ss:Pattern=\"Solid\"/>$B",
  'sBnit'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#C7D2FE\"/><Interior ss:Color=\"#1E1B4B\" ss:Pattern=\"Solid\"/>$B",
  'sBcsh'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#065F46\"/><Interior ss:Color=\"#ECFDF5\" ss:Pattern=\"Solid\"/>$B",
  'sBcrd'   => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#1E40AF\"/><Interior ss:Color=\"#EFF6FF\" ss:Pattern=\"Solid\"/>$B",
  'sBew'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#5B21B6\"/><Interior ss:Color=\"#F5F3FF\" ss:Pattern=\"Solid\"/>$B",
  'sBconf'  => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#065F46\"/><Interior ss:Color=\"#D1FAE5\" ss:Pattern=\"Solid\"/>$B",
  'sBcncl'  => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#991B1B\"/><Interior ss:Color=\"#FEE2E2\" ss:Pattern=\"Solid\"/>$B",
  'sBpend'  => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#92400E\"/><Interior ss:Color=\"#FEF3C7\" ss:Pattern=\"Solid\"/>$B",
  // levels
  'sLvh'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#991B1B\"/><Interior ss:Color=\"#FEE2E2\" ss:Pattern=\"Solid\"/>$B",
  'sLh'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#9A3412\"/><Interior ss:Color=\"#FED7AA\" ss:Pattern=\"Solid\"/>$B",
  'sLm'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#78350F\"/><Interior ss:Color=\"#FEF9C3\" ss:Pattern=\"Solid\"/>$B",
  'sLl'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#065F46\"/><Interior ss:Color=\"#D1FAE5\" ss:Pattern=\"Solid\"/>$B",
  // rank medals
  'sRg'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"14\" ss:Bold=\"1\" ss:Color=\"#B45309\"/><Interior ss:Color=\"#FEF3C7\" ss:Pattern=\"Solid\"/>$B",
  'sRs'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"14\" ss:Bold=\"1\" ss:Color=\"#475569\"/><Interior ss:Color=\"#F1F5F9\" ss:Pattern=\"Solid\"/>$B",
  'sRb'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"14\" ss:Bold=\"1\" ss:Color=\"#7C2D12\"/><Interior ss:Color=\"#FFF7ED\" ss:Pattern=\"Solid\"/>$B",
  'sRn'     => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Color=\"#475569\"/><Interior ss:Color=\"#FFFFFF\" ss:Pattern=\"Solid\"/>$B",
  'sRna'    => "<Alignment ss:H=\"Center\" ss:V=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Color=\"#475569\"/><Interior ss:Color=\"#F8FAFC\" ss:Pattern=\"Solid\"/>$B",
  // misc
  'sNote'   => '<Alignment ss:H="Left" ss:V="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="9" ss:Italic="1" ss:Color="#713F12"/><Interior ss:Color="#FEF9C3" ss:Pattern="Solid"/>',
  'sMeta'   => '<Alignment ss:H="Left" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#94A3B8"/><Interior ss:Color="#1E293B" ss:Pattern="Solid"/>',
  'sFooter' => '<Alignment ss:H="Center" ss:V="Center"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#64748B"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>',
];

// Output all styles, replacing shorthand Horizontal/Vertical with ss: namespace
foreach ($styles as $id => $body) {
    $body = str_replace(['ss:H=','ss:V='], ['ss:Horizontal=','ss:Vertical='], $body);
    echo "<Style ss:ID=\"$id\">$body</Style>\n";
}
echo '</Styles>'."\n";

// ═══════════════════════════════════════════════════════════════════════════════
//  FREEZE PANES HELPER
// ═══════════════════════════════════════════════════════════════════════════════
// Store freeze rows per sheet so WorksheetOptions is emitted AFTER </Table> (correct order)
$_wsFreezeRows = 0;
function wsOpen(string $name, int $freezeRows = 0): void {
    global $_wsFreezeRows;
    $_wsFreezeRows = $freezeRows;
    echo "<Worksheet ss:Name=\"".xe($name)."\">\n";
}
function wsClose(): void {
    global $_wsFreezeRows;
    // WorksheetOptions MUST come after </Table> per SpreadsheetML spec
    if ($_wsFreezeRows > 0) {
        echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'.
             '<FreezePanes/><SplitHorizontal>'.$_wsFreezeRows.'</SplitHorizontal>'.
             '<TopRowBottomPane>'.$_wsFreezeRows.'</TopRowBottomPane><ActivePane>2</ActivePane>'.
             "</WorksheetOptions>\n";
    }
    echo "</Worksheet>\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 1: EXECUTIVE SUMMARY                                    (12 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Executive Summary');
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="180"/>'; // A
echo '<Column ss:Width="160"/>'; // B
echo '<Column ss:Width="14"/>'; // C spacer
echo '<Column ss:Width="160"/>'; // D
echo '<Column ss:Width="160"/>'; // E
echo '<Column ss:Width="14"/>'; // F spacer
echo '<Column ss:Width="160"/>'; // G
echo '<Column ss:Width="160"/>'; // H
echo '<Column ss:Width="14"/>'; // I spacer
echo '<Column ss:Width="160"/>'; // J
echo '<Column ss:Width="160"/>'; // K
echo "\n";

// Title block
echo rH(52, cS('SmartParking — Laporan Kinerja Operasional', 'sBrand', 10));
echo rH(24, cS('Diekspor secara otomatis oleh sistem  ·  '.$exportDate, 'sBrandS', 10));
echo rSp(14);

// KPI Block 1 — Row labels
echo rH(16,
    cS('TOTAL TRANSAKSI SELESAI', 'sKL'), cE('sKL'), cE(),
    cS('TOTAL KUNJUNGAN KUMULATIF', 'sKLg'), cE('sKLg'), cE(),
    cS('GROSS REVENUE KESELURUHAN', 'sKLa'), cE('sKLa'), cE(),
    cS('RATA-RATA REVENUE PER HARI', 'sKLb'), cE('sKLb')
);
// KPI Block 1 — Row values
echo rH(38,
    cS(number_format($totals['grand_total']??0).' Transaksi', 'sKV'), cE('sKV'), cE(),
    cS(number_format(($totals['total_cars']??0)+($totals['total_motorcycles']??0)).' Unit', 'sKVg'), cE('sKVg'), cE(),
    cS(idr((float)($totals['grand_revenue']??0)), 'sKVa'), cE('sKVa'), cE(),
    cS(idr($avgDaily), 'sKVb'), cE('sKVb')
);
echo rSp(14);
// KPI Block 2 — Row labels
echo rH(16,
    cS('MOBIL DILAYANI', 'sKLb'), cE('sKLb'), cE(),
    cS('MOTOR DILAYANI', 'sKLg'), cE('sKLg'), cE(),
    cS('REVENUE DARI MOBIL', 'sKLa'), cE('sKLa'), cE(),
    cS('REVENUE DARI MOTOR', 'sKL'), cE('sKL')
);
// KPI Block 2 — Row values
$carPct = pct((float)($totals['total_cars']??0),(float)max($totals['grand_total']??1,1));
$motPct = pct((float)($totals['total_motorcycles']??0),(float)max($totals['grand_total']??1,1));
echo rH(38,
    cS(number_format($totals['total_cars']??0).' Unit ('.$carPct.')', 'sKVb'), cE('sKVb'), cE(),
    cS(number_format($totals['total_motorcycles']??0).' Unit ('.$motPct.')', 'sKVg'), cE('sKVg'), cE(),
    cS(idr((float)($totals['rev_car']??0)), 'sKVa'), cE('sKVa'), cE(),
    cS(idr((float)($totals['rev_motorcycle']??0)), 'sKV'), cE('sKV')
);
echo rSp(14);
// KPI Block 3
echo rH(16,
    cS('RATA-RATA BIAYA/TRANSAKSI', 'sKL'), cE('sKL'), cE(),
    cS('TRANSAKSI TERBESAR', 'sKLa'), cE('sKLa'), cE(),
    cS('RATA-RATA DURASI PARKIR', 'sKLb'), cE('sKLb'), cE(),
    cS('TINGKAT OKUPANSI SEKARANG', 'sKLg'), cE('sKLg')
);
echo rH(38,
    cS(idr((float)($totals['avg_fee']??0)), 'sKV'), cE('sKV'), cE(),
    cS(idr((float)($totals['max_fee']??0)), 'sKVa'), cE('sKVa'), cE(),
    cS(round((float)($totals['avg_duration']??0),1).' Jam', 'sKVb'), cE('sKVb'), cE(),
    cS($occupancyPct.'%  ('.$occupiedNow.'/'.$totalSlots.' Slot)', 'sKVg'), cE('sKVg')
);
echo rSp(16);

// Best Day
echo rH(30, cS('⭐  HARI TERBAIK — REVENUE TERTINGGI', 'sSecA', 10));
echo rH(32,
    cS('Tanggal', 'sHdrA'), cS('Hari', 'sHdrA'), cS('Kunjungan', 'sHdrA'),
    cS('Mobil', 'sHdrA'), cS('Motor', 'sHdrA'), cS('Rev. Mobil', 'sHdrA'),
    cS('Rev. Motor', 'sHdrA'), cS('Total Revenue', 'sHdrA'),
    cE('sHdrA'), cE('sHdrA'), cE('sHdrA')
);
if ($bestDay) {
    echo rD(
        cS(date('d F Y', strtotime($bestDay['trx_date'])), 'sDb'),
        cS($bestDay['day_name'], 'sD'),
        cS(number_format($bestDay['total_vehicles']), 'sDcb'),
        cS(number_format($bestDay['cars']), 'sBcar'),
        cS(number_format($bestDay['motorcycles']), 'sBmot'),
        cS(idr((float)$bestDay['rev_car']), 'sM'),
        cS(idr((float)$bestDay['rev_moto']), 'sM'),
        cS(idr((float)$bestDay['total_revenue']), 'sMg'),
        cE('sD'), cE('sD'), cE('sD')
    );
}
echo rSp(16);

// Payment distribution
echo rH(30, cS('💳  DISTRIBUSI METODE PEMBAYARAN', 'sSecD', 10));
echo rH(32, cS('Metode', 'sHdr'), cS('Jumlah Transaksi', 'sHdr'), cS('Persentase', 'sHdr'), cE('sHdr',7));
$total = (float)max($totals['grand_total']??1,1);
foreach (['cash' => ['Cash / Tunai','sBcsh'], 'card' => ['Kartu Debit/Kredit','sBcrd'], 'e-wallet' => ['E-Wallet Digital','sBew']] as $mkey => $mval) {
    $cnt = (float)($totals['pay_'.$mkey] ?? ($mkey==='e-wallet' ? $totals['pay_ewallet']??0 : 0));
    echo rD(cS($mval[0], $mval[1]), cS(number_format($cnt), 'sDcb'), cS(pct($cnt,$total), 'sDcb'), cE('sD',7));
}
echo rSp(16);

// Current tariffs
echo rH(30, cS('🏷️  TARIF PARKIR YANG BERLAKU', 'sSecT', 10));
echo rH(32, cS('Jenis Kendaraan','sHdrT'), cS('Jam Pertama','sHdrT'), cS('Jam Berikutnya','sHdrT'), cS('Tarif Maks. Harian','sHdrT'), cE('sHdrT',6));
foreach ($rates as $i => $r) {
    $lbl = $r['vehicle_type']==='car' ? 'Mobil' : 'Sepeda Motor';
    $bs  = $r['vehicle_type']==='car' ? ($i%2?'sBcara':'sBcar') : ($i%2?'sBmota':'sBmot');
    echo rD(cS($lbl,$bs), cS(idr((float)$r['first_hour_rate']),'sM'), cS(idr((float)$r['next_hour_rate']),'sM'), cS(idr((float)$r['daily_max_rate']),'sMg'), cE('sD',6));
}
echo rSp(10);
echo rH(20, cS('Dokumen ini bersifat rahasia dan dibuat secara otomatis pada '.$exportDate.'. Hanya untuk keperluan internal SmartParking.','sFooter',10));
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 2: REVENUE HARIAN                                       (11 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Revenue Harian', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="30"/><Column ss:Width="150"/><Column ss:Width="90"/>';
echo '<Column ss:Width="70"/><Column ss:Width="70"/><Column ss:Width="80"/>';
echo '<Column ss:Width="130"/><Column ss:Width="130"/><Column ss:Width="140"/>';
echo '<Column ss:Width="130"/><Column ss:Width="130"/>'."\n";

echo rH(48, cS('SmartParking   |   Laporan Revenue Harian', 'sBrand', 10));
echo rH(22, cS('Sumber: Tabel transaction (status paid) · Dihasilkan: '.$exportDate, 'sBrandS', 10));
echo rH(34, cS('#','sHdrG'), cS('Tanggal','sHdrG'), cS('Hari','sHdrG'),
    cS('Mobil','sHdrG'), cS('Motor','sHdrG'), cS('Total Kunjungan','sHdrG'),
    cS('Revenue Mobil','sHdrG'), cS('Revenue Motor','sHdrG'), cS('Revenue Harian','sHdrG'),
    cS('Avg Biaya/Trx','sHdrG'), cS('Max Biaya','sHdrG'));

foreach ($daily as $i => $row) {
    [$d, $da]  = $i%2 ? ['sDa','sDba'] : ['sD','sDb'];
    [$m, $mg]  = $i%2 ? ['sMa','sMga'] : ['sM','sMg'];
    [$bc,$bm]  = $i%2 ? ['sBcara','sBmota'] : ['sBcar','sBmot'];
    echo rD(
        cS($i+1,'sDca'), cS(date('d F Y',strtotime($row['trx_date'])),$da),
        cS($row['day_name'],$d), cS(number_format($row['cars']),$bc),
        cS(number_format($row['motorcycles']),$bm), cS(number_format($row['total_vehicles']),'sDcb'),
        cS(idr((float)$row['rev_car']),$m), cS(idr((float)$row['rev_moto']),$m),
        cS(idr((float)$row['total_revenue']),$mg),
        cS(idr((float)$row['avg_fee']),$m), cS(idr((float)$row['max_fee']),$m)
    );
}
// Grand total footer
echo rH(28,
    cS('','sTot'), cS('TOTAL KUMULATIF','sTot'), cS('','sTot'),
    cS(number_format($totals['total_cars']??0),'sTotC'), cS(number_format($totals['total_motorcycles']??0),'sTotC'),
    cS(number_format($totals['grand_total']??0),'sTotC'),
    cS(idr((float)($totals['rev_car']??0)),'sTotM'), cS(idr((float)($totals['rev_motorcycle']??0)),'sTotM'),
    cS(idr((float)($totals['grand_revenue']??0)),'sTotM'),
    cS(idr((float)($totals['avg_fee']??0)),'sTotM'), cS('—','sTot')
);
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 3: TRANSAKSI LENGKAP                                    (15 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Transaksi Lengkap', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="28"/><Column ss:Width="50"/><Column ss:Width="100"/>';
echo '<Column ss:Width="95"/><Column ss:Width="75"/><Column ss:Width="120"/>';
echo '<Column ss:Width="110"/><Column ss:Width="75"/><Column ss:Width="100"/>';
echo '<Column ss:Width="85"/><Column ss:Width="120"/><Column ss:Width="120"/>';
echo '<Column ss:Width="65"/><Column ss:Width="80"/><Column ss:Width="120"/>'."\n";

echo rH(48, cS('SmartParking   |   Log Transaksi Lengkap  (maks. 3.000 record terbaru)', 'sBrand', 14));
echo rH(22, cS('Hanya transaksi berstatus paid. Dihasilkan: '.$exportDate, 'sBrandS', 14));
echo rH(34,
    cS('#','sHdrB'), cS('ID Trx','sHdrB'), cS('Kode Tiket','sHdrB'),
    cS('Plat Nomor','sHdrB'), cS('Jenis','sHdrB'), cS('Nama Pemilik','sHdrB'),
    cS('Telepon','sHdrB'), cS('Slot','sHdrB'), cS('Lantai','sHdrB'),
    cS('Operator','sHdrB'), cS('Check-in','sHdrB'), cS('Check-out','sHdrB'),
    cS('Durasi (Jam)','sHdrB'), cS('Pembayaran','sHdrB'), cS('Total Biaya','sHdrB')
);
foreach ($transactions as $i => $t) {
    [$d,$dc] = $i%2 ? ['sDa','sDca'] : ['sD','sDc'];
    $m = $i%2 ? 'sMa' : 'sM';
    $bc = $t['vehicle_type']==='car' ? ($i%2?'sBcara':'sBcar') : ($i%2?'sBmota':'sBmot');
    $pm = match($t['payment_method']) { 'cash'=>'sBcsh','card'=>'sBcrd',default=>'sBew' };
    $cin  = $t['check_in_time']  ? date('d/m/Y H:i',strtotime($t['check_in_time'])) : '-';
    $cout = $t['check_out_time'] ? date('d/m/Y H:i',strtotime($t['check_out_time'])) : '-';
    echo rD(
        cS($i+1,$dc), cS($t['transaction_id'],$dc), cS($t['ticket_code']??'-',$d),
        cS($t['plate_number']??'-',$d), cS($t['vehicle_type']==='car'?'Mobil':'Motor',$bc),
        cS($t['owner_name'],$d), cS($t['owner_phone']??'-',$d),
        cS($t['slot_number'],$dc), cS($t['floor_name'],$d),
        cS($t['operator_name'],$d), cS($cin,$d), cS($cout,$d),
        cS($t['duration_hours']??'0',$dc), cS(strtoupper($t['payment_method']),$pm),
        cS(idr((float)$t['total_fee']),$m)
    );
}
$sumTrx = array_sum(array_column($transactions,'total_fee'));
echo rH(28, cS('','sTot',13), cS('','sTot'), cS(idr((float)$sumTrx),'sTotM'));
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 4: KENDARAAN AKTIF SEKARANG                             (9 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Kendaraan Aktif (Real-Time)', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="28"/><Column ss:Width="100"/><Column ss:Width="100"/>';
echo '<Column ss:Width="75"/><Column ss:Width="140"/><Column ss:Width="80"/>';
echo '<Column ss:Width="100"/><Column ss:Width="130"/><Column ss:Width="130"/>'."\n";

echo rH(48, cS('SmartParking   |   Kendaraan Sedang Parkir — Real-Time', 'sBrand', 8));
echo rH(22, cS('Snapshot: '.$exportDate.'  ·  '.count($active).' kendaraan aktif  ·  '.($totalSlots-$occupiedNow).' slot tersedia dari '.$totalSlots, 'sBrandS', 8));
echo rH(34,
    cS('#','sHdrA'), cS('Kode Tiket','sHdrA'), cS('Plat Nomor','sHdrA'),
    cS('Jenis','sHdrA'), cS('Nama Pemilik','sHdrA'), cS('Slot','sHdrA'),
    cS('Lantai','sHdrA'), cS('Waktu Masuk','sHdrA'), cS('Durasi (Menit)','sHdrA')
);
if (empty($active)) {
    echo rH(30, cS('Tidak ada kendaraan yang sedang parkir saat laporan ini dicetak.', 'sNote', 8));
} else {
    foreach ($active as $i => $v) {
        [$d,$dc] = $i%2 ? ['sDa','sDca'] : ['sD','sDc'];
        $bc = $v['vehicle_type']==='car' ? ($i%2?'sBcara':'sBcar') : ($i%2?'sBmota':'sBmot');
        $durStyle = (int)$v['minutes_elapsed'] > 180 ? 'sBoc' : $dc;
        echo rD(
            cS($i+1,$dc), cS($v['ticket_code']??'-',$d), cS($v['plate_number']??'-',$d),
            cS($v['vehicle_type']==='car'?'Mobil':'Motor',$bc),
            cS($v['owner_name'],$d), cS($v['slot_number'],$dc),
            cS($v['floor_name'],$d), cS(date('d/m/Y H:i',strtotime($v['check_in_time'])),$d),
            cS($v['minutes_elapsed'].' mnt',$durStyle)
        );
    }
}
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 5: SLOT PARKIR PER LANTAI                               (9 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Status Slot Parkir', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="28"/><Column ss:Width="80"/><Column ss:Width="120"/>';
echo '<Column ss:Width="85"/><Column ss:Width="70"/><Column ss:Width="85"/>';
echo '<Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="100"/>'."\n";

echo rH(48, cS('SmartParking   |   Status Slot Parkir per Lantai', 'sBrand', 8));
echo rH(22, cS('Snapshot real-time · '.$exportDate, 'sBrandS', 8));
echo rH(34,
    cS('#','sHdr'), cS('Kode Lantai','sHdr'), cS('Nama Lantai','sHdr'),
    cS('Jenis Slot','sHdr'), cS('Total','sHdr'), cS('Tersedia','sHdr'),
    cS('Terisi','sHdr'), cS('Direservasi','sHdr'), cS('Maintenance','sHdr')
);
$stotals = ['total'=>0,'avail'=>0,'occ'=>0,'res'=>0,'maint'=>0];
foreach ($slots as $i => $row) {
    [$d,$dc] = $i%2 ? ['sDa','sDca'] : ['sD','sDc'];
    $bc = $row['slot_type']==='car' ? ($i%2?'sBcara':'sBcar') : ($i%2?'sBmota':'sBmot');
    echo rD(
        cS($i+1,$dc), cS($row['floor_code'],$dc), cS($row['floor_name'],$d),
        cS($row['slot_type']==='car'?'Mobil':'Sepeda Motor',$bc),
        cS(number_format($row['total']),$dc),
        cS(number_format($row['avail']),'sBav'), cS(number_format($row['occ']),'sBoc'),
        cS(number_format($row['res']),'sBres'), cS(number_format($row['maint']),'sBmnt')
    );
    foreach (['total','avail','occ','res','maint'] as $k) $stotals[$k] += (int)$row[$k];
}
echo rH(28,
    cS('','sSub',3), cS('TOTAL','sSub'), cS(number_format($stotals['total']),'sSubC'),
    cS(number_format($stotals['avail']),'sBav'), cS(number_format($stotals['occ']),'sBoc'),
    cS(number_format($stotals['res']),'sBres'), cS(number_format($stotals['maint']),'sBmnt')
);
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 6: KINERJA OPERATOR                                     (10 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Kinerja Operator', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="36"/><Column ss:Width="140"/><Column ss:Width="100"/>';
echo '<Column ss:Width="110"/><Column ss:Width="80"/><Column ss:Width="80"/>';
echo '<Column ss:Width="80"/><Column ss:Width="130"/><Column ss:Width="150"/>';
echo '<Column ss:Width="130"/>'."\n";

echo rH(48, cS('SmartParking   |   Kinerja Operator — Ranking Revenue', 'sBrand', 9));
echo rH(22, cS('Berdasarkan total transaksi paid yang dilayani. '.$exportDate, 'sBrandS', 9));
echo rH(34,
    cS('Rank','sHdrT'), cS('Nama Operator','sHdrT'), cS('Shift','sHdrT'),
    cS('Telepon','sHdrT'), cS('Total Trx','sHdrT'), cS('Mobil','sHdrT'),
    cS('Motor','sHdrT'), cS('Avg Biaya/Trx','sHdrT'), cS('Total Revenue Handled','sHdrT'),
    cS('Aktivitas Terakhir','sHdrT')
);
$medals = ['🥇','🥈','🥉'];
$rankStyles = ['sRg','sRs','sRb'];
$opRevTotal = 0;
foreach ($ops as $i => $op) {
    [$d,$dc,$m,$mg] = $i%2 ? ['sDa','sDca','sMa','sMga'] : ['sD','sDc','sM','sMg'];
    $rs = $i < 3 ? $rankStyles[$i] : ($i%2?'sRna':'sRn');
    $rk = $i < 3 ? $medals[$i] : ($i+1);
    $shiftStyle = match($op['shift']) { 'morning'=>'sBmor','afternoon'=>'sBaft',default=>'sBnit' };
    $shiftLabel = match($op['shift']) { 'morning'=>'Morning (06-14)','afternoon'=>'Afternoon (14-22)',default=>'Night (22-06)' };
    $lastAct = $op['last_act'] ? date('d/m/Y H:i',strtotime($op['last_act'])) : 'Belum Ada';
    $opRevTotal += (float)$op['rev'];
    echo rD(
        cS($rk,$rs), cS($op['full_name'],$i%2?'sDba':'sDb'),
        cS($shiftLabel,$shiftStyle), cS($op['phone']??'-',$d),
        cS(number_format($op['total_trx']),$dc),
        cS(number_format($op['cars']),'sBcar'), cS(number_format($op['motos']),'sBmot'),
        cS(idr((float)$op['avg_fee']),$m), cS(idr((float)$op['rev']),$mg),
        cS($lastAct,$d)
    );
}
echo rH(28,
    cS('','sTot',4), cS(number_format(array_sum(array_column($ops,'total_trx'))),'sTotC'),
    cS(number_format(array_sum(array_column($ops,'cars'))),'sTotC'),
    cS(number_format(array_sum(array_column($ops,'motos'))),'sTotC'),
    cS('—','sTot'), cS(idr($opRevTotal),'sTotM'), cS('—','sTot')
);
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 7: ANALISIS JAM SIBUK                                   (7 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Analisis Jam Sibuk', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="110"/><Column ss:Width="120"/><Column ss:Width="100"/>';
echo '<Column ss:Width="90"/><Column ss:Width="90"/><Column ss:Width="70"/>';
echo '<Column ss:Width="130"/>'."\n";

echo rH(48, cS('SmartParking   |   Analisis Jam Sibuk — Peak Hours', 'sBrand', 6));
echo rH(22, cS('Berdasarkan waktu check-in semua kendaraan. Berguna untuk perencanaan shift & kapasitas. '.$exportDate, 'sBrandS', 6));
echo rH(34,
    cS('Jam (WIB)','sHdrP'), cS('Sesi Operasional','sHdrP'), cS('Total Masuk','sHdrP'),
    cS('Masuk Mobil','sHdrP'), cS('Masuk Motor','sHdrP'), cS('% dari Total','sHdrP'),
    cS('Tingkat Aktivitas','sHdrP')
);

$filledHours = [];
foreach ($hours as $h) $filledHours[(int)$h['hr']] = $h;

for ($hr=0; $hr<24; $hr++) {
    $h      = $filledHours[$hr] ?? ['hr'=>$hr,'total'=>0,'cars'=>0,'motos'=>0];
    $tot    = (int)$h['total'];
    $pctH   = $grandEntries > 0 ? round($tot/$grandEntries*100,1) : 0;
    $session = match(true) {
        $hr>=5  && $hr<9  => 'Subuh (05:00-08:59)',
        $hr>=9  && $hr<12 => 'Pagi (09:00-11:59)',
        $hr>=12 && $hr<14 => 'Siang (12:00-13:59)',
        $hr>=14 && $hr<17 => 'Sore (14:00-16:59)',
        $hr>=17 && $hr<20 => 'Petang (17:00-19:59)',
        $hr>=20 && $hr<23 => 'Malam (20:00-22:59)',
        default           => 'Dini Hari (23:00-04:59)',
    };
    [$level,$lstyle] = match(true) {
        $pctH>=10 => ['🔴 VERY HIGH','sLvh'],
        $pctH>=6  => ['🟠 HIGH','sLh'],
        $pctH>=3  => ['🟡 MODERATE','sLm'],
        default   => ['🟢 LOW','sLl'],
    };
    [$d,$dc] = $hr%2 ? ['sDa','sDca'] : ['sD','sDc'];
    $bc = $hr%2 ? 'sBcara' : 'sBcar';
    $bm = $hr%2 ? 'sBmota' : 'sBmot';
    echo rD(
        cS(sprintf('%02d:00 – %02d:59',$hr,$hr),$dc), cS($session,$d),
        cS(number_format($tot),'sDcb'), cS(number_format($h['cars']),$bc),
        cS(number_format($h['motos']),$bm), cS($pctH.'%',$dc),
        cS($level,$lstyle)
    );
}
echo rH(28,
    cS(sprintf('%02d jam operasional',24),'sTot'), cS('TOTAL SEMUA JAM','sTot'),
    cS(number_format($grandEntries),'sTotC'),
    cS(number_format(array_sum(array_column($hours,'cars'))),'sTotC'),
    cS(number_format(array_sum(array_column($hours,'motos'))),'sTotC'),
    cS('100%','sTotC'), cS('—','sTot')
);
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 8: RESERVASI                                            (10 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Reservasi', 3);
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="28"/><Column ss:Width="120"/><Column ss:Width="100"/>';
echo '<Column ss:Width="75"/><Column ss:Width="130"/><Column ss:Width="80"/>';
echo '<Column ss:Width="100"/><Column ss:Width="125"/><Column ss:Width="125"/>';
echo '<Column ss:Width="90"/>'."\n";

echo rH(48, cS('SmartParking   |   Log Reservasi (maks. 500 terbaru)', 'sBrand', 9));
echo rH(22, cS($exportDate, 'sBrandS', 9));
if (empty($reservations)) {
    echo rH(30, cS('Belum ada data reservasi.', 'sNote', 9));
} else {
    echo rH(34,
        cS('#','sHdrP'), cS('Kode Reservasi','sHdrP'), cS('Plat Nomor','sHdrP'),
        cS('Jenis','sHdrP'), cS('Nama Pemilik','sHdrP'), cS('Slot','sHdrP'),
        cS('Lantai','sHdrP'), cS('Dari','sHdrP'), cS('Sampai','sHdrP'),
        cS('Status','sHdrP')
    );
    foreach ($reservations as $i => $r) {
        [$d,$dc] = $i%2 ? ['sDa','sDca'] : ['sD','sDc'];
        $bc = $r['vehicle_type']==='car' ? ($i%2?'sBcara':'sBcar') : ($i%2?'sBmota':'sBmot');
        $ss = match($r['status']) { 'confirmed'=>'sBconf','cancelled','expired'=>'sBcncl',default=>'sBpend' };
        echo rD(
            cS($i+1,$dc), cS($r['reservation_code'],$d), cS($r['plate_number']??'-',$d),
            cS($r['vehicle_type']==='car'?'Mobil':'Motor',$bc),
            cS($r['owner_name'],$d), cS($r['slot_number'],$dc), cS($r['floor_name'],$d),
            cS(date('d/m/Y H:i',strtotime($r['reserved_from'])),$d),
            cS(date('d/m/Y H:i',strtotime($r['reserved_until'])),$d),
            cS(strtoupper($r['status']),$ss)
        );
    }
}
echo '</Table>'."\n";
wsClose();

// ═══════════════════════════════════════════════════════════════════════════════
//  SHEET 9: TARIF PARKIR                                         (6 columns)
// ═══════════════════════════════════════════════════════════════════════════════
wsOpen('Tarif Parkir');
echo '<Table ss:DefaultRowHeight="20">'."\n";
echo '<Column ss:Width="28"/><Column ss:Width="150"/><Column ss:Width="140"/>';
echo '<Column ss:Width="140"/><Column ss:Width="150"/><Column ss:Width="200"/>'."\n";

echo rH(48, cS('SmartParking   |   Struktur Tarif Parkir', 'sBrand', 5));
echo rH(22, cS('Tarif aktif yang berlaku pada sistem. Hubungi Administrator untuk perubahan. '.$exportDate, 'sBrandS', 5));
echo rSp(14);

echo rH(30, cS('Jenis Kendaraan','sSecT'), cS('Tarif Jam Pertama','sSecT'), cS('Tarif Jam Berikutnya','sSecT'), cS('Tarif Maks. Harian','sSecT'), cS('Keterangan','sSecT'));
foreach ($rates as $i => $r) {
    $lbl = $r['vehicle_type']==='car' ? '🚗  Mobil' : '🏍️  Sepeda Motor';
    $bs  = $r['vehicle_type']==='car' ? 'sBcar' : 'sBmot';
    echo rH(26,
        cE(), cS($lbl,$bs), cS(idr((float)$r['first_hour_rate']),'sMg'),
        cS(idr((float)$r['next_hour_rate']),'sM'), cS(idr((float)$r['daily_max_rate']),'sMg'),
        cS('Berlaku semua lantai. Dihitung per jam penuh.','sD')
    );
}
echo rSp(10);
echo rH(28, cS('Catatan','sNote'), cS('Tarif harian maksimum berlaku jika kendaraan parkir lebih dari 24 jam berturut-turut. Biaya = jam pertama + (durasi-1) × jam berikutnya, hingga batas tarif harian.','sNote',4));
echo rSp(14);
echo rH(20, cS('SmartParking System  ·  Laporan ini bersifat rahasia dan hanya untuk keperluan internal  ·  '.$exportDate,'sFooter',5));
echo '</Table>'."\n";
wsClose();

echo '</Workbook>';
