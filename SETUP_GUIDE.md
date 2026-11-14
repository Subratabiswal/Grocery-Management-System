# Quick Setup Guide for Grocery Store System

## Quick Start (5 Minutes)

### 1. Install XAMPP
- Download XAMPP from https://www.apachefriends.org/
- Install with default settings
- Start Apache and MySQL from XAMPP Control Panel

### 2. Copy Files
- Copy the `store` folder to `C:\xampp\htdocs\`

### 3. Setup Database
- Open http://localhost/phpmyadmin
- Create database named `grocery_store`
- Import `database/grocery_store.sql`

### 4. Access the Site
- Open http://localhost/store/
- Owner login: owner@grocery.com / admin123
- Customer registration available on site

## Detailed Configuration

### Database Configuration
File: `includes/db.php`
```php
$host = 'localhost';
$dbname = 'grocery_store';
$username = 'root';
$password = ''; // Default XAMPP password is empty
```

### Folder Permissions
Ensure these folders are writable:
- `assets/uploads/` (for product images)
- Temporary PHP upload directory

### PHP Configuration (php.ini)
Check these settings in `C:\xampp\php\php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

## Testing the System

### Test Owner Functions
1. Login as owner
2. Set shop location
3. Add a product
4. View dashboard statistics

### Test Customer Functions
1. Register as customer
2. Set delivery location
3. Browse products
4. Place an order
5. View order history
6. Download bill

### Test Delivery Calculation
1. Set shop location in owner panel
2. Set customer location within 5km → Free delivery
3. Set customer location beyond 5km → ₹10/km fee

## Common Issues & Solutions

### Issue: "Connection failed"
**Solution**: 
- Verify MySQL is running in XAMPP
- Check database name in `includes/db.php`
- Ensure database was created successfully

### Issue: "Image upload not working"
**Solution**:
- Check folder permissions on `assets/uploads/`
- Verify PHP upload limits in php.ini
- Ensure image file size is under limits

### Issue: "Session not working"
**Solution**:
- Clear browser cookies
- Check session.save_path in php.ini
- Restart Apache server

### Issue: "White screen / blank page"
**Solution**:
- Enable error display in php.ini: `display_errors = On`
- Check PHP error logs in XAMPP
- Verify file permissions

## Security Checklist

Before deploying to production:
- [ ] Change default owner password
- [ ] Set strong database password
- [ ] Enable HTTPS/SSL
- [ ] Restrict directory access
- [ ] Implement rate limiting
- [ ] Add CSRF protection
- [ ] Enable input validation
- [ ] Regular security updates

## Performance Optimization

- Enable PHP OPcache
- Use database indexing
- Optimize images before upload
- Enable gzip compression
- Use CDN for static assets

## Backup Strategy

### Database Backup
```bash
mysqldump -u root -p grocery_store > backup.sql
```

### File Backup
- Regularly backup `assets/uploads/`
- Backup configuration files
- Keep version control of code

## Support Resources

- XAMPP Documentation: https://www.apachefriends.org/docs/
- PHP Documentation: https://www.php.net/docs.php
- MySQL Documentation: https://dev.mysql.com/doc/
- Bootstrap Documentation: https://getbootstrap.com/docs/

## Next Steps

1. Customize the UI with your branding
2. Add your own products
3. Set up payment gateway integration
4. Configure email notifications
5. Add SMS notifications for orders
6. Implement inventory management alerts
7. Add customer reviews and ratings
8. Create promotional campaigns

---

For detailed documentation, see README.md
