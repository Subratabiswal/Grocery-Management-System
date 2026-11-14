<?php
require_once '../../includes/db.php';
require_once '../../includes/security.php';

if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(isset($_SERVER['DOCUMENT_ROOT'])) {
    $projectRoot = str_replace('\\', '/', dirname(dirname(__DIR__)));
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

if(!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function calculateDeliveryFee($distance) {
    $distance = (float) $distance;
    return $distance > 5 ? ($distance - 5) * 10 : 0;
}

function redirectWithFlash($baseUrl, $type, $message) {
    $_SESSION[$type] = $message;
    header("Location: " . $baseUrl . "/customer/orders/place_order.php");
    exit();
}

function fetchCustomerLocation($pdo, $customerId) {
    $stmt = $pdo->prepare("SELECT * FROM location WHERE user_id = ? AND user_type = 'customer' ORDER BY is_default DESC LIMIT 1");
    $stmt->execute([$customerId]);
    return $stmt->fetch();
}

$customer_id = (int) $_SESSION['customer_id'];
ensureCsrfToken();

try {
    $customer_location = fetchCustomerLocation($pdo, $customer_id);
} catch(PDOException $e) {
    $customer_location = null;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isValidCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithFlash($baseUrl, 'error', 'Your session expired. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if($action === 'add_to_cart') {
            $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

            if(!$customer_location) {
                redirectWithFlash($baseUrl, 'error', 'Please set your delivery location first.');
            }

            // Check if customer location is within 10 km radius
            if($customer_location && $customer_location['distance_from_shop'] > 10) {
                redirectWithFlash($baseUrl, 'error', 'Your location is beyond our 10 km delivery radius. Orders can only be placed within 10 km of the shop.');
            }

            if($product_id < 1 || $quantity < 1) {
                redirectWithFlash($baseUrl, 'error', 'Please choose a valid product and quantity.');
            }

            $stmt = $pdo->prepare("SELECT id, name, available_quantity FROM product WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if(!$product) {
                redirectWithFlash($baseUrl, 'error', 'Product not found.');
            }

            $existingQuantity = isset($_SESSION['cart'][$product_id]) ? (int) $_SESSION['cart'][$product_id] : 0;
            $newQuantity = $existingQuantity + $quantity;

            if($newQuantity > (int) $product['available_quantity']) {
                redirectWithFlash(
                    $baseUrl,
                    'error',
                    'Only ' . $product['available_quantity'] . ' item(s) available for ' . $product['name'] . '.'
                );
            }

            $_SESSION['cart'][$product_id] = $newQuantity;
            redirectWithFlash($baseUrl, 'success', $product['name'] . ' added to cart.');
        }

        if($action === 'update_cart') {
            $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

            if($product_id < 1 || !isset($_SESSION['cart'][$product_id])) {
                redirectWithFlash($baseUrl, 'error', 'Cart item not found.');
            }

            if($quantity < 1) {
                unset($_SESSION['cart'][$product_id]);
                redirectWithFlash($baseUrl, 'success', 'Item removed from cart.');
            }

            $stmt = $pdo->prepare("SELECT name, available_quantity FROM product WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if(!$product) {
                unset($_SESSION['cart'][$product_id]);
                redirectWithFlash($baseUrl, 'error', 'Product is no longer available and was removed from cart.');
            }

            if($quantity > (int) $product['available_quantity']) {
                redirectWithFlash(
                    $baseUrl,
                    'error',
                    'Only ' . $product['available_quantity'] . ' item(s) available for ' . $product['name'] . '.'
                );
            }

            $_SESSION['cart'][$product_id] = $quantity;
            redirectWithFlash($baseUrl, 'success', 'Cart updated successfully.');
        }

        if($action === 'remove_from_cart') {
            $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

            if($product_id > 0 && isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
                redirectWithFlash($baseUrl, 'success', 'Item removed from cart.');
            }

            redirectWithFlash($baseUrl, 'error', 'Cart item not found.');
        }

        if($action === 'place_order') {
            $payment_method = trim($_POST['payment_method'] ?? '');
            $valid_payment_methods = ['online', 'cash_on_delivery'];

            if(empty($_SESSION['cart'])) {
                redirectWithFlash($baseUrl, 'error', 'Your cart is empty.');
            }

            if(!$customer_location) {
                redirectWithFlash($baseUrl, 'error', 'Please set your delivery location first.');
            }

            // Check if customer location is within 10 km radius
            if($customer_location && $customer_location['distance_from_shop'] > 10) {
                redirectWithFlash($baseUrl, 'error', 'Your location is beyond our 10 km delivery radius. Orders can only be placed within 10 km of the shop.');
            }

            if(!in_array($payment_method, $valid_payment_methods, true)) {
                redirectWithFlash($baseUrl, 'error', 'Please choose a valid payment method.');
            }

            $productIds = array_map('intval', array_keys($_SESSION['cart']));
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM product WHERE id IN ($placeholders) FOR UPDATE");
            $stmt->execute($productIds);
            $productsById = [];
            foreach($stmt->fetchAll() as $productRow) {
                $productsById[(int) $productRow['id']] = $productRow;
            }

            foreach($_SESSION['cart'] as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;

                if(!isset($productsById[$productId])) {
                    throw new Exception('One of the products in your cart is no longer available.');
                }

                if($quantity < 1) {
                    throw new Exception('Cart contains an invalid quantity.');
                }

                if((int) $productsById[$productId]['available_quantity'] < $quantity) {
                    throw new Exception(
                        'Insufficient stock for ' . $productsById[$productId]['name'] . '. Only ' .
                        $productsById[$productId]['available_quantity'] . ' item(s) available.'
                    );
                }
            }

            $delivery_fee_total = round(calculateDeliveryFee($customer_location['distance_from_shop']), 2);
            $orderSubtotal = 0;
            $totalQuantity = 0;
            $cartProductIds = array_map('intval', array_keys($_SESSION['cart']));
            $primaryProductId = $cartProductIds[0];

            foreach($_SESSION['cart'] as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;
                $product = $productsById[$productId];

                $orderSubtotal += round($product['price'] * $quantity, 2);
                $totalQuantity += $quantity;
            }

            $orderTotal = round($orderSubtotal + $delivery_fee_total, 2);

            $insertOrderStmt = $pdo->prepare("
                INSERT INTO order_product (product_id, customer_id, quantity, payment_method, total_amount, delivery_fee, status)
                VALUES (?, ?, ?, ?, ?, ?, 'ordered')
            ");
            $insertOrderStmt->execute([
                $primaryProductId,
                $customer_id,
                $totalQuantity,
                $payment_method,
                $orderTotal,
                $delivery_fee_total
            ]);

            $createdOrderId = (int) $pdo->lastInsertId();

            $insertItemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price_per_item, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $updateStockStmt = $pdo->prepare("UPDATE product SET available_quantity = ? WHERE id = ?");

            foreach($_SESSION['cart'] as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;
                $product = $productsById[$productId];
                $lineSubtotal = round($product['price'] * $quantity, 2);

                $insertItemStmt->execute([
                    $createdOrderId,
                    $productId,
                    $quantity,
                    $product['price'],
                    $lineSubtotal
                ]);

                $newQuantity = (int) $product['available_quantity'] - $quantity;
                $updateStockStmt->execute([$newQuantity, $productId]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];

            redirectWithFlash(
                $baseUrl,
                'success',
                'Order placed successfully. Order ID: #' . $createdOrderId
            );
        }
    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirectWithFlash($baseUrl, 'error', $e->getMessage());
    } catch(PDOException $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Checkout action failed: ' . $e->getMessage());
        redirectWithFlash($baseUrl, 'error', 'A database error occurred. Please try again.');
    }
}

require_once '../../includes/customer_header.php';

try {
    $stmt = $pdo->query("SELECT * FROM product WHERE available_quantity > 0 ORDER BY name ASC");
    $products = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM product WHERE available_quantity > 0 ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $categories = [];
}

$cart_items = [];
$cart_count = 0;
$cart_subtotal = 0;
$cart_delivery_fee = $customer_location ? calculateDeliveryFee($customer_location['distance_from_shop']) : 0;
$cart_grand_total = 0;

if(!empty($_SESSION['cart'])) {
    $productIds = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id IN ($placeholders)");
    $stmt->execute($productIds);

    $cartProducts = [];
    foreach($stmt->fetchAll() as $cartProduct) {
        $cartProducts[(int) $cartProduct['id']] = $cartProduct;
    }

    foreach($_SESSION['cart'] as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;

        if(!isset($cartProducts[$productId])) {
            continue;
        }

        $product = $cartProducts[$productId];
        $lineSubtotal = round($product['price'] * $quantity, 2);

        $cart_items[] = [
            'id' => $productId,
            'name' => $product['name'],
            'image' => $product['image'],
            'category' => $product['category'],
            'price' => $product['price'],
            'available_quantity' => $product['available_quantity'],
            'quantity' => $quantity,
            'subtotal' => $lineSubtotal
        ];

        $cart_count += $quantity;
        $cart_subtotal += $lineSubtotal;
    }
}

$cart_subtotal = round($cart_subtotal, 2);
$cart_delivery_fee = round($cart_delivery_fee, 2);
$cart_grand_total = round($cart_subtotal + $cart_delivery_fee, 2);
$locationOutOfRange = $customer_location && $customer_location['distance_from_shop'] > 10;
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-cart-plus"></i> Shop &amp; Cart</h2>
        <p class="text-muted">Add multiple products to cart, then place one checkout order.</p>
    </div>
</div>

<?php if(!$customer_location): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Location Required:</strong> Please set your delivery location before adding items to cart or placing an order.
        <a href="<?php echo $baseUrl; ?>/customer/location.php" class="btn btn-sm btn-primary ms-2">Set Location Now</a>
    </div>
<?php else: ?>
    <div class="delivery-info mb-4">
        <i class="bi bi-geo-alt"></i>
        <strong>Delivery Location:</strong> <?php echo htmlspecialchars($customer_location['city']); ?>,
        <?php echo htmlspecialchars($customer_location['pin_code']); ?>
        (Distance: <?php echo $customer_location['distance_from_shop']; ?> km)
        <?php if($customer_location['distance_from_shop'] > 10): ?>
            <span class="out-of-range ms-2"><i class="bi bi-exclamation-triangle"></i> Beyond 10 km delivery radius - Orders cannot be placed</span>
        <?php elseif($cart_delivery_fee > 0): ?>
            <span class="paid-delivery ms-2"><i class="bi bi-info-circle"></i> ₹<?php echo number_format($cart_delivery_fee, 2); ?> delivery fee per checkout</span>
        <?php else: ?>
            <span class="free-delivery ms-2"><i class="bi bi-check-circle"></i> Free Delivery</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row mb-4" id="cartSummary">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cart3"></i> My Cart</h5>
                <span class="badge bg-light text-dark"><?php echo $cart_count; ?> item(s)</span>
            </div>
            <div class="card-body">
                <?php if(count($cart_items) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cart_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if($item['image']): ?>
                                                    <img src="../../<?php echo $item['image']; ?>" class="rounded" width="48" height="48" style="object-fit: cover;">
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                        <td style="min-width: 180px;">
                                            <form method="POST" class="d-flex gap-2">
                                                <?php echo csrfInput(); ?>
                                                <input type="hidden" name="action" value="update_cart">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    name="quantity"
                                                    min="1"
                                                    max="<?php echo $item['available_quantity']; ?>"
                                                    value="<?php echo $item['quantity']; ?>"
                                                    required
                                                >
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                                            </form>
                                            <small class="text-muted">Available: <?php echo $item['available_quantity']; ?></small>
                                        </td>
                                        <td><strong>₹<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                        <td>
                                            <form method="POST">
                                                <?php echo csrfInput(); ?>
                                                <input type="hidden" name="action" value="remove_from_cart">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row justify-content-end mt-4">
                        <div class="col-md-5 col-lg-4">
                            <div class="border rounded p-3 bg-light">
                                <p class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <strong>₹<?php echo number_format($cart_subtotal, 2); ?></strong>
                                </p>
                                <p class="d-flex justify-content-between mb-2">
                                    <span>Delivery Fee</span>
                                    <strong>₹<?php echo number_format($cart_delivery_fee, 2); ?></strong>
                                </p>
                                <p class="d-flex justify-content-between mb-3">
                                    <span>Grand Total</span>
                                    <strong>₹<?php echo number_format($cart_grand_total, 2); ?></strong>
                                </p>

                                <form method="POST">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="action" value="place_order">
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method</label>
                                        <select class="form-select" id="payment_method" name="payment_method" required <?php echo $locationOutOfRange ? 'disabled' : ''; ?>>
                                            <option value="online">Online Payment</option>
                                            <option value="cash_on_delivery">Cash on Delivery</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100" <?php echo $locationOutOfRange ? 'disabled' : ''; ?>>
                                        <i class="bi bi-check-circle"></i> Place Order
                                    </button>
                                    <?php if($locationOutOfRange): ?>
                                        <div class="alert alert-danger mt-2">
                                            <i class="bi bi-exclamation-triangle"></i> Orders cannot be placed from locations beyond 10 km radius.
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <small class="text-muted d-block mt-2">
                                    Delivery fee is applied once for the full checkout.
                                </small>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-x" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-2 mb-0">Your cart is empty. Add products below.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Filter by Category:</h6>
                <div class="btn-group flex-wrap" role="group">
                    <button type="button" class="btn btn-outline-success category-filter active" data-category="all">All</button>
                    <?php foreach($categories as $category): ?>
                        <button type="button" class="btn btn-outline-success category-filter" data-category="<?php echo htmlspecialchars($category); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="input-group">
            <input type="text" class="form-control" id="searchInput" placeholder="Search products...">
            <button class="btn btn-outline-secondary" type="button">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
</div>

<div class="row">
    <?php if(count($products) > 0): ?>
        <?php foreach($products as $product): ?>
            <?php $productDeliveryFee = $customer_location ? calculateDeliveryFee($customer_location['distance_from_shop']) : 0; ?>
            <div class="col-md-4 col-lg-3 mb-4 product-card-container" data-category="<?php echo htmlspecialchars($product['category']); ?>">
                <div class="card product-card h-100">
                    <?php if($product['image']): ?>
                        <img src="../../<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <div class="card-img-top bg-secondary text-white d-flex align-items-center justify-content-center" style="height: 200px;">
                            <i class="bi bi-image" style="font-size: 3rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title product-name"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text">
                            <small class="text-muted product-category"><?php echo htmlspecialchars($product['category']); ?></small><br>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($product['quality']); ?></span>
                        </p>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold text-success">₹<?php echo number_format($product['price'], 2); ?></span>
                            <small class="text-muted">Stock: <?php echo $product['available_quantity']; ?></small>
                        </div>
                        <button type="button" class="btn btn-success mt-auto w-100" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $product['id']; ?>" <?php echo (!$customer_location || $locationOutOfRange) ? 'disabled' : ''; ?>>
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="orderModal<?php echo $product['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add to Cart: <?php echo htmlspecialchars($product['name']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <?php echo csrfInput(); ?>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                                <?php if($product['image']): ?>
                                    <img src="../../<?php echo $product['image']; ?>" class="img-fluid rounded mb-3" style="max-height: 150px;">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">Price per item</label>
                                    <div><strong>₹<?php echo number_format($product['price'], 2); ?></strong></div>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity_<?php echo $product['id']; ?>" class="form-label">Quantity</label>
                                    <input
                                        type="number"
                                        class="form-control quantity-input"
                                        id="quantity_<?php echo $product['id']; ?>"
                                        name="quantity"
                                        min="1"
                                        max="<?php echo $product['available_quantity']; ?>"
                                        value="1"
                                        data-price="<?php echo htmlspecialchars($product['price']); ?>"
                                        data-delivery-fee="<?php echo htmlspecialchars($productDeliveryFee); ?>"
                                        data-target="totalAmount<?php echo $product['id']; ?>"
                                        required
                                    >
                                    <small class="text-muted">Available: <?php echo $product['available_quantity']; ?></small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Checkout Delivery Fee</label>
                                    <?php if($productDeliveryFee > 0): ?>
                                        <div class="text-danger">₹<?php echo number_format($productDeliveryFee, 2); ?></div>
                                    <?php else: ?>
                                        <div class="text-success">FREE</div>
                                    <?php endif; ?>
                                </div>

                                <div class="alert alert-info mb-0">
                                    <strong>Estimated checkout total if this is the only cart item:</strong>
                                    <span id="totalAmount<?php echo $product['id']; ?>">₹<?php echo number_format($product['price'] + $productDeliveryFee, 2); ?></span>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="bi bi-box-seam" style="font-size: 4rem; color: #dee2e6;"></i>
                <h4 class="mt-3">No Products Available</h4>
                <p class="text-muted">Check back later for new products.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.category-filter').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelectorAll('.category-filter').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');

        const category = this.getAttribute('data-category');
        const cards = document.querySelectorAll('.product-card-container');

        cards.forEach(card => {
            if (category === 'all' || card.getAttribute('data-category') === category) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const activeCategoryButton = document.querySelector('.category-filter.active');
        const activeCategory = activeCategoryButton ? activeCategoryButton.getAttribute('data-category') : 'all';

        document.querySelectorAll('.product-card-container').forEach(card => {
            const productName = card.querySelector('.product-name').textContent.toLowerCase();
            const productCategory = card.querySelector('.product-category').textContent.toLowerCase();
            const matchesSearch = productName.includes(searchTerm) || productCategory.includes(searchTerm);
            const matchesCategory = activeCategory === 'all' || card.getAttribute('data-category') === activeCategory;

            card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });
    });
}

document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('input', function() {
        const quantity = parseInt(this.value, 10) || 1;
        const price = parseFloat(this.dataset.price || '0');
        const deliveryFee = parseFloat(this.dataset.deliveryFee || '0');
        const target = document.getElementById(this.dataset.target);

        if (target) {
            const total = (price * quantity) + deliveryFee;
            target.textContent = '₹' + total.toFixed(2);
        }
    });
});
</script>

<?php require_once '../../includes/customer_footer.php'; ?>
