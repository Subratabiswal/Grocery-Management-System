<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Edit Customer Page
 * Allows owner to update customer details
 */

$error = '';
$success = '';
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($customer_id == 0) {
    header("Location: manage_customer.php");
    exit();
}

// Get customer details
try {
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if(!$customer) {
        $_SESSION['error'] = 'Customer not found';
        header("Location: manage_customer.php");
        exit();
    }
    
    // Get customer order count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_product WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $order_count = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validation
    if(empty($name) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        try {
            // Check if email exists for another customer
            $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ? AND id != ?");
            $stmt->execute([$email, $customer_id]);
            
            if($stmt->rowCount() > 0) {
                $error = 'Email already registered to another customer';
            } else {
                // Update customer
                $stmt = $pdo->prepare("UPDATE customer SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $customer_id]);
                
                $success = 'Customer updated successfully!';
                
                // Refresh customer data
                $stmt = $pdo->prepare("SELECT * FROM customer WHERE id = ?");
                $stmt->execute([$customer_id]);
                $customer = $stmt->fetch();
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-pencil"></i> Edit Customer</h2>
        <p class="text-muted">Update customer details for: <?php echo htmlspecialchars($customer['name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Customer Details</h5>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <a href="manage_customer.php" class="btn btn-sm btn-success ms-2">View Customers</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Mobile Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required pattern="[0-9]{10}" title="10 digit mobile number">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer ID</label>
                            <input type="text" class="form-control" value="<?php echo $customer['id']; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_customer.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Update Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Customer Statistics</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Customer ID:</strong> <?php echo $customer['id']; ?>
                </div>
                <div class="mb-3">
                    <strong>Registered:</strong> <?php echo date('M d, Y H:i', strtotime($customer['created_at'])); ?>
                </div>
                <div class="mb-3">
                    <strong>Total Orders:</strong> <?php echo $order_count; ?>
                </div>
                
                <hr>
                
                <h6>Account Status</h6>
                <?php if($order_count > 0): ?>
                    <div class="alert alert-success">Active Customer</div>
                <?php else: ?>
                    <div class="alert alert-warning">New Customer (No orders yet)</div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="../reports/index.php?customer=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info w-100">
                        <i class="bi bi-graph-up"></i> View Order History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
