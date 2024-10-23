<?php
session_start();

require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';
require_once __DIR__ . '/functions.php';  // Add this line

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

// Fetch initial transaction logs
$transactionLogsRef = $database->getReference("tables/transactions");
$snapshot = $transactionLogsRef->getSnapshot();
$initialTransactionLogs = $snapshot->getValue() ?: [];

// Instead, use all logs
$filteredLogs = $initialTransactionLogs;

// Sort filtered logs
usort($filteredLogs, function($a, $b) {
return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
});

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

// Handle form submission for resetting transactions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_transactions'])) {
    try {
        // Delete all transactions
        $transactionsRef = $database->getReference('tables/transactions');
        $transactionsRef->remove();

        // Log the action using the addLogEntry function
        addLogEntry($_SESSION['user_id'], 'Reset Transactions', 'All transaction logs were cleared');

        echo json_encode(['success' => true, 'message' => 'All transactions have been reset and the action has been logged.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HygienexCare</title>
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
box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
color: #369c43 ;
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
    color: #369c43 ; /* Use your preferred color, this is a blue shade */
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
color: #369c43 ;
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
}

/* kahit wala na to*/

/* Main Content Styles */
.content {
flex: 1;
padding: 20px;
margin-left: 250px;
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
background-color: #369c43 ;
border-color: #369c43 ;
transition: background-color 0.3s ease;
}

.btn-primary:hover {
background-color: #0056b3;
/* Darker blue on hover */
}

.btn-success {
background-color: #28a745;
border-color: #28a745;
transition: background-color 0.3s ease;
}

.btn-success:hover {
background-color: #218838;
/* Darker green on hover */
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
background-color: #369c43 ;
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

.recent-transaction {
animation: blinkHighlight 3s ease-in-out;
}

@keyframes blinkHighlight {
0%, 100% { background-color: transparent; }
50% { background-color: #fff998; }
}

.card-body {
    padding: 20px;
}

.table-responsive {
    margin-bottom: 20px;
}

#resetButton {
    margin-right: auto;
}

.pagination {
    margin-top: 0;
    margin-left: auto;
}

@media (max-width: 767px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: flex-start;
    }

    #resetButton, .pagination {
        margin-top: 10px;
    }

    .pagination {
        align-self: flex-end;
    }
}

.btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
    margin-left: 5px;
}

