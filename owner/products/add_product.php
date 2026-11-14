<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Add Product Page
 * Allows owner to add new products to the store
 */

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $isbn = trim($_POST['isbn']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $quality = trim($_POST['quality']);
    
    // Handle image upload
    $image = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = '../../assets/uploads/';
        $image = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $image;
        
        // Check if image file is actual image
        $check = getimagesize($_FILES['image']['tmp_name']);
        if($check !== false) {
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = 'assets/uploads/' . $image;
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
            // Check if ISBN already exists
            $stmt = $pdo->prepare("SELECT id FROM product WHERE isbn = ?");
            $stmt->execute([$isbn]);
            
            if($stmt->rowCount() > 0) {
                $error = 'Product with this ISBN already exists';
            } else {
                // Insert product
                $image_path = !empty($image) ? $image : 'assets/images/default-product.jpg';
                $stmt = $pdo->prepare("
                    INSERT INTO product (name, image, category, isbn, quantity, available_quantity, price, quality) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $image_path, $category, $isbn, $quantity, $quantity, $price, $quality]);
                
                $success = 'Product added successfully!';
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-plus-circle"></i> Add New Product</h2>
        <p class="text-muted">Add a new product to your store inventory</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Product Details</h5>
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
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Grains">Grains</option>
                                <option value="Dairy">Dairy</option>
                                <option value="Vegetables">Vegetables</option>
                                <option value="Fruits">Fruits</option>
                                <option value="Oil">Oil</option>
                                <option value="Beverages">Beverages</option>
                                <option value="Sweeteners">Sweeteners</option>
                                <option value="Snacks">Snacks</option>
                                <option value="Spices">Spices</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="isbn" class="form-label">ISBN/Serial Number *</label>
                            <input type="text" class="form-control" id="isbn" name="isbn" required>
                            <small class="text-muted">Unique identifier for the product</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="quality" class="form-label">Quality</label>
                            <select class="form-select" id="quality" name="quality">
                                <option value="Standard">Standard</option>
                                <option value="Premium">Premium</option>
                                <option value="Fresh">Fresh</option>
                                <option value="Organic">Organic</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="price" class="form-label">Price (₹) *</label>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="image" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Optional: Upload product image</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div id="imagePreviewContainer" style="display: none;">
                            <label class="form-label">Image Preview:</label>
                            <img id="imagePreview" src="" class="image-preview">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_products.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Guidelines</h5>
            </div>
            <div class="card-body">
                <h6>Product Information</h6>
                <ul>
                    <li>Enter accurate product details</li>
                    <li>Use unique ISBN for each product</li>
                    <li>Set appropriate pricing</li>
                    <li>Upload clear product images</li>
                </ul>
                
                <h6>Category Options</h6>
                <ul>
                    <li>Grains: Rice, Wheat, Flour</li>
                    <li>Dairy: Milk, Cheese, Eggs</li>
                    <li>Vegetables: Fresh vegetables</li>
                    <li>Fruits: Fresh fruits</li>
                    <li>Oil: Cooking oils</li>
                    <li>Beverages: Tea, Coffee, Juices</li>
                </ul>
                
                <h6>Quality Standards</h6>
                <ul>
                    <li>Standard: Regular quality</li>
                    <li>Premium: High quality</li>
                    <li>Fresh: Newly stocked</li>
                    <li>Organic: Organic products</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
