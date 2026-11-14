<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Edit Product Page
 * Allows owner to update product details
 */

$error = '';
$success = '';
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($product_id == 0) {
    header("Location: manage_products.php");
    exit();
}

// Get product details
try {
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if(!$product) {
        $_SESSION['error'] = 'Product not found';
        header("Location: manage_products.php");
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $isbn = trim($_POST['isbn']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $quality = trim($_POST['quality']);
    
    // Handle image upload
    $image = $product['image'];
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = '../../assets/uploads/';
        $new_image = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $new_image;
        
        $check = getimagesize($_FILES['image']['tmp_name']);
        if($check !== false) {
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = 'assets/uploads/' . $new_image;
            } else {
                $error = 'Failed to upload image.';
            }
        } else {
            $error = 'File is not an image.';
        }
    }
    
    // Validation
    if(empty($name) || empty($category) || empty($isbn) || empty($quantity) || empty($price)) {
        $error = 'Please fill in all required fields';
    } elseif($quantity < 0) {
        $error = 'Quantity cannot be negative';
    } elseif($price <= 0) {
        $error = 'Price must be greater than 0';
    } else {
        try {
            // Check if ISBN exists for another product
            $stmt = $pdo->prepare("SELECT id FROM product WHERE isbn = ? AND id != ?");
            $stmt->execute([$isbn, $product_id]);
            
            if($stmt->rowCount() > 0) {
                $error = 'Product with this ISBN already exists';
            } else {
                // Update product
                $stmt = $pdo->prepare("
                    UPDATE product 
                    SET name = ?, image = ?, category = ?, isbn = ?, quantity = ?, available_quantity = ?, price = ?, quality = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $image, $category, $isbn, $quantity, $quantity, $price, $quality, $product_id]);
                
                $success = 'Product updated successfully!';
                
                // Refresh product data
                $stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-pencil"></i> Edit Product</h2>
        <p class="text-muted">Update product details for: <?php echo htmlspecialchars($product['name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Product Details</h5>
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
                        <a href="manage_products.php" class="btn btn-sm btn-success ms-2">View Products</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Grains" <?php echo $product['category'] == 'Grains' ? 'selected' : ''; ?>>Grains</option>
                                <option value="Dairy" <?php echo $product['category'] == 'Dairy' ? 'selected' : ''; ?>>Dairy</option>
                                <option value="Vegetables" <?php echo $product['category'] == 'Vegetables' ? 'selected' : ''; ?>>Vegetables</option>
                                <option value="Fruits" <?php echo $product['category'] == 'Fruits' ? 'selected' : ''; ?>>Fruits</option>
                                <option value="Oil" <?php echo $product['category'] == 'Oil' ? 'selected' : ''; ?>>Oil</option>
                                <option value="Beverages" <?php echo $product['category'] == 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                <option value="Sweeteners" <?php echo $product['category'] == 'Sweeteners' ? 'selected' : ''; ?>>Sweeteners</option>
                                <option value="Snacks" <?php echo $product['category'] == 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                <option value="Spices" <?php echo $product['category'] == 'Spices' ? 'selected' : ''; ?>>Spices</option>
                                <option value="Other" <?php echo $product['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="isbn" class="form-label">ISBN/Serial Number *</label>
                            <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($product['isbn']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="quality" class="form-label">Quality</label>
                            <select class="form-select" id="quality" name="quality">
                                <option value="Standard" <?php echo $product['quality'] == 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="Premium" <?php echo $product['quality'] == 'Premium' ? 'selected' : ''; ?>>Premium</option>
                                <option value="Fresh" <?php echo $product['quality'] == 'Fresh' ? 'selected' : ''; ?>>Fresh</option>
                                <option value="Organic" <?php echo $product['quality'] == 'Organic' ? 'selected' : ''; ?>>Organic</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?php echo $product['quantity']; ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label">Price (₹) *</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $product['price']; ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="image" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Leave blank to keep current image</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Image:</label>
                        <?php if($product['image']): ?>
                            <img src="../../<?php echo $product['image']; ?>" class="image-preview" id="currentImage">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                <i class="bi bi-image" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <div id="imagePreviewContainer" style="display: none;">
                            <label class="form-label">New Image Preview:</label>
                            <img id="imagePreview" src="" class="image-preview">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_products.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Product Statistics</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Product ID:</strong> <?php echo $product['id']; ?>
                </div>
                <div class="mb-3">
                    <strong>Added Date:</strong> <?php echo date('M d, Y', strtotime($product['added_date'])); ?>
                </div>
                <div class="mb-3">
                    <strong>Total Quantity:</strong> <?php echo $product['quantity']; ?>
                </div>
                <div class="mb-3">
                    <strong>Available:</strong> <?php echo $product['available_quantity']; ?>
                </div>
                <div class="mb-3">
                    <strong>Price:</strong> ₹<?php echo number_format($product['price'], 2); ?>
                </div>
                
                <hr>
                
                <h6>Stock Status</h6>
                <?php if($product['available_quantity'] == 0): ?>
                    <div class="alert alert-danger">Out of Stock</div>
                <?php elseif($product['available_quantity'] < 10): ?>
                    <div class="alert alert-warning">Low Stock (<?php echo $product['available_quantity']; ?> left)</div>
                <?php else: ?>
                    <div class="alert alert-success">In Stock (<?php echo $product['available_quantity']; ?> available)</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