.btn-outline-secondary:hover {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.pagination-container {
    display: flex;
    justify-content: flex-end;
    margin-top: 15px;
}

.pagination {
    display: flex;
    align-items: center;
}

.pagination button {
    background-color: #369c43;
    color: white;
    border: none;
    padding: 5px 10px;
    margin: 0 5px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.pagination button:hover {
    background-color: #2a7d35;
}

.pagination button:disabled {
    background-color: #88c794;
    cursor: not-allowed;
}

.pagination span {
    margin: 0 10px;
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
<div class="main-container">
<!-- Sidebar -->
        <nav class="sidebar" id="sidebar" data-user-role="<?php echo $userRole; ?>">
            <a href="dash.php" class="menu-item"><i class="fas fa-box"></i> System Status</a>
            <a href="prod.php" class="menu-item"><i class="fas fa-shopping-bag"></i> Product Inventory</a>
            <a href="trans.php" class="menu-item"><i class="fas fa-tag"></i> Transaction History</a>
            <a href="act.php" class="menu-item" id="activityLogLink"><i class="fas fa-history"></i> Activity Log</a>
            <a href="user.php" class="menu-item" id="userManagementLink"><i class="fas fa-cog"></i> User Management</a>
        </nav>  
<!-- Main Content -->
<div class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>Transaction Log</h2>
            <div>
                <button id="exportPDF" class="btn btn-sm btn-outline-secondary">Export PDF</button>
            </div>
        </div>
        <div class="card-body">
            <!-- Add date filter form -->
            <form id="dateFilterForm" class="mb-3">
                <div class="form-row">
                    <div class="col-md-4 mb-3">
                        <label for="startDate">Start Date:</label>
                        <input type="date" id="startDate" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="endDate">End Date:</label>
                        <input type="date" id="endDate" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <button type="button" id="resetFilter" class="btn btn-secondary ml-2">Reset</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="transactionTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Amount</th>
                            <th>Time</th>
                            <th>Date</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <form id="resetForm" method="POST" action="trans.php" style="display: inline;">
                    <input type="hidden" name="reset_transactions" value="1">
                    <button type="button" id="resetButton" class="btn btn-danger" data-role="<?php echo $userRole; ?>">Reset Transactions</button>
                </form>
                <div class="pagination-container"></div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- Firebase App (the core Firebase SDK) is always required and must be listed first -->
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>
// Firebase configuration
var firebaseConfig = {
apiKey: "AIzaSyD_5JkJaZr60O2FZ80H84HL9u6lAjgrZWI",
databaseURL: "https://dbvending-1b336-default-rtdb.firebaseio.com",
projectId: "dbvending-1b336",
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
var database = firebase.database();
var productsRef = database.ref('tables/products');
var transactionsRef = database.ref('tables/transactions');

// Check connection to Firebase
database.ref('.info/connected').on('value', function(snapshot) {
console.log('Connected to Firebase:', snapshot.val());
});

// Function to format date
function formatDate(dateString) {
const date = new Date(dateString);
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
return `${months[date.getMonth()]}-${String(date.getDate()).padStart(2, '0')}-${date.getFullYear()}`;
}

// Function to format time
function formatTime(timeString) {
const [hours, minutes, seconds] = timeString.split(':');
const ampm = hours >= 12 ? 'PM' : 'AM';
const formattedHours = hours % 12 || 12;
return `${formattedHours}:${minutes}:${seconds} ${ampm}`;
}

// Function to get current date and time
function getCurrentDateTime() {
const now = new Date();
const date = now.toISOString().split('T')[0]; // Store in ISO format for consistency
const time = now.toTimeString().split(' ')[0];
return { date, time };
}

var mostRecentTransactionKey = null;
let allTransactions = [];
const transactionsPerPage = 10;
let currentPage = 1;

let startDate = null;
let endDate = null;
let earliestTransactionDate = null;

function loadExistingTransactions() {
    transactionsRef.once('value')
    .then(function(snapshot) {
        var transactions = snapshot.val();
        if (transactions) {
            allTransactions = Object.entries(transactions).map(([key, value]) => ({
                ...value,
                key
            })).sort((a, b) => {
                var dateA = new Date(a.date + 'T' + a.time);
                var dateB = new Date(b.date + 'T' + b.time);
                return dateB - dateA;
            });

            // Find the earliest transaction date
            earliestTransactionDate = allTransactions[allTransactions.length - 1].date;

            // Set the min attribute for date inputs
            $('#startDate, #endDate').attr('min', earliestTransactionDate);

            // Set the max attribute to today's date
            const today = new Date().toISOString().split('T')[0];
            $('#startDate, #endDate').attr('max', today);

            // Apply date filter if set
            if (startDate && endDate) {
                allTransactions = allTransactions.filter(transaction => {
                    const transactionDate = new Date(transaction.date);
                    return transactionDate >= startDate && transactionDate <= endDate;
                });
            }

            mostRecentTransactionKey = allTransactions[0]?.key || null;
            updateTransactionTable();
        } else {
            console.log('No existing transactions found');
            allTransactions = [];
            updateUIForEmptyTransactions();
        }
    })
    .catch(function(error) {
        console.error('Error loading existing transactions:', error);
    });
}

function updateTransactionTable() {
    var tableBody = $('#transactionTable tbody');
    tableBody.empty();

    if (allTransactions && allTransactions.length > 0) {
        const startIndex = (currentPage - 1) * transactionsPerPage;
        const endIndex = startIndex + transactionsPerPage;
        const transactionsToShow = allTransactions.slice(startIndex, endIndex);

        transactionsToShow.forEach(function(transaction) {
            var row = `
                <tr data-key="${transaction.key}">
                    <td>${transaction.product_name}</td>
                    <td>₱${parseFloat(transaction.amount).toFixed(2)}</td>
                    <td>${formatTime(transaction.time)}</td>
                    <td>${formatDate(transaction.date)}</td>
                    <td>${transaction.remaining}</td>
                </tr>
            `;
            tableBody.append(row);
        });

        updatePagination();
    } else {
        tableBody.append('<tr><td colspan="5" class="text-center">No transactions available</td></tr>');
        $('.pagination-container').hide();
    }

    updateUIForEmptyTransactions();
}

function updatePagination() {
    const totalPages = Math.ceil(allTransactions.length / transactionsPerPage);
    const paginationContainer = $('.pagination-container');
    
    if (allTransactions.length <= transactionsPerPage) {
        paginationContainer.hide();
        return;
    }

    paginationContainer.show();
    paginationContainer.empty();

    const pagination = $('<div class="pagination"></div>');
    
    // Previous button
    const prevButton = $(`<button ${currentPage === 1 ? 'disabled' : ''}>
        <i class="fas fa-arrow-left"></i>
    </button>`);
    prevButton.on('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updateTransactionTable();
        }
    });
    pagination.append(prevButton);

    // Page indicator
    pagination.append(`<span>Page ${currentPage} of ${totalPages}</span>`);

    // Next button
    const nextButton = $(`<button ${currentPage === totalPages ? 'disabled' : ''}>
        <i class="fas fa-arrow-right"></i>
    </button>`);
    nextButton.on('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            updateTransactionTable();
        }
    });
    pagination.append(nextButton);

    paginationContainer.append(pagination);
}

