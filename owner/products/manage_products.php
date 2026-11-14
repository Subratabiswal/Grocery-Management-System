<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Manage Products Page
 * Allows owner to view, search, edit, and delete products
 */

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM product WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success'] = 'Product deleted successfully!';
        header("Location: manage_products.php");
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Cannot delete product: ' . $e->getMessage();
    }
}

try {
    // Get products with optional search
    if($search) {
        $stmt = $pdo->prepare("
            SELECT * FROM product 
            WHERE name LIKE ? OR category LIKE ? OR isbn LIKE ? 
            ORDER BY added_date DESC
        ");
        $search_param = "%$search%";
        $stmt->execute([$search_param, $search_param, $search_param]);
    } else {
        $stmt = $pdo->query("SELECT * FROM product ORDER BY added_date DESC");
    }
    $products = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-box-seam"></i> Manage Products</h2>
        <p class="text-muted">View, edit, and manage your product inventory</p>
    </div>
</div>

<!-- Search and Actions -->
<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Search
                </button>
                <?php if($search): ?>
                    <a href="manage_products.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <a href="add_product.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Add New Product
        </a>
    </div>
</div>

<!-- Products Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Product List <?php echo $search ? "(Search: '$search')" : ''; ?></h5>
            </div>
            <div class="card-body">
                <?php if(count($products) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>ISBN</th>
                                    <th>Quality</th>
                                    <th>Price</th>
                                    <th>Available</th>
                                    <th>Added Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $product): ?>
                                    <tr>
                                        <td>
                                            <?php if($product['image']): ?>
                                                <img src="../../<?php echo $product['image']; ?>" class="rounded" width="50" height="50" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($product['category']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['isbn']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($product['quality']); ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-success">₹<?php echo number_format($product['price'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php if($product['available_quantity'] > 10): ?>
                                                <span class="badge bg-success"><?php echo $product['available_quantity']; ?></span>
                                            <?php elseif($product['available_quantity'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $product['available_quantity']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($product['added_date'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="manage_products.php?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger delete-btn" title="Delete">
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
                        <p class="text-muted">Showing <?php echo count($products); ?> product(s)</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-box-seam" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="mt-3">No Products Found</h4>
                        <p class="text-muted">
                            <?php if($search): ?>
                                No products match your search. <a href="manage_products.php">Clear search</a>
                            <?php else: ?>
                                You haven't added any products yet. <a href="add_product.php">Add your first product</a>
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
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>Total Products</h5>
                <h3><?php echo count($products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>In Stock</h5>
                <h3><?php echo count(array_filter($products, fn($p) => $p['available_quantity'] > 0)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>Low Stock</h5>
                <h3><?php echo count(array_filter($products, fn($p) => $p['available_quantity'] > 0 && $p['available_quantity'] < 10)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>Out of Stock</h5>
                <h3><?php echo count(array_filter($products, fn($p) => $p['available_quantity'] == 0)); ?></h3>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
