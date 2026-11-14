<?php
require_once '../../includes/db.php';
require_once '../../includes/order_helpers.php';
require_once '../../includes/customer_header.php';

/**
 * Download Bill Page
 * Generates and displays a printable bill for a specific order
 */

$customer_id = $_SESSION['customer_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($order_id == 0) {
    header("Location: index.php");
    exit();
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT op.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               sl.shop_name, sl.address as shop_address, sl.city as shop_city, sl.pin_code as shop_pin_code
        FROM order_product op
        JOIN customer c ON op.customer_id = c.id
        LEFT JOIN (
            SELECT shop_name, address, city, pin_code
            FROM shop_location
            ORDER BY id DESC
            LIMIT 1
        ) sl ON 1=1
        WHERE op.id = ? AND op.customer_id = ?
    ");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch();
    
    if(!$order) {
        $_SESSION['error'] = 'Order not found';
        header("Location: index.php");
        exit();
    }
    
    // Get customer location for this order
    $stmt = $pdo->prepare("
        SELECT *
        FROM location
        WHERE user_id = ? AND user_type = 'customer'
        ORDER BY is_default DESC, created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$customer_id]);
    $customer_location = $stmt->fetch();

    $itemsByOrder = fetchOrderItemsByOrderIds($pdo, [$order_id]);
    $orders = enrichOrdersWithItems([$order], $itemsByOrder);
    $order = $orders[0];
    
} catch(PDOException $e) {
    error_log('Download bill query failed: ' . $e->getMessage());
    die("Unable to generate the bill right now. Please try again later.");
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-receipt"></i> Order Bill</h2>
        <p class="text-muted">Bill for Order #<?php echo $order['id']; ?></p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="bill-template" id="billContent">
                    <!-- Bill Header -->
                    <div class="bill-header">
                        <h2><?php echo htmlspecialchars($order['shop_name'] ?? 'Grocery Store'); ?></h2>
                        <p><?php echo htmlspecialchars($order['shop_address'] ?? '123 Main Street'); ?></p>
                        <p><?php echo htmlspecialchars($order['shop_city'] ?? 'Mumbai'); ?> - <?php echo htmlspecialchars($order['shop_pin_code'] ?? '400001'); ?></p>
                        <p><strong>Phone:</strong> +91 9876543210 | <strong>Email:</strong> support@grocery.com</p>
                    </div>
                    
                    <!-- Bill Info -->
                    <div class="row mb-4">
                        <div class="col-6">
                            <h5>Bill To:</h5>
                            <p><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            <p><?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <?php if($customer_location): ?>
                                <p><?php echo htmlspecialchars($customer_location['area']); ?></p>
                                <p><?php echo htmlspecialchars($customer_location['city']); ?> - <?php echo htmlspecialchars($customer_location['pin_code']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 text-end">
                            <h5>Bill Details:</h5>
                            <p><strong>Bill No:</strong> #<?php echo $order['id']; ?></p>
                            <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></p>
                            <p><strong>Payment:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                            <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <table class="table table-bordered mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($order['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <?php if($item['product_image']): ?>
                                            <img src="../../<?php echo $item['product_image']; ?>" class="rounded me-2" width="50" height="50" style="object-fit: cover;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </td>
                                    <td>₹<?php echo number_format($item['price_per_item'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₹<?php echo number_format($item['total_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Bill Total -->
                    <div class="bill-total">
                        <div class="row">
                            <div class="col-8 text-end">
                                <p>Subtotal:</p>
                                <p>Delivery Fee:</p>
                                <p><strong>Grand Total:</strong></p>
                            </div>
                            <div class="col-4">
                                <p>₹<?php echo number_format($order['subtotal_amount'], 2); ?></p>
                                <p>₹<?php echo number_format($order['delivery_fee'], 2); ?></p>
                                <p><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms -->
                    <div class="mt-4 pt-4 border-top">
                        <h6>Terms & Conditions:</h6>
                        <ul class="small">
                            <li>Goods once sold will not be taken back</li>
                            <li>Please check the items before delivery</li>
                            <li>Delivery charges may apply based on distance</li>
                            <li>Free delivery within 5km radius</li>
                            <li>For any queries, contact our support team</li>
                        </ul>
                    </div>
                    
                    <!-- Footer -->
                    <div class="text-center mt-4">
                        <p>Thank you for shopping with us!</p>
                        <p><strong><?php echo htmlspecialchars($order['shop_name'] ?? 'Grocery Store'); ?></strong></p>
                        <p class="small">Generated on <?php echo date('M d, Y H:i:s'); ?></p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="text-center mt-4 no-print">
                    <button onclick="printBill()" class="btn btn-primary btn-lg me-2">
                        <i class="bi bi-printer"></i> Print Bill
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Back to History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .bill-template {
        border: 2px solid #000 !important;
        padding: 20px !important;
    }
    body {
        background-color: white !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .navbar, footer, .sidebar {
        display: none !important;
    }
}
</style>

<script>
function printBill() {
    window.print();
}
</script>

<?php require_once '../../includes/customer_footer.php'; ?>