function listenForNewTransactions() {
    transactionsRef.on('child_added', function(snapshot) {
        var newTransaction = snapshot.val();
        newTransaction.key = snapshot.key;
        
        if (newTransaction.key !== mostRecentTransactionKey) {
            allTransactions.unshift(newTransaction);
            mostRecentTransactionKey = newTransaction.key;
            updateTransactionTable();
        }
    });
}

function listenForTransactionChanges() {
    transactionsRef.on('child_changed', function(snapshot) {
        var updatedTransaction = snapshot.val();
        updatedTransaction.key = snapshot.key;
        
        var index = allTransactions.findIndex(t => t.key === updatedTransaction.key);
        if (index !== -1) {
            allTransactions[index] = updatedTransaction;
            updateTransactionTable();
        }
    });
}

function listenForTransactionDeletions() {
    transactionsRef.on('child_removed', function(snapshot) {
        var deletedKey = snapshot.key;
        allTransactions = allTransactions.filter(t => t.key !== deletedKey);
        updateTransactionTable();
    });
}

$(document).ready(function() {
    loadExistingTransactions();
    listenForNewTransactions();
    listenForTransactionChanges();
    listenForTransactionDeletions();
});

// Reset button functionality
$('#resetButton').click(function() {
    var userRole = $(this).data('role');
    
    if (userRole !== 'admin') {
        alert("You don't have permission to reset transactions.");
        return;
    }
    
    if (confirm('Are you sure you want to delete all transactions? This action cannot be undone.')) {
        $.ajax({
            url: 'trans.php',
            type: 'POST',
            data: { reset_transactions: 1 },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    allTransactions = [];
                    loadTransactions(1);
                    updateUIForEmptyTransactions();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while resetting transactions.');
            }
        });
    }
});

// Function to calculate summary data
function calculateSummary(transactions) {
    let totalIncome = 0;
    let productCounts = {};
    transactions.forEach(t => {
        totalIncome += parseFloat(t.amount);
        productCounts[t.product_name] = (productCounts[t.product_name] || 0) + 1;
    });

    // Sort products by total sold (descending order)
    let sortedProducts = Object.entries(productCounts)
        .sort((a, b) => b[1] - a[1])
        .map(([name, count]) => `${name}: ${count}`);

    return {
        totalTransactions: transactions.length,
        totalIncome: totalIncome.toFixed(2),
        productsSold: sortedProducts
    };
}

