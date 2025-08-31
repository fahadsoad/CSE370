<?php
session_start();
include 'db_connect.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT admin_id, password, role FROM Admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password, $role);
    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['role'] = $role;
        if ($role == 'owner') {
            header("Location: owner_dashboard.php");
        } else {
            header("Location: station_dashboard.php");
        }
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT customer_id, password FROM Customer WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password);
    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['role'] = 'customer';
        header("Location: customer_dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <header>
        <h1><i class="fas fa-sign-in-alt"></i> Login</h1>
    </header>
    <div class="container">
        <div class="card">
            <img src="https://picsum.photos/400/250?random=10" alt="Login Image" onerror="this.src='https://via.placeholder.com/400x250?text=No+Image'; this.alt='Image Failed to Load';">
            <h2>Access Your Account</h2>
            <p>Login as Admin, Owner, Manager, or Customer.</p>
            <?php if ($error) echo "<p style='color: #DC3545;'>$error</p>"; ?>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email or Phone:</label>
                    <input type="text" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <input type="submit" value="Login" class="btn">
            </form>
            <p>New user? <a href="customer_registration.php" class="nav-link">Register</a></p>
            <p>Admin/Owner? <a href="owner_registration.php" class="nav-link">Register Here</a></p>
        </div>
    </div>
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>
</body>
</html>

