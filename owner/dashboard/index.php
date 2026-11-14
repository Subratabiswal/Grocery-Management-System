<?php
require_once '../../includes/db.php';
require_once '../../includes/order_helpers.php';
require_once '../../includes/owner_header.php';

/**
 * Owner Dashboard
 * Shows statistics and overview of store performance
 */

// Get statistics
try {
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM product");
    $total_products = $stmt->fetch()['total'];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customer");
    $total_customers = $stmt->fetch()['total'];
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM order_product");
    $total_orders = $stmt->fetch()['total'];
    
    // Available products (in stock)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM product WHERE available_quantity > 0");
    $available_products = $stmt->fetch()['total'];
    
    // Today's orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM order_product WHERE DATE(order_date) = CURDATE()");
    $today_orders = $stmt->fetch()['total'];
    
    // Today's revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM order_product WHERE DATE(order_date) = CURDATE() AND status != 'cancelled'");
    $today_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Pending orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM order_product WHERE status = 'ordered'");
    $pending_orders = $stmt->fetch()['total'];
    
    // Delivered orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM order_product WHERE status = 'delivered'");
    $delivered_orders = $stmt->fetch()['total'];
    
    // Recent orders (last 5)
    $stmt = $pdo->query("
        SELECT op.*, c.name as customer_name
        FROM order_product op 
        JOIN customer c ON op.customer_id = c.id 
        ORDER BY op.order_date DESC 
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

    $recentOrderItems = fetchOrderItemsByOrderIds($pdo, array_column($recent_orders, 'id'));
    $recent_orders = enrichOrdersWithItems($recent_orders, $recentOrderItems);
    
    // Low stock products (less than 10)
    $stmt = $pdo->query("SELECT * FROM product WHERE available_quantity < 10 ORDER BY available_quantity ASC LIMIT 5");
    $low_stock_products = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-speedometer2"></i> Owner Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['owner_name']); ?>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Products</h6>
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
                        <h6 class="card-title">Total Customers</h6>
                        <div class="number"><?php echo $total_customers; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-people"></i>
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
                        <h6 class="card-title">Available Products</h6>
                        <div class="number"><?php echo $available_products; ?></div>
                    </div>
                    <div class="icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Statistics -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Today's Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h4><?php echo $today_orders; ?></h4>
                        <p class="text-muted mb-0">Orders Today</p>
                    </div>
                    <div class="col-6">
                        <h4>₹<?php echo number_format($today_revenue, 2); ?></h4>
                        <p class="text-muted mb-0">Today's Revenue</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Order Status</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h4><?php echo $pending_orders; ?></h4>
                        <p class="text-warning mb-0">Pending Orders</p>
                    </div>
                    <div class="col-6">
                        <h4><?php echo $delivered_orders; ?></h4>
                        <p class="text-success mb-0">Delivered Orders</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Orders</h5>
                <a href="../reports/index.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body">
                <?php if(count($recent_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['items_summary']); ?></td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No orders yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Products -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Low Stock Alert</h5>
                <a href="../products/manage_products.php" class="btn btn-sm btn-outline-light">Manage Products</a>
            </div>
            <div class="card-body">
                <?php if(count($low_stock_products) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Available</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($low_stock_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $product['available_quantity']; ?></span>
                                        </td>
                                        <td>
                                            <a href="../products/edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Update
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">All products are well stocked.</p>
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
                    <div class="col-md-3 mb-3">
                        <a href="../products/add_product.php" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Add Product
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="../customers/add_customer.php" class="btn btn-info w-100">
                            <i class="bi bi-person-plus"></i> Add Customer
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="../reports/index.php" class="btn btn-warning w-100">
                            <i class="bi bi-graph-up"></i> View Reports
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="../location.php" class="btn btn-primary w-100">
                            <i class="bi bi-geo-alt"></i> Location Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
