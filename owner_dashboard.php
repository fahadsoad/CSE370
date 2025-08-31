<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$admin_id = $_SESSION['user_id'];

// Fetch owner details to link stations
$stmt = $conn->prepare("SELECT owner_id FROM Owner WHERE admin_id = ?");
if (!$stmt) {
    die("Prepare failed (Owner fetch): " . $conn->error);
}
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($owner_id);
if (!$stmt->fetch()) {
    die("Owner not found. Please contact support.");
}
$stmt->close();

if (!$owner_id) {
    die("Owner ID not retrieved.");
}

// Try full query with new columns, fallback to original if error
$stmt = $conn->prepare("SELECT station_id, station_name, location, capacity, fuel_available, gas_available, octane_available, diesel_available, petrol_available, octane_amount, diesel_amount, petrol_amount, cash_amount, station_status, service_count, total_sale FROM Station WHERE owner_id = ?");
if (!$stmt) {
    // Fallback query with original columns
    error_log("Failed to prepare full query. Using fallback. Error: " . $conn->error);
    $stmt = $conn->prepare("SELECT station_id, station_name, location, capacity, fuel_available, gas_available, station_status, service_count, cash_amount, total_sale FROM Station WHERE owner_id = ?");
    if (!$stmt) {
        die("Prepare failed (Fallback query): " . $conn->error);
    }
}
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    die("Query execution failed: " . $stmt->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <header>
        <h1><i class="fas fa-dashboard"></i> Owner Dashboard</h1>
    </header>
    <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
    <nav class="sidebar">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="owner_registration.php"><i class="fas fa-plus"></i> Add Station</a>
    </nav>
    <div class="container">
        <h2>Welcome, Owner! Manage Your Stations</h2>
        <div class="dashboard-grid">
            <?php
            while ($row = $result->fetch_assoc()) {
                echo "<div class='card'>";
                echo "<img src='https://picsum.photos/400/250?random=" . $row['station_id'] . "' alt='Station Image' onerror=\"this.src='https://via.placeholder.com/400x250?text=No+Image'; this.alt='Image Failed to Load';\">";
                echo "<h2><i class='fas fa-gas-pump'></i> " . htmlspecialchars($row['station_name']) . "</h2>";
                echo "<p><strong>Location:</strong> " . htmlspecialchars($row['location']) . "</p>";
                echo "<p><strong>Capacity:</strong> " . $row['capacity'] . " (Vacancy: " . ($row['capacity'] - $row['service_count']) . ")</p>";
                echo "<p><strong>Fuel Status:</strong> " . $row['fuel_available'] . ", Gas: " . $row['gas_available'] . "</p>";
                // Conditionally display new fuel fields if available in result
                if (isset($row['octane_available'])) {
                    echo "<p><strong>Fuel Types:</strong> Octane: " . $row['octane_available'] . " (" . $row['octane_amount'] . " litres), Diesel: " . $row['diesel_available'] . " (" . $row['diesel_amount'] . " litres), Petrol: " . $row['petrol_available'] . " (" . $row['petrol_amount'] . " litres)</p>";
                    echo "<p><strong>Cash:</strong> " . $row['cash_amount'] . " Taka</p>";
                    echo "<p><strong>Total Sale:</strong> " . $row['total_sale'] . " Taka</p>";
                } else {
                    echo "<p><strong>Cash:</strong> " . $row['cash_amount'] . " Taka</p>";
                    echo "<p><strong>Total Sale:</strong> " . $row['total_sale'] . " Taka</p>";
                }
                echo "<p><strong>Status:</strong> " . $row['station_status'] . "</p>";
                echo "<a href='station_dashboard.php?sid=" . $row['station_id'] . "' class='btn'>View & Update</a>";
                echo "</div>";
            }
            if ($result->num_rows === 0) {
                echo "<div class='card'>";
                echo "<img src='https://picsum.photos/400/250?random=0' alt='No Stations Image' onerror=\"this.src='https://via.placeholder.com/400x250?text=No+Image'; this.alt='Image Failed to Load';\">";
                echo "<h2>No Stations Yet</h2>";
                echo "<p>Register a new station to get started.</p>";
                echo "<a href='owner_registration.php' class='btn'>Add Station</a>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>