<?php
include_once __DIR__ . '/../config/dbConnect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $rate_name = $_POST['rate_name'] ?? null;
        $rate = $_POST['rate'] ?? null;

        if (!$rate_name || $rate === null) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        // Check if rate_name already exists
        $check_stmt = $conn->prepare('SELECT rateID FROM rates WHERE rate_name = ?');
        $check_stmt->bind_param('s', $rate_name);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Rate name already exists.']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();

        $stmt = $conn->prepare('INSERT INTO rates (rate_name, rate) VALUES (?, ?)');
        $stmt->bind_param('sd', $rate_name, $rate);
        if ($stmt->execute()) {
            $rateID = $conn->insert_id;
            $rateData = [
                'rateID' => $rateID,
                'rate_name' => $rate_name,
                'rate' => $rate
            ];
            echo json_encode(['success' => true, 'rate' => $rateData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating rate.']);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'update') {
        $rateID = $_POST['rateID'] ?? null;
        $rate_name = $_POST['rate_name'] ?? null;
        $rate = $_POST['rate'] ?? null;
        // $description = $_POST['description'] ?? null;
        if (!$rateID || !$rate_name || $rate === null) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE rates SET rate_name = ?, rate = ? WHERE rateID = ?');
        $stmt->bind_param('sdi', $rate_name, $rate, $rateID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating rate.']);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'delete') {
        $rateID = $_POST['rateID'] ?? null;
        if (!$rateID) {
            echo json_encode(['success' => false, 'message' => 'Missing rateID.']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM rates WHERE rateID = ?');
        $stmt->bind_param('i', $rateID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting rate.']);
        }
        $stmt->close();
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rates = [];
    $sql = "SELECT * FROM rates";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rates[] = $row;
        }
    }
    echo json_encode(['success' => true, 'rates' => $rates]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
