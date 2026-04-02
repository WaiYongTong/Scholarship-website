<?php
declare(strict_types=1);

session_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

include('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'committee') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit();
}

$appID = isset($_GET['applicationID']) ? (int)$_GET['applicationID'] : 0;
if ($appID <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid applicationID']);
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');

$sql = "
    SELECT reviewID, reviewerID, score, comments, review_Date, review_status
    FROM review
    WHERE applicationID = ?
    ORDER BY review_Date DESC, reviewID DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $appID);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    $stmt->close();
    exit();
}

$stmt->bind_result($reviewID, $reviewerID, $score, $comments, $reviewDate, $reviewStatus);

$data = [];
while ($stmt->fetch()) {
    $data[] = [
        'reviewID'      => $reviewID,
        'reviewerID'    => $reviewerID,
        'score'         => $score,
        'comments'      => $comments,
        'review_Date'   => $reviewDate,
        'review_status' => $reviewStatus
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
