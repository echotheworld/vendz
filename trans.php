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

.pagination {
margin-top: 20px;
text-align: right;
}

.pagination button {
margin-left: 5px;
padding: 6px 12px;
background-color: #369c43 ;
color: white;
border: none;
border-radius: 4px;
cursor: pointer;
transition: background-color 0.3s;
}

.pagination button:hover {
background-color: #0056b3;
}

.pagination span {
margin-right: 10px;
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
            <a href="trans.php" class="menu-item"><i class="fas fa-tag"></i> Transaction Log</a>
            <a href="user.php" class="menu-item" id="userManagementLink"><i class="fas fa-cog"></i> User Management</a>
        </nav>
<!-- Main Content -->
<div class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>Transaction Log</h2>
            <div>
                <button id="exportCSV" class="btn btn-sm btn-outline-secondary">Export CSV</button>
                <button id="exportPDF" class="btn btn-sm btn-outline-secondary">Export PDF</button>
                <button id="exportExcel" class="btn btn-sm btn-outline-secondary">Export Excel</button>
            </div>
        </div>
        <div class="card-body">
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
                <button id="resetButton" class="btn btn-danger" data-role="<?php echo $userRole; ?>" <?php echo $userRole !== 'admin' ? 'disabled' : ''; ?>>Reset Transactions</button>
                <div id="pagination" class="pagination"></div>
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
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

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
var currentPage = 1;
var transactionsPerPage = 10;
var allTransactions = [];

function addTransactionToTable(transaction, prepend = false, isRecent = false) {
var tableBody = $('#transactionTable tbody');
var highlightClass = isRecent ? 'class="recent-transaction"' : '';
var newRow = `
<tr ${highlightClass} data-key="${transaction.key}">
<td>${transaction.product_name}</td>
<td>₱${parseFloat(transaction.amount).toFixed(2)}</td>
<td>${formatTime(transaction.time)}</td>
<td>${formatDate(transaction.date)}</td>
<td>${transaction.remaining}</td>
</tr>
`;
if (prepend) {
tableBody.prepend(newRow);
} else {
tableBody.append(newRow);
}

if (isRecent) {
setTimeout(() => {
$(`tr[data-key="${transaction.key}"]`).removeClass('recent-transaction');
}, 3000);
}
}

function loadTransactions(page) {
var start = (page - 1) * transactionsPerPage;
var end = start + transactionsPerPage;
var transactionsToShow = allTransactions.slice(start, end);

$('#transactionTable tbody').empty();
transactionsToShow.forEach((transaction, index) => {
addTransactionToTable(transaction, false, index === 0 && page === 1);
});

updatePagination();
console.log('Loaded transactions for page', page, 'Total transactions:', allTransactions.length);
}

function updatePagination() {
var totalPages = Math.ceil(allTransactions.length / transactionsPerPage);
var paginationHtml = '';

paginationHtml += `<span>Page ${currentPage} of ${totalPages}</span>`;

if (currentPage > 1) {
paginationHtml += `<button onclick="changePage(${currentPage - 1})">Previous</button>`;
}

if (currentPage < totalPages) {
paginationHtml += `<button onclick="changePage(${currentPage + 1})">Next</button>`;
}

$('#pagination').html(paginationHtml);
console.log('Pagination updated. Total pages:', totalPages, 'Current page:', currentPage);
}

function changePage(newPage) {
currentPage = newPage;
loadTransactions(currentPage);
}

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

            mostRecentTransactionKey = allTransactions[0]?.key || null;
            loadTransactions(1);
        } else {
            console.log('No existing transactions found');
        }
    })
    .catch(function(error) {
        console.error('Error loading existing transactions:', error);
    });
}

