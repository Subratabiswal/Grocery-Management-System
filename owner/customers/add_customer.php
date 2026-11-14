<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Add Customer Page
 * Allows owner to manually add customers to the system
 */

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    
    // Validation
    if(empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif(strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM customer WHERE email = ?");
            $stmt->execute([$email]);
            
            if($stmt->rowCount() > 0) {
                $error = 'Email already registered';
            } else {
                // Insert new customer
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO customer (name, email, phone, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $hashed_password]);
                
                $success = 'Customer added successfully!';
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-person-plus"></i> Add Customer</h2>
        <p class="text-muted">Manually add a new customer to the system</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Customer Details</h5>
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
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Mobile Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" title="10 digit mobile number">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_customer.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-person-plus"></i> Add Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Information</h5>
            </div>
            <div class="card-body">
                <h6>Manual Customer Addition</h6>
                <p>Use this form to manually add customers who may not have registered through the website.</p>
                
                <h6>Requirements</h6>
                <ul>
                    <li>Valid email address</li>
                    <li>10-digit mobile number</li>
                    <li>Password (min 6 characters)</li>
                    <li>Unique email per customer</li>
                </ul>
                
                <h6>Note</h6>
                <p class="text-muted">Customers can also register themselves through the customer registration page.</p>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Customers will need to set their location after logging in.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
