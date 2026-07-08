<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['name'])) {
        $name = $conn->real_escape_string(trim($_POST['name']));
    if (!empty($name)) {
        // Check if exists
        $check = $conn->query("SELECT id FROM categories WHERE name='$name'");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Category already exists.']);
            exit;
        }
        
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $name)));
        $query = "INSERT INTO categories (name, slug) VALUES ('$name', '$slug')";
        if ($conn->query($query) === TRUE) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id, 'name' => htmlspecialchars($name)]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit;
        }
    }
}
}
echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
