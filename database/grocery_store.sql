-- Grocery Store Database Schema
-- Created for Online Grocery Shop Management System

-- Create Database
CREATE DATABASE IF NOT EXISTS grocery_store;
USE grocery_store;

-- 1. Owner Table
CREATE TABLE IF NOT EXISTS owner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Product Table
CREATE TABLE IF NOT EXISTS product (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    image VARCHAR(255),
    category VARCHAR(100),
    isbn VARCHAR(50) UNIQUE,
    quantity INT DEFAULT 0,
    available_quantity INT DEFAULT 0,
    price DECIMAL(10, 2) NOT NULL,
    quality VARCHAR(50),
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Customer Table
CREATE TABLE IF NOT EXISTS customer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Order Product Table
CREATE TABLE IF NOT EXISTS order_product (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ordered', 'delivered', 'cancelled', 'returned') DEFAULT 'ordered',
    payment_method ENUM('online', 'cash_on_delivery') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    total_amount DECIMAL(10, 2) NOT NULL,
    delivery_fee DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE CASCADE
);

-- 5. Location Table
CREATE TABLE IF NOT EXISTS location (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('owner', 'customer') NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    mobile_no VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    pin_code VARCHAR(10) NOT NULL,
    landmark TEXT,
    area TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    distance_from_shop DECIMAL(10, 2) DEFAULT 0.00,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Shop Location Table (for delivery calculation)
CREATE TABLE IF NOT EXISTS shop_location (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_name VARCHAR(200) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    pin_code VARCHAR(10) NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Order Items Table (for multiple items in one order)
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price_per_item DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES order_product(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
);

-- Insert Default Owner (password: admin123)
INSERT INTO owner (name, email, password) VALUES 
('Shop Owner', 'owner@grocery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert Default Shop Location
INSERT INTO shop_location (shop_name, address, city, pin_code, latitude, longitude) VALUES 
('Main Grocery Store', '123 Main Street', 'Mumbai', '400001', 19.0760, 72.8777);

-- Insert Sample Products
INSERT INTO product (name, image, category, isbn, quantity, available_quantity, price, quality) VALUES 
('Rice', 'uploads/rice.jpg', 'Grains', 'ISBN001', 100, 100, 50.00, 'Premium'),
('Wheat Flour', 'uploads/wheat.jpg', 'Grains', 'ISBN002', 80, 80, 40.00, 'Standard'),
('Sugar', 'uploads/sugar.jpg', 'Sweeteners', 'ISBN003', 150, 150, 45.00, 'Premium'),
('Milk', 'uploads/milk.jpg', 'Dairy', 'ISBN004', 200, 200, 60.00, 'Fresh'),
('Eggs', 'uploads/eggs.jpg', 'Dairy', 'ISBN005', 300, 300, 80.00, 'Farm Fresh'),
('Tomatoes', 'uploads/tomato.jpg', 'Vegetables', 'ISBN006', 50, 50, 30.00, 'Fresh'),
('Onions', 'uploads/onion.jpg', 'Vegetables', 'ISBN007', 60, 60, 35.00, 'Fresh'),
('Potatoes', 'uploads/potato.jpg', 'Vegetables', 'ISBN008', 70, 70, 25.00, 'Fresh'),
('Apples', 'uploads/apple.jpg', 'Fruits', 'ISBN009', 40, 40, 150.00, 'Premium'),
('Bananas', 'uploads/banana.jpg', 'Fruits', 'ISBN010', 100, 100, 40.00, 'Fresh'),
('Cooking Oil', 'uploads/oil.jpg', 'Oil', 'ISBN011', 60, 60, 120.00, 'Refined'),
('Tea', 'uploads/tea.jpg', 'Beverages', 'ISBN012', 90, 90, 200.00, 'Premium');

-- Create Indexes for better performance
CREATE INDEX idx_product_category ON product(category);
CREATE INDEX idx_order_customer ON order_product(customer_id);
CREATE INDEX idx_order_status ON order_product(status);
CREATE INDEX idx_order_date ON order_product(order_date);
CREATE INDEX idx_location_user ON location(user_id, user_type);
