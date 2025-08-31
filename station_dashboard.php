<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'owner' && $_SESSION['role'] != 'manager')) {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$sid = $_GET['sid'] ?? null;
if (!$sid) {
    die("No station ID provided.");
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$authorized = false;
if ($role == 'owner') {
    $stmt = $conn->prepare("SELECT owner_id FROM Owner WHERE admin_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($owner_id);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $sid, $owner_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $authorized = true;
    }
    $stmt->close();
} elseif ($role == 'manager') {
    $stmt = $conn->prepare("SELECT station_id FROM Manager WHERE admin_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($manager_station_id);
    $stmt->fetch();
    $stmt->close();
    if ($manager_station_id == $sid) {
        $authorized = true;
    }
}

if (!$authorized) {
    die("Unauthorized access.");
}

// Fetch station details
$stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$station = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch owner name
$owner_name = '';
if ($station['owner_id']) {
    $stmt = $conn->prepare("SELECT a.name FROM Admin a JOIN Owner o ON a.admin_id = o.admin_id WHERE o.owner_id = ?");
    $stmt->bind_param("i", $station['owner_id']);
    $stmt->execute();
    $stmt->bind_result($owner_name);
    $stmt->fetch();
    $stmt->close();
}

// Fetch food corner details
$stmt = $conn->prepare("SELECT * FROM Food_Corner WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc() ?? ['dry_food' => 'no', 'set_menu' => 'no', 'drinks' => 'no'];
$stmt->close();

// Fetch reviews
$reviews = [];
$stmt = $conn->prepare("SELECT review_text, rating, review_date FROM Review WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($role == 'owner') {
        $station_name = $_POST['station_name'];
        $location = $_POST['location'];
        $capacity = $_POST['capacity'];
        $octane_amount = $_POST['octane_amount'] ?? 0;
        $diesel_amount = $_POST['diesel_amount'] ?? 0;
        $petrol_amount = $_POST['petrol_amount'] ?? 0;

        $stmt = $conn->prepare("UPDATE Station SET station_name = ?, location = ?, capacity = ?, octane_amount = ?, diesel_amount = ?, petrol_amount = ? WHERE station_id = ?");
        $stmt->bind_param("ssidddi", $station_name, $location, $capacity, $octane_amount, $diesel_amount, $petrol_amount, $sid);
        $stmt->execute();
        $stmt->close();

        if (isset($_POST['delete_station'])) {
            $stmt = $conn->prepare("DELETE FROM Station WHERE station_id = ?");
            $stmt->bind_param("i", $sid);
            $stmt->execute();
            $stmt->close();
            header("Location: owner_dashboard.php");
            exit();
        }
    } elseif ($role == 'manager') {
        $fuel_available = $_POST['fuel_available'];
        $gas_available = $_POST['gas_available'];
        $octane_available = $_POST['octane_available'];
        $diesel_available = $_POST['diesel_available'];
        $petrol_available = $_POST['petrol_available'];
        $total_sale = $_POST['total_sale'];

        $stmt = $conn->prepare("UPDATE Station SET fuel_available = ?, gas_available = ?, octane_available = ?, diesel_available = ?, petrol_available = ?, total_sale = ? WHERE station_id = ?");
        $stmt->bind_param("sssssdi", $fuel_available, $gas_available, $octane_available, $diesel_available, $petrol_available, $total_sale, $sid);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: station_dashboard.php?sid=$sid");
    exit();
}

// Update station status based on capacity
if ($station['service_count'] >= $station['capacity']) {
    $stmt = $conn->prepare("UPDATE Station SET station_status = 'off' WHERE station_id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE Station SET station_status = 'on' WHERE station_id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $stmt->close();
}

// Refresh station data after updates
$stmt = $conn->prepare("SELECT * FROM Station WHERE station_id = ?");
$stmt->bind_param("i", $sid);
$stmt->execute();
$station = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Dashboard - <?php echo htmlspecialchars($station['station_name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <header>
        <h1><i class="fas fa-gas-pump"></i> Station Dashboard: <?php echo htmlspecialchars($station['station_name']); ?></h1>
    </header>
    
    <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
    
    <nav class="sidebar">
        <a href="owner_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    
    <div class="container">
        <div class="dashboard-grid">
            <div class="card">
                <img src="https://picsum.photos/400/250?random=5" alt="Station Details">
                <h2><i class="fas fa-info-circle"></i> Station Details</h2>
                <p><strong>ID:</strong> <?php echo $station['station_id']; ?></p>
                <p><strong>Owner:</strong> <?php echo htmlspecialchars($owner_name); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($station['location']); ?></p>
                <p><strong>Capacity:</strong> <?php echo $station['capacity']; ?></p>
                <p><strong>Current Customers:</strong> <?php echo $station['service_count']; ?></p>
                <p><strong>Status:</strong> <?php echo $station['station_status']; ?></p>
                <p><strong>Fuel Available:</strong> <span class="highlight"><?php echo $station['fuel_available']; ?></span></p>
                <p><strong>Gas Available:</strong> <span class="highlight"><?php echo $station['gas_available']; ?></span></p>
                <p><strong>Octane Available:</strong> <span class="highlight"><?php echo $station['octane_available']; ?> (<?php echo $station['octane_amount']; ?> liters)</span></p>
                <p><strong>Diesel Available:</strong> <span class="highlight"><?php echo $station['diesel_available']; ?> (<?php echo $station['diesel_amount']; ?> liters)</span></p>
                <p><strong>Petrol Available:</strong> <span class="highlight"><?php echo $station['petrol_available']; ?> (<?php echo $station['petrol_amount']; ?> liters)</span></p>
                <p><strong>Cash Amount:</strong> <span class="highlight"><?php echo $station['cash_amount']; ?> Taka</span></p>
                <p><strong>Total Sale:</strong> <span class="highlight"><?php echo $station['total_sale']; ?> Taka</span></p>
            </div>
            
            <div class="card">
                <img src="https://picsum.photos/400/250?random=6" alt="Food Corner">
                <h2><i class="fas fa-utensils"></i> Food Corner</h2>
                <p><strong>Dry Food:</strong> <?php echo $food['dry_food']; ?></p>
                <p><strong>Set Menu:</strong> <?php echo $food['set_menu']; ?></p>
                <p><strong>Drinks:</strong> <?php echo $food['drinks']; ?></p>
                <a href="#" class="btn">Manage Options</a>
            </div>
            
            <div class="card">
                <img src="https://picsum.photos/400/250?random=7" alt="Customer Reviews">
                <h2><i class="fas fa-star"></i> Customer Reviews</h2>
                <?php if (empty($reviews)): ?>
                    <p>No reviews yet.</p>
                <?php else: ?>
                    <ul class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <li>
                            <strong>Rating:</strong> <span class="rating"><?php echo $review['rating']; ?>/5</span> <i class="fas fa-star"></i><br>
                            <strong>Review:</strong> <?php echo htmlspecialchars($review['review_text']); ?><br>
                            <strong>Date:</strong> <?php echo $review['review_date']; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($role == 'owner'): ?>
            <div class="card">
                <img src="https://picsum.photos/400/250?random=8" alt="Update Station">
                <h2><i class="fas fa-edit"></i> Update Station</h2>
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="station_name">Station Name:</label>
                        <input type="text" name="station_name" value="<?php echo htmlspecialchars($station['station_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="location">Location:</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($station['location']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity:</label>
                        <input type="number" name="capacity" value="<?php echo $station['capacity']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="octane_amount">Octane Amount (liters):</label>
                        <input type="number" step="0.01" name="octane_amount" value="<?php echo $station['octane_amount']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="diesel_amount">Diesel Amount (liters):</label>
                        <input type="number" step="0.01" name="diesel_amount" value="<?php echo $station['diesel_amount']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="petrol_amount">Petrol Amount (liters):</label>
                        <input type="number" step="0.01" name="petrol_amount" value="<?php echo $station['petrol_amount']; ?>">
                    </div>
                    <input type="submit" value="Update" class="btn">
                    <button type="submit" name="delete_station" class="btn" style="background: linear-gradient(to right, #e74c3c, #c0392b);">Delete Station</button>
                </form>
            </div>
        <?php elseif ($role == 'manager'): ?>
            <div class="card">
                <img src="https://picsum.photos/400/250?random=9" alt="Update Availability">
                <h2><i class="fas fa-edit"></i> Update Availability</h2>
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="fuel_available">Fuel Available:</label>
                        <select name="fuel_available">
                            <option value="yes" <?php if ($station['fuel_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                            <option value="no" <?php if ($station['fuel_available'] == 'no') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="gas_available">Gas Available:</label>
                        <select name="gas_available">
                            <option value="yes" <?php if ($station['gas_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                            <option value="no" <?php if ($station['gas_available'] == 'no') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="octane_available">Octane Available:</label>
                        <select name="octane_available">
                            <option value="yes" <?php if ($station['octane_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                            <option value="no" <?php if ($station['octane_available'] == 'no') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="diesel_available">Diesel Available:</label>
                        <select name="diesel_available">
                            <option value="yes" <?php if ($station['diesel_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                            <option value="no" <?php if ($station['diesel_available'] == 'no') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="petrol_available">Petrol Available:</label>
                        <select name="petrol_available">
                            <option value="yes" <?php if ($station['petrol_available'] == 'yes') echo 'selected'; ?>>Yes</option>
                            <option value="no" <?php if ($station['petrol_available'] == 'no') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="total_sale">Total Sale (Taka):</label>
                        <input type="number" step="0.01" name="total_sale" value="<?php echo $station['total_sale']; ?>" required>
                    </div>
                    <input type="submit" value="Update" class="btn">
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>Contact: support@gasfuelstation.com | +880-123-456-789</p>
        <p>&copy; 2025 Gas & Fuel Station. All rights reserved.</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>