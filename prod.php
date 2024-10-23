<?php

session_start();

require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';
require_once __DIR__ . '/functions.php';

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

// Fetch products
$productsRef = $database->getReference('tables/products');
$snapshot = $productsRef->getSnapshot();
$products = $snapshot->getValue() ?: [];

// Fetch the current user's role
$userRole = '';
$userId = $_SESSION['user_id'];
$usersRef = $database->getReference('tables/user');
$snapshot = $usersRef->getSnapshot();
$users = $snapshot->getValue();

foreach ($users as $key => $user) {
    if ($user['user_id'] === $userId) {
        $userRole = $user['u_role'];
        break;
    }
}

// Function to determine product status
function determineProductStatus($quantity)
{
    $quantity = intval($quantity); // Convert to integer, empty or non-numeric values become 0

    if ($quantity <= 2) {
        return "CRITICAL";
    } elseif ($quantity <= 5) {
        return "LOW STOCK";
    } else {
        return "AVAILABLE";
    }
}

// Handle form submission for editing a product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    try {
        $productId = $_POST['product_id'];
        $productName = $_POST['product_name'];
        $productQuantity = max(intval($_POST['product_quantity']), 0); // Ensure non-negative integer
        $productPrice = (float)$_POST['product_price'];
        $productIdentity = $_POST['product_identity'];

        if ($productPrice < 0) {
            throw new Exception('Price cannot be negative.');
        }

        $productRef = $database->getReference('tables/products/' . $productId);
        $oldProduct = $productRef->getValue();

        $updates = [];
        $changes = [];

        if ($oldProduct['product_name'] !== $productName) {
            $updates['product_name'] = $productName;
            $changes[] = "**NAME** updated from '{$oldProduct['product_name']}' to '{$productName}'";
        }

        if ((int)$oldProduct['product_quantity'] !== $productQuantity) {
            $updates['product_quantity'] = $productQuantity;
            $changes[] = "**QTY** adjusted from {$oldProduct['product_quantity']} to {$productQuantity}";
        }

        if (abs((float)$oldProduct['product_price'] - $productPrice) > 0.001) {
            $updates['product_price'] = $productPrice;
            $changes[] = "**PRICE** modified from {$oldProduct['product_price']} to {$productPrice}";
        }

        $updates['product_status'] = determineProductStatus($productQuantity);

        if (!empty($_FILES["product_image"]["name"])) {
            $fileName = handleFileUpload($_FILES["product_image"]);
            if ($fileName) {
                $updates['product_image'] = $fileName;
                $changes[] = "**IMAGE** updated";
            }
        }

        if (!empty($updates)) {
            $productRef->update($updates);
            $changeDetails = implode(". ", $changes);
            $timestamp = date('Y-m-d H:i:s'); // Current date and time in GMT+8
            $logMessage = "**{$productIdentity}**: {$changeDetails}";
            addLogEntry($_SESSION['user_id'], 'Updated Product', $logMessage);
        } else {
            $timestamp = date('Y-m-d H:i:s'); // Current date and time in GMT+8
            $logMessage = "**{$productIdentity}**: No changes made";
            addLogEntry($_SESSION['user_id'], 'Product Update Attempted', $logMessage, $timestamp);
        }

        echo "<script>alert('Product updated successfully.'); window.location.href='prod.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Function to determine the badge class
