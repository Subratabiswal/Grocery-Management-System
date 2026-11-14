<?php
require_once 'includes/db.php';
session_start();

/**
 * Main Index Page
 * Landing page for the grocery store website
 */

try {
    // Get featured products (random selection)
    $stmt = $pdo->query("SELECT * FROM product WHERE available_quantity > 0 ORDER BY RAND() LIMIT 8");
    $featured_products = $stmt->fetchAll();
    
    // Get categories
    $stmt = $pdo->query("SELECT DISTINCT category FROM product WHERE available_quantity > 0 ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get shop location
    $stmt = $pdo->query("SELECT * FROM shop_location ORDER BY id DESC LIMIT 1");
    $shop_location = $stmt->fetch();
    
} catch(PDOException $e) {
    $featured_products = [];
    $categories = [];
    $shop_location = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grocery Store - Fresh Products Delivered</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 100px 0;
            border-radius: 0 0 50px 50px;
        }
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .feature-icon {
            font-size: 3rem;
            color: #28a745;
        }
        .product-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-card img {
            height: 200px;
            object-fit: cover;
        }
        .hero-primary-btn {
            background-color: #ffffff;
            border-color: #ffffff;
            color: #198754;
            font-weight: 600;
        }
        .hero-primary-btn:hover {
            background-color: #f8f9fa;
            border-color: #f8f9fa;
            color: #157347;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop"></i> Grocery Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="bi bi-house"></i> Home</a>
                    </li>
                    <?php if(isset($_SESSION['owner_logged_in'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="owner/dashboard/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="owner/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                        </li>
                    <?php elseif(isset($_SESSION['customer_logged_in'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="customer/dashboard/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customer/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="owner/login.php"><i class="bi bi-person-circle"></i> Owner Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customer/login.php"><i class="bi bi-person"></i> Customer Login</a>
                        </li>
                        <li class="nav-item">
                        <a class="btn btn-light text-success ms-lg-2 px-3" href="customer/register.php">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold">Fresh Groceries Delivered to Your Doorstep</h1>
                    <p class="lead mb-4">Quality products at affordable prices with free delivery within 5km radius</p>
                    <?php if(isset($_SESSION['customer_logged_in'])): ?>
                        <a href="customer/orders/place_order.php" class="btn hero-primary-btn btn-lg">
                            <i class="bi bi-cart-plus"></i> Start Shopping
                        </a>
                    <?php else: ?>
                        <a href="customer/register.php" class="btn hero-primary-btn btn-lg me-2">
                            <i class="bi bi-person-plus"></i> Register Now
                        </a>
                        <a href="customer/login.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-center">
                    <i class="bi bi-basket" style="font-size: 15rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold">Why Choose Us?</h2>
                    <p class="text-muted">We provide the best grocery shopping experience</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card p-4 text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h4>Free Delivery</h4>
                        <p class="text-muted">Free delivery within 5km radius. Affordable rates beyond that.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card p-4 text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-star"></i>
                        </div>
                        <h4>Quality Products</h4>
                        <p class="text-muted">Fresh and high-quality products sourced directly from trusted suppliers.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card p-4 text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-cash"></i>
                        </div>
                        <h4>Easy Payment</h4>
                        <p class="text-muted">Multiple payment options including online payment and cash on delivery.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold">Featured Products</h2>
                    <p class="text-muted">Check out our fresh selection of products</p>
                </div>
            </div>
            
            <?php if(count($featured_products) > 0): ?>
                <div class="row">
                    <?php foreach($featured_products as $product): ?>
                        <div class="col-md-3 mb-4">
                            <div class="card product-card">
                                <?php if($product['image']): ?>
                                    <img src="<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-secondary text-white d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <i class="bi bi-image" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars($product['category']); ?></small><br>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['quality']); ?></span>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="product-price">₹<?php echo number_format($product['price'], 2); ?></span>
                                        <small class="text-muted">Stock: <?php echo $product['available_quantity']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(isset($_SESSION['customer_logged_in'])): ?>
                    <div class="text-center mt-4">
                        <a href="customer/orders/place_order.php" class="btn btn-success btn-lg">
                            <i class="bi bi-cart-plus"></i> View All Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-4">
                        <a href="customer/register.php" class="btn btn-success btn-lg">
                            <i class="bi bi-person-plus"></i> Register to Shop
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-box-seam" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h4 class="mt-3">No Products Available</h4>
                    <p class="text-muted">Check back later for new products.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Categories Section -->
    <?php if(count($categories) > 0): ?>
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold">Shop by Category</h2>
                    <p class="text-muted">Browse our wide range of categories</p>
                </div>
            </div>
            <div class="row">
                <?php foreach($categories as $category): ?>
                    <div class="col-md-2 col-4 mb-4">
                        <div class="card text-center feature-card p-3">
                            <div class="feature-icon mb-2" style="font-size: 2rem;">
                                <i class="bi bi-tag"></i>
                            </div>
                            <h6><?php echo htmlspecialchars($category); ?></h6>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action -->
    <section class="py-5 bg-success text-white">
        <div class="container text-center">
            <h2 class="fw-bold mb-3">Ready to Start Shopping?</h2>
            <p class="lead mb-4">Register now and get fresh groceries delivered to your doorstep</p>
            <?php if(isset($_SESSION['customer_logged_in'])): ?>
                <a href="customer/orders/place_order.php" class="btn hero-primary-btn btn-lg">
                    <i class="bi bi-cart-plus"></i> Start Shopping
                </a>
            <?php else: ?>
                <a href="customer/register.php" class="btn hero-primary-btn btn-lg">
                    <i class="bi bi-person-plus"></i> Register Now
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Grocery Store</h5>
                    <p>Your one-stop online grocery shop. Fresh products delivered to your doorstep.</p>
                </div>
                <div class="col-md-6">
                    <h5>Contact Us</h5>
                    <p><i class="bi bi-telephone"></i> +91 9876543210</p>
                    <p><i class="bi bi-envelope"></i> support@grocery.com</p>
                    <?php if($shop_location): ?>
                        <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($shop_location['address']); ?></p>
                        <p><?php echo htmlspecialchars($shop_location['city']); ?> - <?php echo htmlspecialchars($shop_location['pin_code']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> Grocery Store. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>
