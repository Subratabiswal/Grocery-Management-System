<?php
require_once '../../includes/db.php';
require_once '../../includes/order_helpers.php';
require_once '../../includes/owner_header.php';

/**
 * Owner Reports Page
 * Shows daily orders, successful orders, pending orders, and total revenue
 */

// Handle status update
$statusInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
if(isset($statusInput['update_status']) && isset($statusInput['status']) && isset($statusInput['order_id'])) {
    try {
        $valid_statuses = ['ordered', 'delivered', 'cancelled', 'returned'];
        $new_status = trim($statusInput['status']);
        $redirectDate = isset($statusInput['date']) && $statusInput['date'] !== '' ? trim($statusInput['date']) : date('Y-m-d');
        $redirectType = isset($statusInput['report_type']) ? trim($statusInput['report_type']) : 'daily';
        $validReportTypes = ['daily', 'weekly', 'monthly'];
        if(!in_array($redirectType, $validReportTypes, true)) {
            $redirectType = 'daily';
        }
        
        if(in_array($new_status, $valid_statuses)) {
            $stmt = $pdo->prepare("UPDATE order_product SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $statusInput['order_id']]);
            $_SESSION['success'] = 'Order status updated successfully!';
        } else {
            $_SESSION['error'] = 'Invalid status';
        }
        header("Location: index.php?report_type=" . urlencode($redirectType) . "&date=" . urlencode($redirectDate));
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
}

// Handle report filter
$report_type = isset($_GET['report_type']) ? trim($_GET['report_type']) : 'daily';
$validReportTypes = ['daily', 'weekly', 'monthly'];
if(!in_array($report_type, $validReportTypes, true)) {
    $report_type = 'daily';
}

$date_filter = isset($_GET['date']) && trim($_GET['date']) !== '' ? trim($_GET['date']) : date('Y-m-d');
$selectedDate = DateTime::createFromFormat('Y-m-d', $date_filter);
if(!$selectedDate) {
    $selectedDate = new DateTime('today');
    $date_filter = $selectedDate->format('Y-m-d');
}

switch($report_type) {
    case 'weekly':
        $pageTitle = 'Weekly Reports';
        $pageDescription = 'View weekly order statistics and details';
        $summaryTitle = 'Weekly Summary';
        $periodStart = (clone $selectedDate)->modify('monday this week')->setTime(0, 0, 0);
        $periodEnd = (clone $periodStart)->modify('+1 week');
        $periodDisplay = $periodStart->format('F d, Y') . ' - ' . (clone $periodStart)->modify('+6 days')->format('F d, Y');
        $resetLabel = 'This Week';
        break;

    case 'monthly':
        $pageTitle = 'Monthly Reports';
        $pageDescription = 'View monthly order statistics and details';
        $summaryTitle = 'Monthly Summary';
        $periodStart = (clone $selectedDate)->modify('first day of this month')->setTime(0, 0, 0);
        $periodEnd = (clone $periodStart)->modify('+1 month');
        $periodDisplay = $periodStart->format('F Y');
        $resetLabel = 'This Month';
        break;

    case 'daily':
    default:
        $pageTitle = 'Daily Reports';
        $pageDescription = 'View daily order statistics and details';
        $summaryTitle = 'Daily Summary';
        $periodStart = (clone $selectedDate)->setTime(0, 0, 0);
        $periodEnd = (clone $periodStart)->modify('+1 day');
        $periodDisplay = $periodStart->format('F d, Y');
        $resetLabel = 'Today';
        break;
}

$periodStartSql = $periodStart->format('Y-m-d H:i:s');
$periodEndSql = $periodEnd->format('Y-m-d H:i:s');

try {
    // Total orders for selected period
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM order_product WHERE order_date >= ? AND order_date < ?");
    $stmt->execute([$periodStartSql, $periodEndSql]);
    $total_orders = $stmt->fetch()['total'];
    
    // Successful orders (delivered) for selected period
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM order_product WHERE order_date >= ? AND order_date < ? AND status = 'delivered'");
    $stmt->execute([$periodStartSql, $periodEndSql]);
    $successful_orders = $stmt->fetch()['total'];
    
    // Pending orders for selected period
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM order_product WHERE order_date >= ? AND order_date < ? AND status = 'ordered'");
    $stmt->execute([$periodStartSql, $periodEndSql]);
    $pending_orders = $stmt->fetch()['total'];
    
    // Total revenue for selected period
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM order_product WHERE order_date >= ? AND order_date < ? AND status != 'cancelled'");
    $stmt->execute([$periodStartSql, $periodEndSql]);
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Get all orders for selected period
    $stmt = $pdo->prepare("
        SELECT op.*, c.name as customer_name, c.phone as customer_phone
        FROM order_product op
        JOIN customer c ON op.customer_id = c.id
        WHERE op.order_date >= ? AND op.order_date < ?
        ORDER BY op.order_date DESC
    ");
    $stmt->execute([$periodStartSql, $periodEndSql]);
    $orders = $stmt->fetchAll();

    $orderItems = fetchOrderItemsByOrderIds($pdo, array_column($orders, 'id'));
    $orders = enrichOrdersWithItems($orders, $orderItems);
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-graph-up"></i> <?php echo $pageTitle; ?></h2>
        <p class="text-muted"><?php echo $pageDescription; ?></p>
    </div>
</div>

<!-- Report Filter -->
<div class="row mb-4">
    <div class="col-lg-7">
        <form method="GET" action="">
            <div class="row g-2">
                <div class="col-md-4">
                    <select class="form-select" name="report_type">
                        <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                        <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Weekly Report</option>
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-calendar"></i> Filter
                    </button>
                    <a href="index.php?report_type=<?php echo urlencode($report_type); ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x"></i> <?php echo $resetLabel; ?>
                    </a>
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
        <span class="text-muted">Showing reports for: <strong><?php echo htmlspecialchars($periodDisplay); ?></strong></span>
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
                        <h6 class="card-title">Successful Orders</h6>
                        <div class="number"><?php echo $successful_orders; ?></div>
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
        <div class="card dashboard-card info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Revenue</h6>
                        <div class="number">₹<?php echo number_format($total_revenue, 0); ?></div>
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
                <h5 class="mb-0">Order Details - <?php echo htmlspecialchars($periodDisplay); ?></h5>
            </div>
            <div class="card-body">
                <?php if(count($orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Delivery Fee</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Order Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                        </td>
                                        <td>
                                            <?php if($order['primary_product_image']): ?>
                                                <img src="../../<?php echo $order['primary_product_image']; ?>" class="rounded" width="40" height="40" style="object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($order['items_summary']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['items_summary_full']); ?></small>
                                        </td>
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
                                        <td><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <form method="POST" action="" class="d-flex gap-2 align-items-center flex-nowrap">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                                                <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                                                <select name="status" class="form-select form-select-sm" style="min-width: 140px;">
                                                    <option value="ordered" <?php echo $order['status'] === 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    <option value="returned" <?php echo $order['status'] === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <p class="text-muted">Showing <?php echo count($orders); ?> order(s) for <?php echo htmlspecialchars($periodDisplay); ?></p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cart-x" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="mt-3">No Orders Found</h4>
                        <p class="text-muted">No orders were placed for <?php echo htmlspecialchars($periodDisplay); ?>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Summary Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $summaryTitle; ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Order Statistics</h6>
                        <ul>
                            <li>Total Orders: <?php echo $total_orders; ?></li>
                            <li>Successful Deliveries: <?php echo $successful_orders; ?></li>
                            <li>Pending Orders: <?php echo $pending_orders; ?></li>
                            <li>Cancelled/Returned: <?php echo $total_orders - $successful_orders - $pending_orders; ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Financial Summary</h6>
                        <ul>
                            <li>Total Revenue: ₹<?php echo number_format($total_revenue, 2); ?></li>
                            <li>Average Order Value: ₹<?php echo $total_orders > 0 ? number_format($total_revenue / $total_orders, 2) : '0.00'; ?></li>
                            <li>Delivery Fees Collected: ₹<?php echo number_format(array_sum(array_column($orders, 'delivery_fee')), 2); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