function getBadgeClass($status)
{
    switch ($status) {
        case 'CRITICAL':
            return 'badge-danger';
        case 'LOW STOCK':
            return 'badge-warning';
        case 'AVAILABLE':
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HygienexCare</title>
    <link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
  <!--  <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="fonts/Linearicons-Free-v1.0.0/icon-font.min.css">
    <link rel="stylesheet" type="text/css" href="css/util.css">
    <link rel="stylesheet" type="text/css" href="css/main1.css">-->
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Bootstrap CSS for grid system -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            color: #343a40;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        

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
            box-sizing: border-box;
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
            transition: background-color 0.3s ease;
        }

        .sidebar a:hover {
            background-color: #d6d8db;
        }

        .sidebar i {
            margin-right: 10px;
            font-size: 18px; 
        }

        .content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
            margin-top: 80px;
        }

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
                margin-top: 60px; 
            } 
        }/* kahit wala na to*/
        
        /* Main Content Styles */
        .content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
            margin-top: 80px;
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
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3; /* Darker blue on hover */
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            transition: background-color 0.3s ease;
        }

        .btn-success:hover {
            background-color: #218838; /* Darker green on hover */
        }
        /* Visible only on small screens */
        .menu-bar-icon {
            display: none; 
        }

        /* Responsive Styles */
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
            }
        }

        .table-responsive {
            margin-top: 15px;
        }

        .table thead th {
            background-color: #369c43;
            color: #ffffff;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            padding: 12px;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 12px;
        }

        .table tbody td img {
            max-width: 100px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .delete-btn {
            color: red;
            cursor: pointer;
        }

        .delete-btn:hover {
            color: darkred;
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
        }

        .search-bar-container {
            position: relative;
            margin-bottom: 15px;
        }

        .search-bar-container input[type="text"] {
            padding-right: 40px;
        }
    </style>
    
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="logo">HygienexCare</div>
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


    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar" data-user-role="<?php echo $userRole; ?>">
            <a href="dash.php" class="menu-item"><i class="fas fa-box"></i> System Status</a>
            <a href="prod.php" class="menu-item"><i class="fas fa-shopping-bag"></i> Product Inventory</a>
            <a href="trans.php" class="menu-item"><i class="fas fa-tag"></i> Transaction History</a>
            <a href="act.php" class="menu-item" id="activityLogLink"><i class="fas fa-history"></i> Activity Log</a>
            <a href="user.php" class="menu-item" id="userManagementLink"><i class="fas fa-cog"></i> User Management</a>
        </nav>
        <!-- Content -->
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h2>Product Inventory</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product Name</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Table body will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="prod.php" enctype="multipart/form-data">
                        <input type="hidden" name="product_id" id="editProductId">
                        <input type="hidden" name="product_identity" id="editProductIdentity">
                        <div class="form-group">
                            <label for="edit_product_image">Product Image</label>
                            <input type="file" class="form-control-file" name="product_image" id="editProductImage">
                        </div>
                        <div class="form-group">
                            <label for="edit_product_name">Product Name</label>
                            <input type="text" class="form-control" name="product_name" id="editProductName" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_quantity">Quantity</label>
                            <input type="number" class="form-control" name="product_quantity" id="editProductQuantity" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_price">Price</label>
                            <input type="number" class="form-control" name="product_price" id="editProductPrice" step="0.01" required>
                        </div>
                        <button type="submit" name="edit_product" class="btn btn-warning">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Add this at the beginning of your script
        var userRole = '<?php echo $userRole; ?>';

        // Add this JavaScript function to determine product status
        function determineProductStatus(quantity) {
            quantity = parseInt(quantity) || 0; // Convert to integer, use 0 if NaN
            if (quantity <= 2) {
                return "CRITICAL";
            } else if (quantity <= 5) {
                return "LOW STOCK";
            } else {
                return "AVAILABLE";
            }
        }

        function openEditModal(productId, productName, productQuantity, productPrice, productImage, productIdentity) {
            $('#editProductId').val(productId);
            $('#editProductName').val(productName);
            $('#editProductQuantity').val(productQuantity);
            $('#editProductPrice').val(productPrice);
            $('#editProductIdentity').val(productIdentity);
            $('#editProductModal').modal('show');
        }

        // Toggle sidebar for small screens
        const menuBarIcon = document.getElementById('menu-bar-icon');
        const sidebar = document.getElementById('sidebar');
        
        menuBarIcon.addEventListener('click', () => {
            sidebar.classList.toggle('active'); 
        });

        // Your web app's Firebase configuration
        var firebaseConfig = {
            apiKey: "AIzaSyD_5JkJaZr60O2FZ80H84HL9u6lAjgrZWI",
            databaseURL: "https://dbvending-1b336-default-rtdb.firebaseio.com",
            projectId: "dbvending-1b336",
        };
        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);

        function updateProductTable(products) {
            console.log('Updating product table with:', products);
            var tableBody = $('.table tbody');
            tableBody.empty();

            if (!products) {
                console.log('No products to display');
                return;
            }

            $.each(products, function(productId, product) {
                if (product && product.product_name) {
                    var quantity = parseInt(product.product_quantity) || 0;
                    var status = determineProductStatus(quantity);
                    var displayQuantity = quantity > 0 ? quantity : ''; // Display nothing if quantity is 0
                    var row = `
                        <tr>
                            <td><img src="uploads/${product.product_image || ''}" alt="Product Image" style="width: 50px; height: 50px;"></td>
                            <td>${product.product_identity}: ${product.product_name}</td>
                            <td>${displayQuantity}</td>
                            <td>${product.product_price || ''}</td>
                            <td>
                                <span class="badge ${getBadgeClass(status)}">${status}</span>
                            </td>
                            <td>
                                <button class="btn btn-warning edit-btn" 
                                    data-id="${productId}" 
                                    data-name="${product.product_name}" 
                                    data-quantity="${quantity}" 
                                    data-price="${product.product_price || 0}" 
                                    data-image="${product.product_image || ''}"
                                    data-identity="${product.product_identity}"
                                    data-role="${userRole}">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                    `;
                    tableBody.append(row);
                }
            });
        }

        function getBadgeClass(status) {
            switch (status) {
                case 'CRITICAL': return 'badge-danger';
                case 'LOW STOCK': return 'badge-warning';
                case 'AVAILABLE': return 'badge-success';
                default: return 'badge-secondary';
            }
        }

        function fetchProductData() {
            $.ajax({
                url: 'fetch_products.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    console.log('Fetched data:', data);
                    updateProductTable(data);
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching product data:', error);
                }
            });
        }

        // Initial load of products
        $(document).ready(function() {
            console.log('Document ready, loading initial products');
            fetchProductData();
            
            // Set up auto-refresh every 5 seconds
            setInterval(fetchProductData, 5000);
        });

        // Listen for real-time updates
        var productsRef = firebase.database().ref('tables/products');
        productsRef.on('value', function(snapshot) {
            console.log('Firebase update received:', snapshot.val());
            var updatedProducts = snapshot.val();
            updateProductTable(updatedProducts);
        });

        // Use event delegation for edit buttons
        $(document).on('click', '.edit-btn', function() {
            var userRole = $(this).data('role');
            
            if (userRole !== 'admin') {
                alert("You don't have permission to edit products.");
                return;
            }
            
            var productId = $(this).data('id');
            var productName = $(this).data('name');
            var productQuantity = $(this).data('quantity');
            var productPrice = $(this).data('price');
            var productImage = $(this).data('image');
            var productIdentity = $(this).data('identity');
            openEditModal(productId, productName, productQuantity, productPrice, productImage, productIdentity);
        });

        // Modify the existing DOMContentLoaded event listener
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

            userManagementLink.addEventListener('click', function(event) {
                restrictAccess(event, 'User Management');
            });

            // Add this new event listener for Activity Log
            activityLogLink.addEventListener('click', function(event) {
                restrictAccess(event, 'Activity Log');
            });
        });
    </script>
    <!-- Add this line just before the closing </body> tag (you'll need to find the appropriate line number) -->
    <?php include 'edit_profile_modal.php'; ?>

    <!-- Add this line to include the edit_profile.js file (with the other script includes, typically near the end of the file) -->
    <script src="edit_profile.js"></script>
    <script src="reset_listener.js"></script>
</body>
</html>
