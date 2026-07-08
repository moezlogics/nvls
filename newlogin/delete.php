<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage.php");
    exit;
}

$id = (int)$_GET['id'];

// Handle Delete
$query = "DELETE FROM posts WHERE id = $id";
if ($conn->query($query) === TRUE) {
    clear_page_cache();
    header("Location: manage.php?msg=deleted");
} else {
    error_log("Database error deleting post: " . $conn->error);
    echo "Something went wrong. Please try again.";
}
?>
