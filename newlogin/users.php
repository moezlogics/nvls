<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] != 'main_admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    if (isset($_POST['add_user'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $conn->real_escape_string($_POST['role']);
        
        $check = $conn->query("SELECT id FROM admin_users WHERE username='$username' OR email='$email'");
        if ($check->num_rows > 0) {
            $msg = "<div class='alert alert-danger'>Username or Email already exists!</div>";
        } else {
            $conn->query("INSERT INTO admin_users (username, email, password, role) VALUES ('$username', '$email', '$password', '$role')");
            $msg = "<div class='alert alert-success'>User added successfully!</div>";
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        if ($id != $_SESSION['admin_id']) {
            $conn->query("DELETE FROM admin_users WHERE id=$id");
            $msg = "<div class='alert alert-success'>User deleted!</div>";
        } else {
            $msg = "<div class='alert alert-danger'>You cannot delete yourself!</div>";
        }
    }
}

$users = $conn->query("SELECT * FROM admin_users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Manage Users</h2>

        <?php if(isset($msg)) echo $msg; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Add New User</div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="add_user" value="1">
                            <div class="mb-3">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="author">Author (Can post)</option>
                                    <option value="main_admin">Main Admin (Full Access)</option>
                                </select>
                            </div>
                            <button class="btn btn-primary w-100">Add User</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover m-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $u['role'] == 'main_admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                            <?php echo str_replace('_', ' ', $u['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($u['id'] != $_SESSION['admin_id']): ?>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="delete_user" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
