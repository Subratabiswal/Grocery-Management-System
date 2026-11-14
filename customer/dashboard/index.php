<?php
require_once '../../includes/db.php';
require_once '../../includes/order_helpers.php';
require_once '../../includes/customer_header.php';

/**
 * Customer Dashboard
 * Shows customer-specific statistics and overview
 */

$customer_id = $_SESSION['customer_id'];

try {
    // Total products available
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM product WHERE available_quantity > 0");
    $total_products = $stmt->fetch()['total'];
    
    // Customer's total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM order_product WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $total_orders = $stmt->fetch()['total'];
    
    // Customer's delivered orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM order_product WHERE customer_id = ? AND status = 'delivered'");
    $stmt->execute([$customer_id]);
    $delivered_orders = $stmt->fetch()['total'];
    
    // Customer's pending orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM order_product WHERE customer_id = ? AND status = 'ordered'");
    $stmt->execute([$customer_id]);
    $pending_orders = $stmt->fetch()['total'];
    
    // Total spent by customer
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM order_product WHERE customer_id = ? AND status = 'delivered'");
    $stmt->execute([$customer_id]);
    $total_spent = $stmt->fetch()['total'] ?? 0;
    
    // Recent orders
    $stmt = $pdo->prepare("
        SELECT op.*
        FROM order_product op 
        WHERE op.customer_id = ? 
        ORDER BY op.order_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$customer_id]);
    $recent_orders = $stmt->fetchAll();

    $recentOrderItems = fetchOrderItemsByOrderIds($pdo, array_column($recent_orders, 'id'));
    $recent_orders = enrichOrdersWithItems($recent_orders, $recentOrderItems);
    
    // Customer's location
    $stmt = $pdo->prepare("SELECT * FROM location WHERE user_id = ? AND user_type = 'customer' ORDER BY is_default DESC LIMIT 1");
    $stmt->execute([$customer_id]);
    $customer_location = $stmt->fetch();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-speedometer2"></i> Customer Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Available Products</h6>
                        <div class="number"><?php echo $total_products; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-box-seam"></i>
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
                        <h6 class="card-title">My Orders</h6>
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
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Pending Orders</h6>
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
        <div class="card dashboard-card success">
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

<!-- Location Status -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Delivery Location</h5>
            </div>
            <div class="card-body">
                <?php if($customer_location): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Location is set
                    </div>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($customer_location['name']); ?></p>
                    <p><strong>City:</strong> <?php echo htmlspecialchars($customer_location['city']); ?></p>
                    <p><strong>Pin Code:</strong> <?php echo htmlspecialchars($customer_location['pin_code']); ?></p>
                    <p><strong>Mobile:</strong> <?php echo htmlspecialchars($customer_location['mobile_no']); ?></p>
                    <p><strong>Distance from Shop:</strong> <?php echo $customer_location['distance_from_shop']; ?> km</p>
                    <a href="<?php echo $baseUrl; ?>/customer/location.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil"></i> Update Location
                    </a>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Location not set
                    </div>
                    <p class="text-muted">Please set your delivery location to place orders.</p>
                    <a href="<?php echo $baseUrl; ?>/customer/location.php" class="btn btn-primary">
                        <i class="bi bi-geo-alt"></i> Set Location
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-credit-card"></i> Payment Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 text-center">
                        <i class="bi bi-wallet" style="font-size: 2rem; color: #28a745;"></i>
                        <h5 class="mt-2">Online Payment</h5>
                        <p class="text-muted">Pay securely online</p>
                    </div>
                    <div class="col-6 text-center">
                        <i class="bi bi-cash" style="font-size: 2rem; color: #ffc107;"></i>
                        <h5 class="mt-2">Cash on Delivery</h5>
                        <p class="text-muted">Pay when delivered</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Orders</h5>
                <a href="<?php echo $baseUrl; ?>/customer/history/index.php" class="btn btn-sm btn-outline-light">View All Orders</a>
            </div>
            <div class="card-body">
                <?php if(count($recent_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Items</th>
                                    <th>Order Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td>
                                            <?php if($order['primary_product_image']): ?>
                                                <img src="../../<?php echo $order['primary_product_image']; ?>" class="rounded" width="40" height="40" style="object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($order['items_summary']); ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-x" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-2">No orders yet. Start shopping!</p>
                        <a href="<?php echo $baseUrl; ?>/customer/orders/place_order.php" class="btn btn-success">
                            <i class="bi bi-cart-plus"></i> Place Your First Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo $baseUrl; ?>/customer/orders/place_order.php" class="btn btn-success w-100">
                            <i class="bi bi-cart-plus"></i> Place Order
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo $baseUrl; ?>/customer/history/index.php" class="btn btn-info w-100">
                            <i class="bi bi-clock-history"></i> Order History
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo $baseUrl; ?>/customer/location.php" class="btn btn-primary w-100">
                            <i class="bi bi-geo-alt"></i> Update Location
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/customer_footer.php'; ?>