function listenForNewTransactions() {
    transactionsRef.on('child_added', function(snapshot) {
        var newTransaction = snapshot.val();
        newTransaction.key = snapshot.key;
        
        // Check if this transaction is already in our list
        if (!allTransactions.some(t => t.key === newTransaction.key)) {
            allTransactions.unshift(newTransaction);
            if (currentPage === 1) {
                addTransactionToTable(newTransaction, true, true);
                if ($('#transactionTable tbody tr').length > transactionsPerPage) {
                    $('#transactionTable tbody tr:last').remove();
                }
            }
            updatePagination();
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
            if (Math.floor(index / transactionsPerPage) + 1 === currentPage) {
                $(`tr[data-key="${updatedTransaction.key}"]`).replaceWith(
                    $(addTransactionToTable(updatedTransaction, false, true))
                );
            }
        }
    });
}

function listenForTransactionDeletions() {
    transactionsRef.on('child_removed', function(snapshot) {
        var deletedKey = snapshot.key;
        allTransactions = allTransactions.filter(t => t.key !== deletedKey);
        $(`tr[data-key="${deletedKey}"]`).remove();
        loadTransactions(currentPage);
        updatePagination();
    });
}

// Reset button functionality
$('#resetButton').click(function() {
    var userRole = $(this).data('role');
    
    if (userRole !== 'admin') {
        return; // Button should be disabled for non-admins, but just in case
    }
    
    if (confirm('Are you sure you want to delete all transactions? This action cannot be undone.')) {
        var transactionsRef = firebase.database().ref('tables/transactions');
        transactionsRef.remove()
            .then(function() {
                console.log('All transactions have been reset');
                $('#transactionTable tbody').empty();
                allTransactions = [];
                updatePagination();
                alert('All transactions have been successfully deleted.');
            })
            .catch(function(error) {
                console.error('Error resetting transactions:', error);
                alert('An error occurred while deleting transactions. Please try again.');
            });
    }
});

$(document).ready(function() {
    console.log('jQuery is ready');
    console.log('Table exists:', $('#transactionTable').length > 0);
    console.log('Pagination div exists:', $('#pagination').length > 0);
    loadExistingTransactions();
    listenForNewTransactions();
    listenForTransactionChanges();
    listenForTransactionDeletions();
});

// Add this to the existing <script> tag, after line 657
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const userRole = sidebar.dataset.userRole;
    const userManagementLink = document.getElementById('userManagementLink');

    userManagementLink.addEventListener('click', function(event) {
        if (userRole !== 'admin') {
            event.preventDefault();
            alert("You don't have permission to access User Management.");
        }
    });
});

// Function to calculate summary data
function calculateSummary(transactions) {
    let totalIncome = 0;
    let productCounts = {};
    transactions.forEach(t => {
        totalIncome += parseFloat(t.amount);
        productCounts[t.product_name] = (productCounts[t.product_name] || 0) + 1;
    });

    let mostSold = Object.entries(productCounts).reduce((a, b) => a[1] > b[1] ? a : b)[0];
    let leastSold = Object.entries(productCounts).reduce((a, b) => a[1] < b[1] ? a : b)[0];

    return {
        totalTransactions: transactions.length,
        totalIncome: totalIncome.toFixed(2),
        mostSoldProduct: mostSold,
        leastSoldProduct: leastSold
    };
}

// Function to export data as CSV
function exportCSV() {
    let summary = calculateSummary(allTransactions);
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "HygienexCare - Transaction Log Report\n\n";
    csvContent += `Report Generated: ${new Date().toLocaleString()}\n\n`;
    csvContent += `Total Transactions: ${summary.totalTransactions}\n`;
    csvContent += `Total Income: ₱${summary.totalIncome}\n`;
    csvContent += `Most Sold Product: ${summary.mostSoldProduct}\n`;
    csvContent += `Least Sold Product: ${summary.leastSoldProduct}\n\n`;
    csvContent += "Transaction ID,Name,Amount,Time,Date,Remaining\n";
    allTransactions.forEach(function(transaction, index) {
        let row = `${index + 1},${transaction.product_name},₱${parseFloat(transaction.amount).toFixed(2)},${formatTime(transaction.time)},${formatDate(transaction.date)},${transaction.remaining}`;
        csvContent += row + "\n";
    });
    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "HygienexCare_Transaction_Log.csv");
    document.body.appendChild(link);
    link.click();
}

