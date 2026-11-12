<?php
/**
 * e-Barangay Web Portal - Live Queue JSON API
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDb();
    
    // 1. Now Serving tickets for today
    $nowServingStmt = $pdo->query("
        SELECT ticket_number, counter_number, priority_level
        FROM queue_tickets
        WHERE queue_date = CURDATE() AND status = 'Now Serving'
        ORDER BY called_at DESC
    ");
    $nowServingList = $nowServingStmt->fetchAll();

    $counter1 = null;
    $counter2 = null;
    $nowServingLatest = null;

    foreach ($nowServingList as $row) {
        if (!$nowServingLatest) {
            $nowServingLatest = $row['ticket_number'];
        }
        if (stripos($row['counter_number'] ?? '', 'Counter 1') !== false || stripos($row['counter_number'] ?? '', 'Desk') !== false) {
            if (!$counter1) $counter1 = $row['ticket_number'];
        } elseif (stripos($row['counter_number'] ?? '', 'Counter 2') !== false || stripos($row['counter_number'] ?? '', 'Treasurer') !== false) {
            if (!$counter2) $counter2 = $row['ticket_number'];
        }
    }

    // 2. Count waiting tickets today
    $waitingStmt = $pdo->query("
        SELECT COUNT(*) FROM queue_tickets
        WHERE queue_date = CURDATE() AND status IN ('Waiting', 'Requeued')
    ");
    $totalWaiting = (int)$waitingStmt->fetchColumn();

    // 3. Current logged-in citizen status
    $myTicket = null;
    $myPosition = null;
    $aheadCount = null;

    if (isLoggedIn() && !empty($_SESSION['resident_id'])) {
        $residentId = (int)$_SESSION['resident_id'];
        $myTicketStmt = $pdo->prepare("
            SELECT ticket_id, ticket_number, priority_level, priority_rank, status, counter_number, estimated_wait_minutes
            FROM queue_tickets
            WHERE resident_id = :rid AND queue_date = CURDATE() AND status IN ('Waiting', 'Now Serving', 'Requeued')
            ORDER BY ticket_id DESC
            LIMIT 1
        ");
        $myTicketStmt->execute([':rid' => $residentId]);
        $myTicket = $myTicketStmt->fetch();

        if ($myTicket && $myTicket['status'] === 'Waiting') {
            $aheadStmt = $pdo->prepare("
                SELECT COUNT(*) FROM queue_tickets
                WHERE queue_date = CURDATE()
                  AND status IN ('Waiting', 'Requeued')
                  AND (
                      priority_rank > :rank 
                      OR (priority_rank = :rank AND ticket_id < :tid)
                  )
            ");
            $aheadStmt->execute([
                ':rank' => $myTicket['priority_rank'],
                ':tid'  => $myTicket['ticket_id']
            ]);
            $aheadCount = (int)$aheadStmt->fetchColumn();
            $myPosition = $aheadCount + 1;
        }
    }

    echo json_encode([
        'success' => true,
        'now_serving_latest' => $nowServingLatest,
        'now_serving_frontdesk' => $counter1 ?: ($nowServingLatest ?: '--'),
        'now_serving_treasurer' => $counter2 ?: '--',
        'total_waiting' => $totalWaiting,
        'my_ticket' => $myTicket,
        'my_position' => $myPosition,
        'ahead_count' => $aheadCount,
        'timestamp' => date('h:i:s A')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