// Replace the existing exportPDF function with this new version
function showExportDialog() {
    const dialog = $(`
        <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exportModalLabel">Export PDF</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="exportOption" id="exportAll" value="all" checked>
                            <label class="form-check-label" for="exportAll">
                                Export all transactions
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="exportOption" id="exportFiltered" value="filtered">
                            <label class="form-check-label" for="exportFiltered">
                                Export filtered transactions
                            </label>
                        </div>
                        <div id="dateFilterFields" style="display: none;">
                            <div class="form-group">
                                <label for="exportStartDate">Start Date:</label>
                                <input type="date" class="form-control" id="exportStartDate">
                            </div>
                            <div class="form-group">
                                <label for="exportEndDate">End Date:</label>
                                <input type="date" class="form-control" id="exportEndDate">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmExport">Export</button>
                    </div>
                </div>
            </div>
        </div>
    `);

    $('body').append(dialog);
    $('#exportModal').modal('show');

    // Set min and max dates for the export date inputs
    $('#exportStartDate, #exportEndDate').attr('min', earliestTransactionDate);
    $('#exportStartDate, #exportEndDate').attr('max', new Date().toISOString().split('T')[0]);

    // Show/hide date filter fields based on radio button selection
    $('input[name="exportOption"]').change(function() {
        if ($(this).val() === 'filtered') {
            $('#dateFilterFields').show();
        } else {
            $('#dateFilterFields').hide();
        }
    });

    // Handle export confirmation
    $('#confirmExport').click(function() {
        const exportOption = $('input[name="exportOption"]:checked').val();
        let transactionsToExport = allTransactions;

        if (exportOption === 'filtered') {
            const exportStartDate = new Date($('#exportStartDate').val());
            const exportEndDate = new Date($('#exportEndDate').val());
            exportEndDate.setHours(23, 59, 59, 999);

            transactionsToExport = allTransactions.filter(transaction => {
                const transactionDate = new Date(transaction.date);
                return transactionDate >= exportStartDate && transactionDate <= exportEndDate;
            });
        }

        exportPDF(transactionsToExport);
        $('#exportModal').modal('hide');
    });

    // Clean up the modal when it's hidden
    $('#exportModal').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}

// Modify the exportPDF function to accept transactions as a parameter
function exportPDF(transactions) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const summary = calculateSummary(transactions);
    const pageWidth = doc.internal.pageSize.width;
    const pageHeight = doc.internal.pageSize.height;

    // Colors
    const primaryColor = [54, 156, 67];  // #369c43
    const secondaryColor = [240, 240, 240];  // #f0f0f0

    // Add background color
    doc.setFillColor(...secondaryColor);
    doc.rect(0, 0, pageWidth, pageHeight, 'F');

    // Header
    doc.setFillColor(...primaryColor);
    doc.rect(0, 0, pageWidth, 40, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(24);
    doc.setFont("helvetica", "bold");
    doc.text("HygienexCare", 20, 25);
    doc.setFontSize(14);
    doc.setFont("helvetica", "normal");
    doc.text("Transaction Log Report", pageWidth - 20, 25, { align: "right" });

    // Summary section
    doc.setTextColor(0, 0, 0);
    doc.setFillColor(220, 220, 220);
    doc.roundedRect(20, 50, pageWidth - 40, 80, 3, 3, 'F');
    doc.setFontSize(16);
    doc.setFont("helvetica", "bold");
    doc.text("Summary", 25, 60);
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text(`Total Transactions: ${summary.totalTransactions}`, 25, 75);
    doc.text(`Total Income: ₱${summary.totalIncome}`, 25, 85);
    
    // Products Sold
    doc.text("Products Sold:", 25, 95);
    summary.productsSold.forEach((product, index) => {
        doc.text(product, 35, 105 + (index * 10));
    });

    // Date and Time
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 140);

    // Table
    doc.autoTable({
        head: [['ID', 'Name', 'Amount', 'Time', 'Date', 'Remaining']],
        body: transactions.map((t, index) => [
            index + 1,
            t.product_name, 
            `₱${parseFloat(t.amount).toFixed(2)}`, 
            formatTime(t.time), 
            formatDate(t.date), 
            t.remaining
        ]),
        startY: 150,
        styles: { fillColor: [255, 255, 255] },
        columnStyles: {
            0: { cellWidth: 20 },
            1: { cellWidth: 'auto' },
            2: { cellWidth: 30 },
            3: { cellWidth: 30 },
            4: { cellWidth: 30 },
            5: { cellWidth: 30 }
        },
        headStyles: { fillColor: primaryColor, textColor: [255, 255, 255] },
        alternateRowStyles: { fillColor: [245, 245, 245] }
    });

    // Footer and Watermark
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        
        // Footer
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text(`Page ${i} of ${pageCount}`, pageWidth - 20, pageHeight - 10, { align: "right" });
        
        // Watermark
        doc.setGState(new doc.GState({opacity: 0.1}));
        doc.setFontSize(80);
        doc.setTextColor(...primaryColor);

        // Calculate text dimensions
        const watermarkText = "HygienexCare";
        const textWidth = doc.getStringUnitWidth(watermarkText) * doc.internal.getFontSize() / doc.internal.scaleFactor;
        const textHeight = doc.internal.getFontSize() / doc.internal.scaleFactor;

        // Calculate center position
        const centerX = pageWidth / 2;
        const centerY = pageHeight / 2;

        // Calculate the position for rotated text
        const angle = 45 * Math.PI / 180; // 45 degrees in radians
        const cos = Math.cos(angle);
        const sin = Math.sin(angle);

        // Adjust these values to fine-tune the position
        const offsetX = 90; // Positive moves right, negative moves left
        const offsetY = 0; // Positive moves down, negative moves up

        const x = centerX - (textWidth / 2 * cos) + offsetX;
        const y = centerY + (textWidth / 2 * sin) + offsetY;

        // Draw the rotated text
        doc.text(watermarkText, x, y, {
            angle: 45,
            align: 'center',
            baseline: 'middle'
        });

        doc.setGState(new doc.GState({opacity: 1}));
    }

    doc.save("HygienexCare_Transaction_Log.pdf");
}

