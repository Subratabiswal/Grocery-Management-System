<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Customer Header File - Header for customer panel
 */
if(isset($_SERVER['DOCUMENT_ROOT'])) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '\\/'));
    $baseUrl = str_starts_with($projectRoot, $documentRoot)
        ? str_replace('\\', '/', substr($projectRoot, strlen($documentRoot)))
        : '/store';
    $baseUrl = rtrim($baseUrl, '/');
} else {
    $baseUrl = '/store';
}

if(!isset($_SESSION['customer_logged_in'])) {
    header("Location: " . $baseUrl . "/customer/login.php");
    exit();
}

$cartCount = 0;
if(isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $cartQuantity) {
        $cartCount += max(0, (int) $cartQuantity);
    }
}

$customerLocationLabel = 'Set delivery location';
if(isset($pdo) && isset($_SESSION['customer_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT area, city, pin_code FROM location WHERE user_id = ? AND user_type = 'customer' ORDER BY is_default DESC LIMIT 1");
        $stmt->execute([$_SESSION['customer_id']]);
        $headerLocation = $stmt->fetch();

        if($headerLocation) {
            $locationParts = [];
            if(!empty($headerLocation['area'])) {
                $locationParts[] = trim($headerLocation['area']);
            }
            if(!empty($headerLocation['city'])) {
                $locationParts[] = trim($headerLocation['city']);
            }
            if(!empty($headerLocation['pin_code'])) {
                $locationParts[] = trim($headerLocation['pin_code']);
            }

            if(!empty($locationParts)) {
                $customerLocationLabel = implode(', ', $locationParts);
            }
        }
    } catch(PDOException $e) {
        $customerLocationLabel = 'Set delivery location';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Grocery Store</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo $baseUrl; ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .customer-location-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .customer-location-pill:hover {
            background: rgba(255, 255, 255, 0.24);
            color: #fff;
        }
        .customer-location-text {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $baseUrl; ?>/index.php">
                <i class="bi bi-shop"></i> Grocery Store
            </a>
            <a class="customer-location-pill ms-3 d-none d-lg-inline-flex" href="<?php echo $baseUrl; ?>/customer/location.php">
                <i class="bi bi-geo-alt-fill"></i>
                <span class="customer-location-text"><?php echo htmlspecialchars($customerLocationLabel); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/customer/dashboard/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/customer/orders/place_order.php">
                            <i class="bi bi-cart-plus"></i> Shop &amp; Cart
                            <?php if($cartCount > 0): ?>
                                <span class="badge bg-light text-success ms-1"><?php echo $cartCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/customer/history/index.php"><i class="bi bi-clock-history"></i> Order History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/customer/location.php"><i class="bi bi-geo-alt"></i> My Location</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/customer/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Welcome Message -->
    <div class="bg-light py-2">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
            <p class="mb-0">Welcome, <strong><?php echo htmlspecialchars($_SESSION['customer_name']); ?></strong> | 
               <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($_SESSION['customer_phone']); ?>
            </p>
            <a class="text-decoration-none" href="<?php echo $baseUrl; ?>/customer/location.php">
                <i class="bi bi-geo-alt-fill text-success"></i>
                <span class="text-muted"><?php echo htmlspecialchars($customerLocationLabel); ?></span>
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['success']; 
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['error']; 
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container mt-4">
