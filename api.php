<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, DELETE'); 
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php'; 

$action = $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified.']);
    exit;
}

switch ($action) {
    case 'list':
        try {
            // Fixes Race Conditions: Data pulled instantly from transactional atomic rows
            $stmt = $pdo->query("SELECT * FROM items ORDER BY timestamp DESC");
            $items = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $items]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to pull listings: ' . $e->getMessage()]);
        }
        break;

    case 'add':
        // Protected Scope Validation Check
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Authentication required to list an item.']);
            break;
        }
        
        $sellerId = $_SESSION['user_id'];
        $input = file_get_contents('php://input');
        $newItem = json_decode($input, true);

        if (!$newItem || empty($newItem['name']) || !is_numeric($newItem['price'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or incomplete item data.']);
            break;
        }

        try {
            $itemId = uniqid();
            $imageUrl = !empty($newItem['imageUrl']) ? $newItem['imageUrl'] : null;
            
            $stmt = $pdo->prepare("INSERT INTO items (id, name, price, description, imageUrl, sellerId) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $itemId,
                $newItem['name'],
                $newItem['price'],
                $newItem['description'],
                $imageUrl,
                $sellerId
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Item added successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database write error: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        // Protected Scope Validation Check
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Authentication required to delete an item.']);
            break;
        }
        
        $currentUserId = $_SESSION['user_id'];
        $itemId = $_GET['id'] ?? null;

        if (!$itemId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing item ID.']);
            break;
        }

        try {
            // Ownership validation sequence before data alteration
            $stmt = $pdo->prepare("SELECT sellerId FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                echo json_encode(['status' => 'error', 'message' => 'Item ID not found.']);
                break;
            }
            
            // Verifies if the active session matches item authority
            if ($item['sellerId'] !== $currentUserId) {
                http_response_code(403); 
                echo json_encode(['status' => 'error', 'message' => 'You are not authorized to delete this listing.']);
                break;
            }
            
            $delStmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $delStmt->execute([$itemId]);

            echo json_encode(['status' => 'success', 'message' => 'Listing deleted successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Deletion breakdown error: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}
?>