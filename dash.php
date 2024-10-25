<?php
session_start();

require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Use the global $database variable
global $database;

// Get initial esp_presence value
$espPresenceRef = $database->getReference('esp_presence');
$initialEspPresence = $espPresenceRef->getValue();

// Fetch product data
$productsRef = $database->getReference('tables/products');
$products = $productsRef->getValue();

// Fetch transaction data
$transactionsRef = $database->getReference('tables/transactions');
$transactions = $transactionsRef->getValue();

// Function to get transaction counts for a given date range
function getTransactionCounts($transactions, $startDate, $endDate) {
    $dailyTransactions = [
        'Product 1' => [],
        'Product 2' => []
    ];
    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);

    while ($currentDate <= $endDateTime) {
        $dateString = $currentDate->format('Y-m-d');
        $dailyTransactions['Product 1'][$dateString] = 0;
        $dailyTransactions['Product 2'][$dateString] = 0;
        $currentDate->modify('+1 day');
    }

    if ($transactions) {
        foreach ($transactions as $transaction) {
            $transactionDate = $transaction['date'];
            $productIdentity = $transaction['product_identity'];
            if (isset($dailyTransactions[$productIdentity][$transactionDate])) {
                $dailyTransactions[$productIdentity][$transactionDate]++;
            }
        }
    }

    return $dailyTransactions;
}

// Default to last 7 days
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-6 days'));
$dailyTransactions = getTransactionCounts($transactions, $startDate, $endDate);

// Function to determine stock status
function getStockStatus($quantity) {
    if ($quantity >= 0 && $quantity <= 2) {
        return ['Critical Stock', 'danger'];
    } elseif ($quantity >= 3 && $quantity <= 5) {
        return ['Low Stock', 'warning'];
    } else {
        return ['Available', 'success'];
    }
}

// Simulating internet connection status (replace with actual logic)
$isConnectedToInternet = true;

// Function to get critical stock products
function getCriticalStockProducts($products) {
    return array_filter($products, function($product) {
        return $product['product_quantity'] <= 2;
    });
}

$criticalStockProducts = getCriticalStockProducts($products);

// Add this after line 23 (after initializing the database)
$userId = $_SESSION['user_id'];
$userRole = '';
$usersRef = $database->getReference('tables/user');
$snapshot = $usersRef->getSnapshot();
$users = $snapshot->getValue();

