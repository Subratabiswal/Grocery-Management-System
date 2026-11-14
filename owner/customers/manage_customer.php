<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Manage Customers Page
 * Allows owner to view, edit, and delete customers
 */

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM customer WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success'] = 'Customer deleted successfully!';
        header("Location: manage_customer.php");
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Cannot delete customer: ' . $e->getMessage();
    }
}

try {
    // Get customers with optional search
    if($search) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM order_product WHERE customer_id = c.id) as order_count 
            FROM customer c 
            WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? 
            ORDER BY c.created_at DESC
        ");
        $search_param = "%$search%";
        $stmt->execute([$search_param, $search_param, $search_param]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM order_product WHERE customer_id = c.id) as order_count 
            FROM customer c 
            ORDER BY c.created_at DESC
        ");
    }
    $customers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-people"></i> Manage Customers</h2>
        <p class="text-muted">View and manage customer accounts</p>
    </div>
</div>

<!-- Search and Actions -->
<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Search
                </button>
                <?php if($search): ?>
                    <a href="manage_customer.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <a href="add_customer.php" class="btn btn-success">
            <i class="bi bi-person-plus"></i> Add Customer
        </a>
    </div>
</div>

<!-- Customers Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Customer List <?php echo $search ? "(Search: '$search')" : ''; ?></h5>
            </div>
            <div class="card-body">
                <?php if(count($customers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Orders</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customers as $customer): ?>
                                    <tr>
                                        <td>#<?php echo $customer['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $customer['order_count']; ?> orders</span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="manage_customer.php?delete=<?php echo $customer['id']; ?>" class="btn btn-sm btn-danger delete-btn" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <p class="text-muted">Showing <?php echo count($customers); ?> customer(s)</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="mt-3">No Customers Found</h4>
                        <p class="text-muted">
                            <?php if($search): ?>
                                No customers match your search. <a href="manage_customer.php">Clear search</a>
                            <?php else: ?>
                                No customers registered yet. <a href="add_customer.php">Add your first customer</a>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5>Total Customers</h5>
                <h3><?php echo count($customers); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5>Active Customers</h5>
                <h3><?php echo count(array_filter($customers, fn($c) => $c['order_count'] > 0)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5>New This Month</h5>
                <h3><?php echo count(array_filter($customers, fn($c) => strtotime($c['created_at']) > strtotime('-1 month'))); ?></h3>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
