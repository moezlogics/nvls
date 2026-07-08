<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_id'])) {
    header("Location: logout.php");
    exit;
}
require_once '../lib/image_seo.php';

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $email = $conn->real_escape_string($_POST['email']);
    $bio = $conn->real_escape_string($_POST['bio']);
    
    // Handle File Uploads
    $profile_pic_query = "";
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['profile_pic'], __DIR__ . '/../images', 'images');
        if ($up['ok']) {
            $profile_pic = $conn->real_escape_string($up['path']);
            $profile_pic_query = ", profile_pic='$profile_pic'";
        }
    }

    // Handle Password Update
    $password_query = "";
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_query = ", password='$password'";
    }

    $query = "UPDATE admin_users SET email='$email', bio='$bio' $profile_pic_query $password_query WHERE id=$admin_id";
    
    if ($conn->query($query) === TRUE) {
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Profile updated successfully!</div>";
    } else {
        error_log("Database error: " . $conn->error); $msg = "<div class='alert alert-danger'><i class='fa-solid fa-circle-xmark me-2'></i> Something went wrong. Please try again.</div>";
    }
}

$user = $conn->query("SELECT * FROM admin_users WHERE id = $admin_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4">My Profile</h2>

        <?php if(isset($msg)) echo $msg; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="" method="POST" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-3 text-center mb-4">
                            <div class="mb-3">
                                <img src="<?php echo !empty($user['profile_pic']) ? '../' . $user['profile_pic'] : 'https://placehold.co/150x150/6366f1/ffffff?text=' . strtoupper(substr($user['username'] ?? 'A',0,1)); ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Profile Picture</label>
                                <input type="file" name="profile_pic" class="form-control form-control-sm" accept="image/*">
                            </div>
                            <div class="badge bg-primary text-uppercase"><?php echo str_replace('_', ' ', $user['role']); ?></div>
                        </div>
                        <div class="col-md-9">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Username</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <small class="text-muted">Username cannot be changed.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Bio / About Me</label>
                                <textarea name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                            </div>
                            <div class="mb-4 border-top pt-4 mt-4">
                                <h5>Change Password</h5>
                                <p class="text-muted small">Leave blank if you don't want to change your password.</p>
                                <input type="password" name="password" class="form-control" placeholder="New Password">
                            </div>
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
