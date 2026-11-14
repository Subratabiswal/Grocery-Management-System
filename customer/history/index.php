<?php
require_once '../../includes/db.php';
require_once '../../includes/order_helpers.php';
require_once '../../includes/customer_header.php';

/**
 * Customer Order History Page
 * Shows customer's past orders with bill download functionality
 */

$customer_id = $_SESSION['customer_id'];

try {
    // Get customer's orders
    $stmt = $pdo->prepare("
        SELECT op.*
        FROM order_product op
        WHERE op.customer_id = ?
        ORDER BY op.order_date DESC
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll();

    $itemsByOrder = fetchOrderItemsByOrderIds($pdo, array_column($orders, 'id'));
    $orders = enrichOrdersWithItems($orders, $itemsByOrder);
    
    // Calculate statistics
    $total_orders = count($orders);
    $total_spent = array_sum(array_column($orders, 'total_amount'));
    $delivered_orders = count(array_filter($orders, fn($o) => $o['status'] == 'delivered'));
    $pending_orders = count(array_filter($orders, fn($o) => $o['status'] == 'ordered'));
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-clock-history"></i> Order History</h2>
        <p class="text-muted">View your past orders and download bills</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Orders</h6>
                        <div class="number"><?php echo $total_orders; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cart-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Delivered</h6>
                        <div class="number"><?php echo $delivered_orders; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Pending</h6>
                        <div class="number"><?php echo $pending_orders; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Spent</h6>
                        <div class="number">₹<?php echo number_format($total_spent, 0); ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">My Orders</h5>
            </div>
            <div class="card-body">
                <?php if(count($orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Items</th>
                                    <th>Order Date</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Delivery Fee</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td>
                                            <?php if($order['primary_product_image']): ?>
                                                <img src="../../<?php echo $order['primary_product_image']; ?>" class="rounded" width="40" height="40" style="object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($order['items_summary']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['items_summary_full']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo $order['total_quantity']; ?></td>
                                        <td>₹<?php echo number_format($order['subtotal_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($order['delivery_fee'], 2); ?></td>
                                        <td><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="download_bill.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary" title="Download Bill">
                                                <i class="bi bi-download"></i> Bill
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <p class="text-muted">Showing <?php echo count($orders); ?> order(s)</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cart-x" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="mt-3">No Orders Yet</h4>
                        <p class="text-muted">You haven't placed any orders yet.</p>
                        <a href="../orders/place_order.php" class="btn btn-success">
                            <i class="bi bi-cart-plus"></i> Place Your First Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/customer_footer.php'; ?>
