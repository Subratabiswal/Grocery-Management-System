<?php
require_once 'includes/db.php';

try {
    // Check if owner table exists and has data
    $stmt = $pdo->query("SELECT * FROM owner");
    $owner = $stmt->fetch();
    
    if ($owner) {
        echo "Owner account found:\n";
        echo "ID: " . $owner['id'] . "\n";
        echo "Name: " . $owner['name'] . "\n";
        echo "Email: " . $owner['email'] . "\n";
        echo "Password hash: " . $owner['password'] . "\n";
        
        // Test password verification
        if (password_verify('admin123', $owner['password'])) {
            echo "Password verification: SUCCESS\n";
        } else {
            echo "Password verification: FAILED\n";
        }
    } else {
        echo "No owner account found in database.\n";
        echo "You need to import the database schema.\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>