foreach ($users as $key => $user) {
    if ($user['user_id'] === $userId) {
        $userRole = $user['u_role'];
        break;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HygienexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* General Styles */
        body {
            background-color: #f4f6f9; 
            color: black; 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
        }

        /* Header Section Styles */
        .header {
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 15px 20px; 
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            z-index: 1000; 
        }
        .header .logo {
            font-size: 24px; 
            font-weight: bold; 
            color: #369c43;
        }
        .header .user-menu {
            display: flex; 
            align-items: center;
            position: relative; 
            margin-left: auto;
        }
        .header .user-menu .user-name {
            margin-right: 10px; 
            font-size: 18px; /* Increased from 16px */
            font-weight: 600; /* Make the font semi-bold */
            letter-spacing: 0.5px; /* Add slight letter spacing for emphasis */
            color: #369c43; /* Use your preferred color, this is a blue shade */
            text-transform: uppercase; /* Make the text uppercase for more emphasis */
        }
        .header .user-menu img {
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            cursor: pointer; 
        }
        .header .user-menu .dropdown {
            display: none;
            position: absolute;
            top: 40px; 
            right: 0;
            background-color: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            min-width: 160px; 
            border-radius: 5px; 
        }

        .header .user-menu:hover .dropdown {
            display: block;
        }

        .header .user-menu .dropdown a {
            color: #343a40;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .header .user-menu .dropdown a:hover {
            background-color: #f1f1f1;
        }

        /* Container for the sidebar and content */
        .main-container {
            display: flex; 
            flex: 1; 
            margin-top: 60px; 
        }

        /* Sidebar Menu Styles */
        .sidebar {
            background-color: #e9ecef;
            padding: 15px;
            height: calc(100vh - 60px);
            position: fixed;
            width: 250px; 
            top: 79px;
            left: 0;
            z-index: 1001; 
        }
        .sidebar a {
            color: #369c43;
            text-decoration: none;
            font-size: 16px; 
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .sidebar a:hover {
            background-color: #d6d8db;
        }
        .sidebar i {
            margin-right: 10px;
            font-size: 18px; 
        }

        /* Main Content Styles */
        .content {
            flex: 1;
            padding: 20px;
            margin-left: 250px; 
            margin-top: 20px; /* Adjust this value to move the card higher */
        }

        /* Card Styles */
        .card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.8rem;
            color: #333333;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 15px; 
        }
        .form-control {
            border-radius: 5px; 
        }
        .btn-primary {
            background-color: #369c43; 
            border-color: #369c43; 
        }
        .btn-success {
            background-color: #28a745; 
            border-color: #28a745; 
        }

        /* Visible only on small screens */
        .menu-bar-icon {
            display: none; 
        }

        /*  Small Screens */
        @media (max-width: 767px) {
            .menu-bar-icon {
                display: block; 
                font-size: 24px; 
                cursor: pointer; 
                margin: 10px; 
            }
            .sidebar {
                transform: translateX(-100%); 
                transition: transform 0.3s ease; 
                width: 250px; 
            }
            .sidebar.active {
                transform: translateX(0); 
            }
            .content {
                margin-left: 0;
                margin-top: 60px; /* Adjust for mobile view */
            }
        }

        /* New styles for the awesome dashboard */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .dashboard-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .dashboard-card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
        }
        .dashboard-card-header h4 {
            margin: 0;
            font-size: 1.2rem;
            color: #333333;
            font-weight: 600;
        }
        .dashboard-card-body {
            padding: 20px;
            text-align: center;
        }
        .stock-level {
            font-size: 24px;
            font-weight: bold;
        }
        .chart-container {
            height: 300px;
        }
        .time-range-selector {
            margin-bottom: 20px;
        }
        .stock-status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .stock-danger {
            background-color: #dc3545;
            color: white;
        }
        .stock-warning {
            background-color: #ffc107;
            color: black;
        }
        .stock-success {
            background-color: #28a745;
            color: white;
        }

        /* Increase the size of the globe icon */
        #connectionIcon {
            font-size: 48px; /* Increased from the default size */
            margin-bottom: 10px; /* Add some space below the icon */
        }

        /* Increase the size of the product icons */
        .dashboard-card .icon {
            font-size: 48px; /* Increased from the default size */
            margin-bottom: 10px; /* Add some space below the icon */
        }

        /* Adjust the stock level text size */
        .stock-level {
            font-size: 28px; /* Slightly increase the font size */
            font-weight: bold;
            margin: 10px 0; /* Add some vertical spacing */
        }

        /* Styles for the troubleshoot button */
        .troubleshoot-container {
            display: flex;
            justify-content: center;
            margin-top: 15px; /* Adjust this value to move the button lower */
        }

        /* Apply the 'Available' style to the troubleshoot button */
        #troubleshootButton {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: inline-block;
            background-color: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
        }

        /* Logo styles */
        .header .logo {
            display: flex;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
            color: #369c43;
        }

        .header .logo-image {
            height: 50px; /* Increase or decrease this value */
            width: auto; /* This maintains the aspect ratio */
            margin-right: 10px;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header class="header">
        <div class="logo">
            <img src="logo2.png" alt="HygienexCare Logo" class="logo-image">
            HYGIENEXCARE
        </div>
        <div class="user-menu">
            <span class="user-name">Hello, <?php echo htmlspecialchars($_SESSION['user_id']); ?>!</span>
            <img src="admin.jfif" alt="User Picture">
            <div class="dropdown">
                <a href="#" id="editProfileLink">Edit Profile</a>
                <a href="logout.php">Log-Out</a>
            </div>
        </div>
        <div class="menu-bar-icon" id="menu-bar-icon">
            <i class="fas fa-bars"></i>
        </div>
    </header>

    <!-- Container for sidebar and content -->
    <div class="main-container">
        <!-- Sidebar Menu -->
        <nav class="sidebar" id="sidebar" data-user-role="<?php echo $userRole; ?>">
            <a href="dash.php" class="menu-item"><i class="fas fa-box"></i> System Status</a>
            <a href="prod.php" class="menu-item"><i class="fas fa-shopping-bag"></i> Product Inventory</a>
            <a href="trans.php" class="menu-item"><i class="fas fa-tag"></i> Transaction History</a>
            <a href="act.php" class="menu-item" id="activityLogLink"><i class="fas fa-history"></i> Activity Log</a>
            <a href="#" class="menu-item" id="userManagementLink"><i class="fas fa-cog"></i> User Management</a>
        </nav>

        <!-- Main Content Area -->
        <div class="content" id="content">
            <div class="card">
                <div class="card-header">
                    <h2>System Status</h2>
                    <p>Today's date: <strong><?php echo date('l, F j, Y'); ?></strong></p>
                </div>
                <div class="card-body">
                    <?php if (!empty($criticalStockProducts)): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Critical Stock Alert!</strong> The following products need immediate restocking:
                            <ul>
                                <?php foreach ($criticalStockProducts as $product): ?>
                                    <li><?php echo htmlspecialchars($product['product_name']); ?> (Quantity: <?php echo $product['product_quantity']; ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-grid">
                        <!-- Device Status Card -->
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h4>Device Status</h4>
                            </div>
                            <div class="dashboard-card-body">
                                <i id="connectionIcon" class="fas fa-globe icon text-warning"></i>
                                <p id="connectionStatus">Checking...</p>
                                <div class="troubleshoot-container">
                                    <button id="troubleshootButton" class="btn btn-primary" onclick="showTroubleshootModal()" style="display: none;">Troubleshoot</button>
                                </div>
                            </div>
                        </div>

                        <!-- Total Income Card -->
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h4>Total Income</h4>
                            </div>
                            <div class="dashboard-card-body">
                                <i class="fas fa-peso-sign icon text-success"></i>
                                <p class="stock-level" id="totalIncome">0</p>
                                <p>PHP Pesos</p>
                            </div>
                        </div>

                        <!-- Product Stock Levels Cards -->
                        <?php foreach ($products as $key => $product): 
                            $stockStatus = getStockStatus($product['product_quantity']);
                        ?>
                        <div class="dashboard-card" data-product-id="<?php echo $key; ?>">
                            <div class="dashboard-card-header">
                                <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                            </div>
                            <div class="dashboard-card-body">
                                <i class="fas fa-box icon text-primary"></i>
                                <p class="stock-level"><?php echo $product['product_quantity']; ?></p>
                                <p>In Stock</p>
                                <div class="stock-status stock-<?php echo $stockStatus[1]; ?>">
                                    <?php echo $stockStatus[0]; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Daily Transactions Chart Card -->
                        <div class="dashboard-card" style="grid-column: 1 / -1;">
                            <div class="dashboard-card-header">
                                <h4>Transaction History</h4>
                            </div>
                            <div class="dashboard-card-body">
                                <div class="time-range-selector">
                                    <label for="timeRange">Select Time Range:</label>
                                    <select id="timeRange" onchange="updateChart()">
                                        <option value="7">Past Week</option>
                                        <option value="30">Past Month</option>
                                        <option value="90">Past 3 Months</option>
                                    </select>
                                </div>
                                <div class="chart-container">
                                    <canvas id="transactionsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
    <script>
        // Toggle sidebar for small screens
        const menuBarIcon = document.getElementById('menu-bar-icon');
        const sidebar = document.getElementById('sidebar');
        
        menuBarIcon.addEventListener('click', () => {
            sidebar.classList.toggle('active'); 
        });

        let transactionsChart;

        function initChart(labels, data) {
            if (!document.getElementById('transactionsChart')) {
                console.error('transactionsChart element not found');
                return;
            }

            const ctx = document.getElementById('transactionsChart').getContext('2d');
            transactionsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Product 1 (Napkins)',
                            data: data['Product 1'],
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        },
                        {
                            label: 'Product 2 (Wipes)',
                            data: data['Product 2'],
                            borderColor: 'rgb(255, 99, 132)',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Number of Transactions'
                            }
                        },
                        x: {
                            ticks: {
                                callback: function(value, index, values) {
                                    return new Date(this.getLabelForValue(value)).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                                }
                            },
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y;
                                }
                            }
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: 'Transaction History',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
        }

        function updateChart() {
            const timeRange = document.getElementById('timeRange').value;
            
            $.ajax({
                url: 'get_transactions.php',
                type: 'GET',
                data: { days: timeRange },
                dataType: 'json',
                success: function(response) {
                    transactionsChart.data.labels = response.labels;
                    transactionsChart.data.datasets[0].data = response.data['Product 1'];
                    transactionsChart.data.datasets[1].data = response.data['Product 2'];
                    transactionsChart.options.plugins.title.text = `Transaction History - Past ${timeRange} Days`;
                    transactionsChart.update();
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching transaction data:', error);
                }
            });
        }

        // Initial chart setup
        initChart(<?php echo json_encode(array_keys($dailyTransactions)); ?>, 
                  <?php echo json_encode($dailyTransactions); ?>);

        // Your web app's Firebase configuration
        var firebaseConfig = {
            apiKey: "AIzaSyD_5JkJaZr60O2FZ80H84HL9u6lAjgrZWI",
            databaseURL: "https://dbvending-1b336-default-rtdb.firebaseio.com",
            projectId: "dbvending-1b336",
        };
        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);

        let lastKnownStatus = 'Connecting';
        let lastPresenceValue = 0;
        let presenceChangeCount = 0;

        function checkESP32Presence() {
            return new Promise((resolve, reject) => {
                const presenceRef = firebase.database().ref('esp32_presence');
                let checkCount = 0;
                presenceChangeCount = 0; // Reset the count at the start of each check
                
                const intervalId = setInterval(() => {
                    presenceRef.once('value', (snapshot) => {
                        const currentPresence = snapshot.val();
                        
                        if (currentPresence !== lastPresenceValue) {
                            console.log('ESP32 presence changed:', currentPresence);
                            lastPresenceValue = currentPresence;
                            presenceChangeCount++;
                            
                            // If we were disconnected and received at least one presence update, show "Connecting"
                            if (lastKnownStatus === 'Disconnected' && presenceChangeCount === 1) {
                                updateConnectionUI('Connecting');
                            }
                        }
                        
                        checkCount++;
                        if (checkCount >= 3) {  // Check for 9 seconds (3 * 3 seconds)
                            clearInterval(intervalId);
                            resolve(presenceChangeCount >= 2);  // At least 2 changes in 9 seconds
                        }
                    });
                }, 3000);  // Check every 3 seconds
            });
        }

        function updateConnectionStatus() {
            // Immediately show last known status
            updateConnectionUI(lastKnownStatus);

            checkESP32Presence().then(isConnected => {
                if (isConnected) {
                    lastKnownStatus = 'Connected';
                } else {
                    lastKnownStatus = 'Disconnected';
                }
                updateConnectionUI(lastKnownStatus);
            });
        }

        function updateConnectionUI(status) {
            const connectionIcon = document.getElementById('connectionIcon');
            const connectionStatus = document.getElementById('connectionStatus');
            const troubleshootButton = document.getElementById('troubleshootButton');

            switch(status) {
                case 'Connected':
                    connectionIcon.className = 'fas fa-globe icon text-success';
                    connectionStatus.textContent = 'Connected';
                    troubleshootButton.style.display = 'none';
                    break;
                case 'Disconnected':
                    connectionIcon.className = 'fas fa-globe icon text-danger';
                    connectionStatus.textContent = 'Disconnected';
                    troubleshootButton.style.display = 'block';
                    break;
                case 'Connecting':
                    connectionIcon.className = 'fas fa-globe icon text-warning';
                    connectionStatus.textContent = 'Connecting...';
                    troubleshootButton.style.display = 'none';
                    break;
                default:
                    connectionIcon.className = 'fas fa-globe icon text-warning';
                    connectionStatus.textContent = 'Unknown';
                    troubleshootButton.style.display = 'none';
            }
        }

        function showTroubleshootModal() {
            $('#troubleshootModal').modal('show');
        }

        // Try to retrieve last known status from localStorage
        lastKnownStatus = localStorage.getItem('lastKnownStatus') || 'Connecting';

        // Initial update
        updateConnectionUI(lastKnownStatus);
        updateConnectionStatus();

        // Check connection status every 10 seconds
        setInterval(updateConnectionStatus, 10000);

        // Save last known status before unloading the page
        window.addEventListener('beforeunload', () => {
            localStorage.setItem('lastKnownStatus', lastKnownStatus);
        });

        // Modify this part of the existing DOMContentLoaded event listener
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const userRole = sidebar.dataset.userRole;
            const userManagementLink = document.getElementById('userManagementLink');
            const activityLogLink = document.getElementById('activityLogLink'); // Add this line

            function restrictAccess(event, linkName) {
                if (userRole !== 'admin') {
                    event.preventDefault();
                    alert(`You don't have permission to access ${linkName}.`);
                }
            }

            // Add this new event listener for Activity Log
            activityLogLink.addEventListener('click', function(event) {
                restrictAccess(event, 'Activity Log');
            });
        });

        // Function to update product stock levels
        function updateProductStock(snapshot) {
            const products = snapshot.val();
            for (const [key, product] of Object.entries(products)) {
                const cardBody = document.querySelector(`.dashboard-card[data-product-id="${key}"] .dashboard-card-body`);
                if (cardBody) {
                    const stockLevel = cardBody.querySelector('.stock-level');
                    const stockStatus = cardBody.querySelector('.stock-status');
                    
                    stockLevel.textContent = product.product_quantity;
                    
                    const [status, className] = getStockStatus(product.product_quantity);
                    stockStatus.textContent = status;
                    stockStatus.className = `stock-status stock-${className}`;
                }
            }
        }

        // Function to get stock status (same as PHP version)
        function getStockStatus(quantity) {
            if (quantity >= 0 && quantity <= 2) {
                return ['Critical Stock', 'danger'];
            } else if (quantity >= 3 && quantity <= 5) {
                return ['Low Stock', 'warning'];
            } else {
                return ['Available', 'success'];
            }
        }

        // Listen for changes in product stock
        const productsRef = firebase.database().ref('tables/products');
        productsRef.on('value', updateProductStock);

        // Function to update transaction chart and total income
        function updateTransactionChart(snapshot) {
            const transactions = snapshot.val() || {};
            const timeRange = document.getElementById('timeRange').value;
            const endDate = new Date();
            const startDate = new Date(endDate);
            startDate.setDate(startDate.getDate() - parseInt(timeRange));

            const dailyTransactions = {
                'Product 1': {},
                'Product 2': {}
            };

            for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                const dateString = d.toISOString().split('T')[0];
                dailyTransactions['Product 1'][dateString] = 0;
                dailyTransactions['Product 2'][dateString] = 0;
            }

            let totalIncome = 0;

            Object.values(transactions).forEach(transaction => {
                const transactionDate = transaction.date;
                const productIdentity = transaction.product_identity;
                if (transactionDate in dailyTransactions[productIdentity]) {
                    dailyTransactions[productIdentity][transactionDate]++;
                }
                totalIncome += parseFloat(transaction.amount) || 0;
            });

            const labels = Object.keys(dailyTransactions['Product 1']);
            const data = {
                'Product 1': Object.values(dailyTransactions['Product 1']),
                'Product 2': Object.values(dailyTransactions['Product 2'])
            };

            if (transactionsChart) {
                transactionsChart.data.labels = labels;
                transactionsChart.data.datasets[0].data = data['Product 1'];
                transactionsChart.data.datasets[1].data = data['Product 2'];
                transactionsChart.options.plugins.title.text = `Transaction History - Past ${timeRange} Days`;
                transactionsChart.update();
            } else {
                console.error('transactionsChart is not initialized');
            }

            document.getElementById('totalIncome').textContent = totalIncome.toFixed(2);
        }

        // Keep the existing listener
        const transactionsRef = firebase.database().ref('tables/transactions');
        transactionsRef.on('value', updateTransactionChart);
    </script>

    <?php include 'edit_profile_modal.php'; ?>
    <script src="edit_profile.js"></script>
    <script src="firebase-init.js"></script>
    <script src="reset_listener.js"></script>

    <!-- Add this modal at the end of the body, just before the closing </body> tag -->
    <div class="modal fade" id="troubleshootModal" tabindex="-1" role="dialog" aria-labelledby="troubleshootModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="troubleshootModalLabel">Troubleshooting Steps</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6>Please follow these steps to reconnect your device:</h6>
                    <ol>
                        <li>Ensure the device is powered on.</li>
                        <li>Verify your internet connection is active.</li>
                        <li>Confirm the device is connected to the internet.</li>
                        <li>If issues persist, press the reset button on the device.</li>
                    </ol>
                    <h6>To access the device's Wi-Fi manager:</h6>
                    <p>Connect to the following network:</p>
                    <ul>
                        <li><strong>Wi-Fi Name:</strong> HygienexCare_AP</li>
                        <li><strong>Wi-Fi Password:</strong> password</li>
                    </ul>
                    <p>Once connected, you can access the Wi-Fi manager to configure your device's network settings.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the password prompt modal -->
    <?php include 'promptpass.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const userRole = sidebar.dataset.userRole;
        const userManagementLink = document.getElementById('userManagementLink');
    
        userManagementLink.addEventListener('click', function(event) {
            event.preventDefault();
            if (userRole !== 'admin') {
                alert("You don't have permission to access User Management.");
            } else {
                $('#passwordPromptModal').modal('show');
            }
        });
    
        $('#confirmPasswordPrompt').click(function() {
            const password = $('#adminPasswordPrompt').val();
            verifyAdminPassword(password);
        });
    
        function verifyAdminPassword(password) {
            $.ajax({
                url: 'verify_admin_password.php',
                type: 'POST',
                data: { password: password },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#passwordPromptModal').modal('hide');
                        window.location.href = 'user.php';
                    } else {
                        alert('Incorrect password. Please try again.');
                    }
                },
                error: function() {
                    alert('An error occurred while verifying the password.');
                }
            });
        }
    });
    </script>

</body>
</html>