// Function to export data as PDF
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const summary = calculateSummary(allTransactions);
    const pageWidth = doc.internal.pageSize.width;
    const pageHeight = doc.internal.pageSize.height;

    // Add background color
    doc.setFillColor(240, 240, 240);
    doc.rect(0, 0, pageWidth, pageHeight, 'F');

    // Header
    doc.setFillColor(57, 156, 67);  // Green color
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
    doc.roundedRect(20, 50, pageWidth - 40, 50, 3, 3, 'F');
    doc.setFontSize(14);
    doc.setFont("helvetica", "bold");
    doc.text("Summary", 25, 60);
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text(`Total Transactions: ${summary.totalTransactions}`, 25, 70);
    doc.text(`Total Income: ₱${summary.totalIncome}`, 25, 80);
    doc.text(`Most Sold Product: ${summary.mostSoldProduct}`, pageWidth / 2 + 10, 70);
    doc.text(`Least Sold Product: ${summary.leastSoldProduct}`, pageWidth / 2 + 10, 80);

    // Date and Time
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 110);

    // Table
    doc.autoTable({
        head: [['ID', 'Name', 'Amount', 'Time', 'Date', 'Remaining']],
        body: allTransactions.map((t, index) => [
            index + 1,
            t.product_name, 
            `₱${parseFloat(t.amount).toFixed(2)}`, 
            formatTime(t.time), 
            formatDate(t.date), 
            t.remaining
        ]),
        startY: 120,
        styles: { fillColor: [255, 255, 255] },
        columnStyles: {
            0: { cellWidth: 20 },
            1: { cellWidth: 50 },
            2: { cellWidth: 30 },
            3: { cellWidth: 30 },
            4: { cellWidth: 30 },
            5: { cellWidth: 30 }
        },
        headStyles: { fillColor: [57, 156, 67], textColor: [255, 255, 255] },
        alternateRowStyles: { fillColor: [245, 245, 245] }
    });

    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text(`Page ${i} of ${pageCount}`, pageWidth - 20, pageHeight - 10, { align: "right" });
        
        // Add logo or watermark
        doc.setGState(new doc.GState({opacity: 0.2}));
        doc.setFontSize(60);
        doc.setTextColor(150, 150, 150);
        doc.text("HygienexCare", pageWidth / 2, pageHeight / 2, { align: "center", angle: 45 });
        doc.setGState(new doc.GState({opacity: 1}));
    }

    doc.save("HygienexCare_Transaction_Log.pdf");
}

// Function to export data as Excel
function exportExcel() {
    const summary = calculateSummary(allTransactions);
    const worksheet = XLSX.utils.json_to_sheet([
        { A: "HygienexCare - Transaction Log Report" },
        { A: `Report Generated: ${new Date().toLocaleString()}` },
        { A: `Total Transactions: ${summary.totalTransactions}` },
        { A: `Total Income: ₱${summary.totalIncome}` },
        { A: `Most Sold Product: ${summary.mostSoldProduct}` },
        { A: `Least Sold Product: ${summary.leastSoldProduct}` },
        {}  // Empty row
    ], { header: ["A"], skipHeader: true });

    XLSX.utils.sheet_add_json(worksheet, allTransactions.map((t, index) => ({
        ID: index + 1,
        Name: t.product_name,
        Amount: `₱${parseFloat(t.amount).toFixed(2)}`,
        Time: formatTime(t.time),
        Date: formatDate(t.date),
        Remaining: t.remaining
    })), { origin: "A8" });

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Transactions");
    XLSX.writeFile(workbook, "HygienexCare_Transaction_Log.xlsx");
}

// Add event listeners for export buttons
document.getElementById('exportCSV').addEventListener('click', exportCSV);
document.getElementById('exportPDF').addEventListener('click', exportPDF);
document.getElementById('exportExcel').addEventListener('click', exportExcel);
</script>

<?php include 'edit_profile_modal.php'; ?>
<script src="edit_profile.js"></script>

<!-- Add the reset_listener.js script -->
<script src="reset_listener.js"></script>
<script src="firebase-init.js"></script>
<script src="reset_listener.js"></script>
</body>
</html>
