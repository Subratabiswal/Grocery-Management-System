<?php
session_start();
/**
 * Owner Header File - Header with sidebar for owner panel
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

if(!isset($_SESSION['owner_logged_in'])) {
    header("Location: " . $baseUrl . "/owner/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Grocery Store</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo $baseUrl; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="owner-layout-page">
    <div class="d-flex owner-layout">
        <!-- Sidebar -->
        <div class="bg-dark text-white sidebar owner-sidebar">
            <div class="p-3">
                <h4><i class="bi bi-shop"></i> Owner Panel</h4>
                <hr class="bg-white">
                <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['owner_name']); ?></p>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/dashboard/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/products/add_product.php">
                        <i class="bi bi-plus-circle"></i> Add Product
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/products/manage_products.php">
                        <i class="bi bi-box-seam"></i> Manage Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/customers/add_customer.php">
                        <i class="bi bi-person-plus"></i> Add Customer
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/customers/manage_customer.php">
                        <i class="bi bi-people"></i> Manage Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/reports/index.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?php echo $baseUrl; ?>/owner/location.php">
                        <i class="bi bi-geo-alt"></i> Location Settings
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link text-danger" href="<?php echo $baseUrl; ?>/owner/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1 owner-main">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-success">
                <div class="container-fluid">
                    <a class="navbar-brand" href="<?php echo $baseUrl; ?>/index.php">
                        <i class="bi bi-shop"></i> Grocery Store
                    </a>
                    <span class="navbar-text ms-auto">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['owner_name']); ?>
                    </span>
                </div>
            </nav>

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

            <div class="container-fluid p-3">
