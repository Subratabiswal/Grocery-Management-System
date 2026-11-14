<?php
require_once 'includes/db.php';

$email = 'owner@grocery.com';
$password = 'admin123';
$name = 'Shop Owner';

try {
    // Check if owner already exists
    $stmt = $pdo->prepare("SELECT id FROM owner WHERE email = ?");
    $stmt->execute([$email]);
    $existing_owner = $stmt->fetch();
    
    if ($existing_owner) {
        // Update existing owner
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE owner SET name = ?, password = ? WHERE email = ?");
        $stmt->execute([$name, $password_hash, $email]);
        echo "Owner account updated successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
    } else {
        // Insert new owner
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO owner (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $password_hash]);
        echo "Owner account created successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
    }
    
    // Verify the setup
    $stmt = $pdo->prepare("SELECT * FROM owner WHERE email = ?");
    $stmt->execute([$email]);
    $owner = $stmt->fetch();
    
    echo "\nVerification:\n";
    echo "Owner ID: " . $owner['id'] . "\n";
    echo "Owner Name: " . $owner['name'] . "\n";
    echo "Owner Email: " . $owner['email'] . "\n";
    
    if (password_verify($password, $owner['password'])) {
        echo "Password verification: SUCCESS\n";
    } else {
        echo "Password verification: FAILED\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>