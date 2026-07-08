<?php
/**
 * community_auth.php — Handles registration, login, profile updates, and Google OAuth2 verification.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/coming_soon.php';
coming_soon_gate('community', true);

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'google_login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $credential = $input['credential'] ?? '';

    if (empty($credential)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID token']);
        exit;
    }

    // Verify token using Google's TokenInfo API
    $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
    $ch = curl_init($verifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid Google ID token']);
        exit;
    }

    $payload = json_decode($response, true);
    if (!isset($payload['sub'], $payload['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token payload']);
        exit;
    }

    $googleId = $payload['sub'];
    $email = $payload['email'];
    $name = $payload['name'] ?? explode('@', $email)[0];
    $picture = $payload['picture'] ?? null;

    // Check if member already exists
    $stmt = $conn->prepare("SELECT * FROM members WHERE google_id = ? OR email = ?");
    $stmt->bind_param("ss", $googleId, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();

    if ($member) {
        // Link google_id if not linked
        if (empty($member['google_id'])) {
            $stmt = $conn->prepare("UPDATE members SET google_id = ?, profile_pic = COALESCE(profile_pic, ?) WHERE id = ?");
            $stmt->bind_param("ssi", $googleId, $picture, $member['id']);
            $stmt->execute();
            $stmt->close();
            $member['google_id'] = $googleId;
            if (empty($member['profile_pic'])) {
                $member['profile_pic'] = $picture;
            }
        }
    } else {
        // Create new member
        // 1. Generate unique username
        $usernameBase = preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]);
        if (empty($usernameBase)) {
            $usernameBase = 'user';
        }
        $username = $usernameBase;
        
        // Loop until username is unique
        $isUnique = false;
        $counter = 0;
        while (!$isUnique) {
            $testUsername = $counter === 0 ? $username : $username . rand(100, 999);
            $chk = $conn->prepare("SELECT id FROM members WHERE username = ?");
            $chk->bind_param("s", $testUsername);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows === 0) {
                $username = $testUsername;
                $isUnique = true;
            }
            $chk->close();
            $counter++;
            if ($counter > 10) {
                $username = $username . time();
                break;
            }
        }

        // 2. Insert into members
        $stmt = $conn->prepare("INSERT INTO members (google_id, name, username, email, profile_pic) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $googleId, $name, $username, $email, $picture);
        if ($stmt->execute()) {
            $memberId = $stmt->insert_id;
            $member = [
                'id' => $memberId,
                'google_id' => $googleId,
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'profile_pic' => $picture,
                'bio' => null,
                'status' => 'active'
            ];
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create member account']);
            exit;
        }
        $stmt->close();
    }

    if ($member['status'] === 'suspended') {
        http_response_code(403);
        echo json_encode(['error' => 'Your account has been suspended.']);
        exit;
    }

    // Initialize session
    $_SESSION['member_id'] = $member['id'];
    $_SESSION['member_name'] = $member['name'];
    $_SESSION['member_username'] = $member['username'];
    $_SESSION['member_profile_pic'] = $member['profile_pic'];

    echo json_encode([
        'status' => 'success',
        'user' => [
            'id' => $member['id'],
            'name' => $member['name'],
            'username' => $member['username'],
            'profile_pic' => $member['profile_pic'],
            'bio' => $member['bio']
        ]
    ]);
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['member_id']);
    unset($_SESSION['member_name']);
    unset($_SESSION['member_username']);
    unset($_SESSION['member_profile_pic']);
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'update_profile') {
    if (!isset($_SESSION['member_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $name = '';
    $bio = '';
    
    // Check Content-Type for JSON vs Form data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $bio = trim($input['bio'] ?? '');
    } else {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
    }

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Display Name cannot be empty']);
        exit;
    }

    $profilePicPath = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Only JPG, JPEG, PNG, and WEBP formats are allowed']);
            exit;
        }
        if ($_FILES['profile_pic']['size'] > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Image size cannot exceed 2MB']);
            exit;
        }

        $uploadDir = __DIR__ . '/uploads/community/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = 'profile_' . $_SESSION['member_id'] . '_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {
            $profilePicPath = 'uploads/community/' . $filename;

            // Fetch and delete old local profile pic if any
            $oldPic = null;
            $q = $conn->prepare("SELECT profile_pic FROM members WHERE id = ?");
            $q->bind_param("i", $_SESSION['member_id']);
            $q->execute();
            $q->bind_result($oldPic);
            $q->fetch();
            $q->close();

            if ($oldPic && !preg_match("~^(?:f|ht)tps?://~i", $oldPic)) {
                $oldFilePath = __DIR__ . '/' . ltrim($oldPic, '/');
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }
        }
    }

    if ($profilePicPath !== null) {
        $stmt = $conn->prepare("UPDATE members SET name = ?, bio = ?, profile_pic = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $bio, $profilePicPath, $_SESSION['member_id']);
    } else {
        $stmt = $conn->prepare("UPDATE members SET name = ?, bio = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $bio, $_SESSION['member_id']);
    }

    if ($stmt->execute()) {
        $_SESSION['member_name'] = $name;
        if ($profilePicPath !== null) {
            $_SESSION['member_profile_pic'] = $profilePicPath;
        }
        
        $avatarUrl = $_SESSION['member_profile_pic'] ?? '';
        if ($avatarUrl && !preg_match("~^(?:f|ht)tps?://~i", $avatarUrl)) {
            $avatarUrl = $site_url . '/' . ltrim($avatarUrl, '/');
        } elseif (!$avatarUrl) {
            $avatarUrl = $site_url . '/uploads/community/default_avatar.png';
        }

        echo json_encode([
            'status' => 'success',
            'name' => $name,
            'bio' => $bio,
            'profile_pic' => $avatarUrl
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile']);
    }
    $stmt->close();
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
?>
