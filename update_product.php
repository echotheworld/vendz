<?php
session_start();
require __DIR__.'/vendor/autoload.php';
require_once __DIR__ . '/firebase-init.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $productId = $_POST['product_id'];
        $productName = $_POST['product_name'];
        $productQuantity = max(intval($_POST['product_quantity']), 0);
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

        if (!empty($updates)) {
            $updatedProduct = array_merge($oldProduct, $updates);
            $changeDetails = implode(". ", $changes);
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "**{$productIdentity}**: {$changeDetails}";
            addLogEntry($_SESSION['user_id'], 'Updated Product', $logMessage);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product updated successfully.',
                'productId' => $productId,
                'updatedProduct' => $updatedProduct
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'No changes were made to the product.',
                'productId' => $productId,
                'updatedProduct' => $oldProduct
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