// Update the event listener for the export button
document.getElementById('exportPDF').addEventListener('click', showExportDialog);

function updateUIForEmptyTransactions() {
    transactionsRef.once('value')
    .then(function(snapshot) {
        if (snapshot.exists()) {
            $('#resetButton').prop('disabled', false);
            $('#exportPDF').prop('disabled', false);
            if ($('#transactionTable tbody tr').length === 0) {
                loadExistingTransactions(); // Reload transactions if table is empty but Firebase has data
            }
        } else {
            $('#transactionTable tbody').html('<tr><td colspan="5" class="text-center">No transactions available</td></tr>');
            $('#resetButton').prop('disabled', true);
            $('#exportPDF').prop('disabled', true);
        }
    })
    .catch(function(error) {
        console.error('Error checking transactions:', error);
    });
}

function listenForChanges() {
    transactionsRef.on('value', function(snapshot) {
        console.log('Changes detected in transactions');
        loadExistingTransactions();
    });
}

// Call this function when the page loads
$(document).ready(function() {
    loadExistingTransactions();
    listenForChanges();
});

// Add this to the existing <script> tag, after line 657
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

// Add event listeners for date filter form
$(document).ready(function() {
    $('#dateFilterForm').on('submit', function(e) {
        e.preventDefault();
        startDate = new Date($('#startDate').val());
        endDate = new Date($('#endDate').val());
        endDate.setHours(23, 59, 59, 999); // Set to end of day
        loadExistingTransactions();
    });

    $('#resetFilter').on('click', function() {
        $('#startDate').val('');
        $('#endDate').val('');
        startDate = null;
        endDate = null;
        loadExistingTransactions();
    });

    // Add event listener for start date change
    $('#startDate').on('change', function() {
        $('#endDate').attr('min', $(this).val());
    });

    // Add event listener for end date change
    $('#endDate').on('change', function() {
        $('#startDate').attr('max', $(this).val());
    });

    loadExistingTransactions();
    listenForChanges();
});
</script>

<?php include 'edit_profile_modal.php'; ?>
<script src="edit_profile.js"></script>

<!-- Add the reset_listener.js script -->
<script src="reset_listener.js"></script>
<script src="firebase-init.js"></script>
<script src="reset_listener.js"></script>
</body>
</html>
