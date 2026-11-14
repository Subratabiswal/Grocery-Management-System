<?php
require_once '../../includes/db.php';
require_once '../../includes/owner_header.php';

/**
 * Product Image Management Page
 * Allows owner to manage product images separately
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
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = '../../assets/uploads/';
        $new_image = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $new_image;
        
        $check = getimagesize($_FILES['image']['tmp_name']);
        if($check !== false) {
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                try {
                    $image_path = 'assets/uploads/' . $new_image;
                    $stmt = $pdo->prepare("UPDATE product SET image = ? WHERE id = ?");
                    $stmt->execute([$image_path, $product_id]);
                    
                    $success = 'Product image updated successfully!';
                    
                    // Refresh product data
                    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to upload image.';
            }
        } else {
            $error = 'File is not an image.';
        }
    } else {
        $error = 'Please select an image file.';
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-image"></i> Manage Product Image</h2>
        <p class="text-muted">Update image for: <?php echo htmlspecialchars($product['name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Current Image</h5>
            </div>
            <div class="card-body text-center">
                <?php if($product['image']): ?>
                    <img src="../../<?php echo $product['image']; ?>" class="img-fluid rounded" style="max-height: 400px;">
                <?php else: ?>
                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="height: 400px;">
                        <div>
                            <i class="bi bi-image" style="font-size: 4rem;"></i>
                            <p class="mt-3">No Image Set</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Upload New Image</h5>
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
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="image" class="form-label">Select New Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                        <small class="text-muted">Supported formats: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="mb-3">
                        <div id="imagePreviewContainer" style="display: none;">
                            <label class="form-label">Preview:</label>
                            <img id="imagePreview" src="" class="img-fluid rounded">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload"></i> Upload Image
                        </button>
                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Edit Product
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Image Guidelines</h5>
            </div>
            <div class="card-body">
                <ul>
                    <li>Use high-quality images (minimum 500x500 pixels)</li>
                    <li>Recommended aspect ratio: 1:1 (square)</li>
                    <li>File size should be under 2MB</li>
                    <li>Use clear, well-lit product photos</li>
                    <li>Show product packaging clearly</li>
                    <li>Avoid blurry or distorted images</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/owner_footer.php'; ?>
