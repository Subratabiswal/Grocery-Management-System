# Grocery-Management-System
# Online Grocery Store Management System

A complete PHP-based grocery store management system with owner and customer portals, featuring location-based delivery pricing, order management, and comprehensive reporting.

<img width="1562" height="720" alt="Screenshot 2026-11-14 231956" src="https://github.com/user-attachments/assets/ede9ee4a-baa3-4766-8a05-d8242e3b60d1" />


## Features

### For Shop Owner
- **Dashboard**: Real-time statistics of products, customers, orders, and revenue
- **Product Management**: Add, edit, delete, and manage products with images
- **Customer Management**: View and manage customer accounts
- **Order Management**: View daily orders, update order status, track deliveries
- **Reports**: Daily reports with total orders, successful deliveries, pending orders, and revenue
- **Location Settings**: Set shop location for delivery distance calculations

### For Customers
- **User Registration**: Easy registration with name, email, phone, and password
- **Product Browsing**: View available products with images, categories, and prices
- **Order Placement**: Add products to cart and place orders
- **Location-Based Delivery**: 
  - Free delivery within 5km radius
  - ₹10 per km for distances beyond 5km
- **Payment Options**: Online payment or Cash on Delivery
- **Order History**: View all past orders with status tracking
- **Bill Download**: Download and print detailed order bills

## Technology Stack

- **Backend**: PHP (Core PHP, no frameworks)
- **Database**: MySQL
- **Frontend**: HTML, CSS, Bootstrap 5
- **JavaScript**: Vanilla JavaScript (no frameworks)
- **Icons**: Bootstrap Icons

## Project Structure

```
store/
├── assets/
│   ├── css/
│   │   └── style.css          # Custom styles
│   ├── js/
│   │   └── script.js          # Custom JavaScript
│   ├── images/                # Static images
│   └── uploads/               # Uploaded product images
├── database/
│   └── grocery_store.sql      # Database schema
├── includes/
│   ├── db.php                 # Database connection
│   ├── header.php             # Common header
│   ├── footer.php             # Common footer
│   ├── owner_header.php       # Owner panel header
│   ├── owner_footer.php       # Owner panel footer
│   ├── customer_header.php    # Customer panel header
│   └── customer_footer.php     # Customer panel footer
├── owner/
│   ├── login.php              # Owner login
│   ├── logout.php             # Owner logout
│   ├── location.php           # Shop location settings
│   ├── dashboard/
│   │   └── index.php          # Owner dashboard
│   ├── products/
│   │   ├── add_product.php    # Add new product
│   │   ├── manage_products.php # Manage products
│   │   ├── edit_product.php   # Edit product
│   │   └── image_product.php   # Manage product images
│   ├── customers/
│   │   ├── add_customer.php   # Add customer
│   │   ├── manage_customer.php # Manage customers
│   │   └── edit_customer.php  # Edit customer
│   └── reports/
│       └── index.php          # Daily reports
├── customer/
│   ├── login.php              # Customer login
│   ├── register.php           # Customer registration
│   ├── logout.php             # Customer logout
│   ├── location.php           # Customer location settings
│   ├── dashboard/
│   │   └── index.php          # Customer dashboard
│   ├── orders/
│   │   └── place_order.php    # Place orders
│   └── history/     
│       ├── index.php          # Order history
│       └── download_bill.php   # Download bill
├── index.php                  # Main landing page
└── README.md                  # This file
```

## Installation Instructions

### Prerequisites
- XAMPP (or any PHP/MySQL server)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Step-by-Step Setup

1. **Install XAMPP**
   - Download and install XAMPP from https://www.apachefriends.org/
   - Start Apache and MySQL services from XAMPP Control Panel

2. **Setup Project Files**
   - Copy the entire `store` folder to `C:\xampp\htdocs\`
   - Ensure the folder structure is maintained

3. **Create Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "New" to create a new database
   - Name the database: `grocery_store`
   - Click "Create"

4. **Import Database Schema**
   - Select the `grocery_store` database
   - Click "Import" tab
   - Choose the file: `database/grocery_store.sql`
   - Click "Go" to import

5. **Configure Database Connection**
   - Open `includes/db.php`
   - Verify the database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'grocery_store';
     $username = 'root';
     $password = '';
     ```
   - Adjust if your MySQL credentials differ

