<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

// Search by location
$location = $_GET['location'] ?? '';
$stations = [];
if ($location) {
    $stmt = $conn->prepare("SELECT * FROM Station WHERE location LIKE ?");
    $loc = "%$location%";
    $stmt->bind_param("s", $loc);
    $stmt->execute();
    $stations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM Station");
    $stmt->execute();
    $stations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    $station_id = $_POST['station_id'];
    $service_date = $_POST['service_date'];
    $service_time = $_POST['service_time'];
    $pre_booking = 'yes';
    $payment_status = 'pending'; // Assume online payment later

    $stmt = $conn->prepare("INSERT INTO Service (customer_id, station_id, service_date, service_time, pre_booking, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $user_id, $station_id, $service_date, $service_time, $pre_booking, $payment_status);
    $stmt->execute();
    $stmt->close();
}

// Handle review
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['review'])) {
    $station_id = $_POST['station_id'];
    $review_text = $_POST['review_text'];
    $rating = $_POST['rating'];

    $stmt = $conn->prepare("INSERT INTO Review (customer_id, station_id, review_text, rating) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $user_id, $station_id, $review_text, $rating);
    $stmt->execute();
    $stmt->close();
}

// Handle preorder food (assume add to Service or new table; here add as note in Service)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['preorder'])) {
    $station_id = $_POST['station_id'];
    $food_note = "Preorder: Dry Food: " . $_POST['dry_food'] . ", Set Menu: " . $_POST['set_menu'] . ", Drinks: " . $_POST['drinks'];

    // Add to Service note or separate table; here use review_text as placeholder for simplicity
    $stmt = $conn->prepare("INSERT INTO Service (customer_id, station_id, pre_booking) VALUES (?, ?, 'yes')");
    $stmt->bind_param("ii", $user_id, $station_id);
    $stmt->execute();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Gas & Fuel Station</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>
    <header>
        <h1><i class="fas fa-user"></i> Customer Dashboard</h1>
    </header>
    <div class="container">
        <h2>Search Stations</h2>
        <form action="" method="GET">
            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>">
            </div>
            <input type="submit" value="Search" class="btn">
        </form>
        <div class="dashboard-grid">
            <?php foreach ($stations as $station): ?>
                <div class="card">
                    <img src="https://picsum.photos/400/250?random=<?php echo $station['station_id']; ?>" alt="Station">
                    <h2><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($station['station_name']); ?></h2>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($station['location']); ?></p>
                    <p><strong>Availability:</strong> Fuel: <?php echo $station['fuel_available']; ?>, Gas: <?php echo $station['gas_available']; ?></p>
                    <p><strong>Capacity Vacancy:</strong> <?php echo $station['capacity'] - $station['service_count']; ?> spots</p>
                    <p><strong>Reviews:</strong> Average rating (calculate if needed)</p>
                    <p><strong>Fuel Types:</strong> Octane: <?php echo $station['octane_available']; ?>, Diesel: <?php echo $station['diesel_available']; ?>, Petrol: <?php echo $station['petrol_available']; ?></p>
                    <form method="POST">
                        <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                        <div class="form-group">
                            <label for="service_date">Date:</label>
                            <input type="date" name="service_date" required>
                        </div>
                        <div class="form-group">
                            <label for="service_time">Time:</label>
                            <input type="time" name="service_time" required>
                        </div>
                        <input type="submit" name="book" value="Book Service" class="btn">
                    </form>
                    <form method="POST">
                        <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                        <h3>Preorder Food</h3>
                        <label><input type="checkbox" name="dry_food"> Dry Food</label>
                        <label><input type="checkbox" name="set_menu"> Set Menu</label>
                        <label><input type="checkbox" name="drinks"> Drinks</label>
                        <input type="submit" name="preorder" value="Preorder" class="btn">
                    </form>
                    <form method="POST">
                        <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                        <h3>Give Review</h3>
                        <textarea name="review_text" placeholder="Your review" required></textarea>
                        <select name="rating" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                        <input type="submit" name="review" value="Submit Review" class="btn">
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>