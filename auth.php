<?php
session_start(); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST'); 
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php'; 

$action = $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No authentication action specified.']);
    exit;
}

switch ($action) {
    case 'signup':
        $input = file_get_contents('php://input');
        $userData = json_decode($input, true);

        if (!$userData || empty($userData['username']) || empty($userData['password']) || empty($userData['email'])) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            break;
        }

        $username = strtolower(trim($userData['username']));
        $email = trim($userData['email']);
        $birthdate = !empty($userData['birthdate']) ? $userData['birthdate'] : null;
        
        // Fixes Plain-Text Exposure Vulnerability
        $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT); 

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
                exit;
            }

            $newId = uniqid("user_");
            $stmt = $pdo->prepare("INSERT INTO users (id, username, email, birthdate, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$newId, $userData['username'], $email, $birthdate, $hashedPassword]);

            echo json_encode(['status' => 'success', 'message' => 'Account created successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'login':
        $input = file_get_contents('php://input');
        $credentials = json_decode($input, true);

        if (!$credentials || empty($credentials['username']) || empty($credentials['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing username or password.']);
            break;
        }

        $username = trim($credentials['username']);
        $password = $credentials['password'];

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                echo json_encode(['status' => 'success', 'message' => 'Login successful.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Query failure: ' . $e->getMessage()]);
        }
        break;
        
    case 'check_session':
        // Fixes Asynchronous State Mismatches: Always returns explicit status
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'status' => 'success', 
                'loggedIn' => true, 
                'userId' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? 'User'
            ]);
        } else {
            echo json_encode(['status' => 'success', 'loggedIn' => false]);
        }
        break;

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}
?>