6. **Set File Permissions**
   - Ensure the `assets/uploads/` folder has write permissions
   - Right-click `uploads` folder → Properties → Security
   - Grant write permissions for PHP/Apache user

7. **Access the Application**
   - Open browser and navigate to: http://localhost/store/
   - You should see the landing page

## Default Credentials

### Owner Account
- **Email**: owner@grocery.com
- **Password**: admin123

### Customer Registration
- Customers can register themselves using the registration form
- Or the owner can manually add customers from the owner panel

## Usage Guide

### For Shop Owner

1. **Login as Owner**
   - Go to http://localhost/store/owner/login.php
   - Enter default credentials

2. **Set Shop Location**
   - Navigate to "Location Settings" from sidebar
   - Enter shop address and coordinates
   - This is essential for delivery fee calculation

3. **Add Products**
   - Go to "Add Product" from sidebar
   - Fill in product details (name, category, price, quantity, etc.)
   - Upload product image (optional)
   - Click "Add Product"

4. **Manage Products**
   - View all products from "Manage Products"
   - Edit or delete products as needed
   - Update stock quantities

5. **View Reports**
   - Access "Reports" from sidebar
   - Filter by date to view daily order statistics
   - Update order status (Ordered → Delivered/Cancelled)

### For Customers

1. **Register/Login**
   - Go to http://localhost/store/customer/register.php
   - Fill in registration details
   - Or login with existing credentials

2. **Set Delivery Location**
   - After login, navigate to "My Location"
   - Enter delivery address
   - Use "Get Current Location" for automatic coordinates
   - This is required before placing orders

3. **Browse Products**
   - Go to "Place Order" from dashboard
   - Browse products by category
   - View product details and prices

4. **Place Order**
   - Select products and quantity
   - Choose payment method (Online/Cash on Delivery)
   - Delivery fee is calculated based on distance
   - Confirm order

5. **View Order History**
   - Access "Order History" from dashboard
   - View all past orders with status
   - Download bills for delivered orders

## Delivery Fee Calculation

- **Free Delivery**: Within 5km radius from shop
- **Paid Delivery**: ₹10 per km for distances beyond 5km
- **Formula**: `Delivery Fee = (Distance - 5) × 10` (if Distance > 5km)

## Database Schema

### Tables Created
1. **owner** - Shop owner credentials
2. **product** - Product inventory
3. **customer** - Customer accounts
4. **order_product** - Order details
5. **location** - User locations (owner/customer)
6. **shop_location** - Shop location for delivery calculation
7. **order_items** - Individual items in orders

## Security Features

- **Prepared Statements**: All database queries use PDO prepared statements
- **Password Hashing**: Passwords are hashed using PHP's `password_hash()`
- **Session Management**: Secure session handling for authentication
- **Input Validation**: Form inputs are validated and sanitized
- **SQL Injection Prevention**: Parameterized queries prevent SQL injection

## Customization

### Changing Delivery Fee
Edit the delivery fee calculation in:
- `customer/orders/place_order.php` (line ~120)
- `customer/location.php` (line ~70)

### Adding More Categories
Update category dropdowns in:
- `owner/products/add_product.php`
- `owner/products/edit_product.php`

### Modifying Styling
Edit `assets/css/style.css` for custom styles

## Troubleshooting

### Database Connection Error
- Verify MySQL service is running in XAMPP
- Check database credentials in `includes/db.php`
- Ensure database `grocery_store` exists

### Image Upload Not Working
- Check write permissions on `assets/uploads/` folder
- Verify PHP upload limits in php.ini
- Ensure image file size is within limits

### Session Issues
- Clear browser cookies and cache
- Check PHP session configuration in php.ini
- Ensure session path is writable

## Support

For issues or questions:
- Check the error messages in browser console
- Verify XAMPP error logs
- Review PHP error logs in XAMPP

## License

This project is created for educational purposes.

## Credits

- Bootstrap 5 - UI Framework
- Bootstrap Icons - Icon set
- PHP - Backend language
- MySQL - Database management

---

**Note**: This is a demonstration project. For production use, additional security measures and optimizations should be implemented.

