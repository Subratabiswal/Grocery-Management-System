/**
 * Custom JavaScript for Grocery Store
 * Pure JavaScript - no Java classes
 */

// Document Ready Function
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Image Preview Function
    const imageInput = document.getElementById('productImage');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Quantity Selector
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const max = parseInt(this.getAttribute('max'));
            const value = parseInt(this.value);
            
            if (value > max) {
                this.value = max;
                alert('Maximum available quantity is ' + max);
            }
            if (value < 1) {
                this.value = 1;
            }
        });
    });

    // Calculate Delivery Fee
    const distanceInput = document.getElementById('distance');
    const deliveryFeeDisplay = document.getElementById('deliveryFee');
    const totalAmountDisplay = document.getElementById('totalAmount');
    
    if (distanceInput && deliveryFeeDisplay) {
        distanceInput.addEventListener('input', function() {
            calculateDeliveryFee();
        });
    }

    // Cart Functions
    updateCartTotal();

    // Form Validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Search Functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(function(card) {
                const productName = card.querySelector('.product-name').textContent.toLowerCase();
                const productCategory = card.querySelector('.product-category').textContent.toLowerCase();
                
                if (productName.includes(searchTerm) || productCategory.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Confirm Delete Actions
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    // Print Bill Function
    const printBillBtn = document.getElementById('printBill');
    if (printBillBtn) {
        printBillBtn.addEventListener('click', function() {
            window.print();
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.classList.add('fade');
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

/**
 * Calculate Delivery Fee based on distance
 * Free delivery within 5km, ₹10 per km beyond that
 */
function calculateDeliveryFee() {
    const distance = parseFloat(document.getElementById('distance').value) || 0;
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    
    let deliveryFee = 0;
    if (distance > 5) {
        deliveryFee = (distance - 5) * 10;
    }
    
    const total = subtotal + deliveryFee;
    
    document.getElementById('deliveryFee').textContent = '₹' + deliveryFee.toFixed(2);
    document.getElementById('totalAmount').textContent = '₹' + total.toFixed(2);
    document.getElementById('deliveryFeeInput').value = deliveryFee;
    document.getElementById('totalAmountInput').value = total;
    
    // Update delivery info message
    const deliveryInfo = document.getElementById('deliveryInfo');
    if (deliveryInfo) {
        if (distance <= 5) {
            deliveryInfo.innerHTML = '<span class="free-delivery"><i class="bi bi-check-circle"></i> Free Delivery (within 5km)</span>';
        } else {
            deliveryInfo.innerHTML = '<span class="paid-delivery"><i class="bi bi-info-circle"></i> Delivery Fee: ₹' + deliveryFee.toFixed(2) + ' (' + distance + 'km)</span>';
        }
    }
}

/**
 * Update Cart Total
 */
function updateCartTotal() {
    const cartItems = document.querySelectorAll('.cart-item');
    let subtotal = 0;
    
    cartItems.forEach(function(item) {
        const price = parseFloat(item.querySelector('.item-price').textContent.replace('₹', ''));
        const quantity = parseInt(item.querySelector('.item-quantity').value);
        const itemTotal = price * quantity;
        
        item.querySelector('.item-total').textContent = '₹' + itemTotal.toFixed(2);
        subtotal += itemTotal;
    });
    
    const cartSubtotal = document.getElementById('cartSubtotal');
    if (cartSubtotal) {
        cartSubtotal.textContent = '₹' + subtotal.toFixed(2);
    }
    
    // Recalculate delivery fee if distance is set
    const distance = parseFloat(document.getElementById('distance').value) || 0;
    if (distance > 0) {
        calculateDeliveryFee();
    }
}

/**
 * Add to Cart Function
 */
function addToCart(productId, productName, price) {
    // This would typically send an AJAX request to add item to cart
    // For now, we'll show a confirmation message
    alert('Added ' + productName + ' to cart!');
}

/**
 * Remove from Cart Function
 */
function removeFromCart(itemId) {
    if (confirm('Remove this item from cart?')) {
        // This would typically send an AJAX request to remove item from cart
        const cartItem = document.getElementById('cartItem-' + itemId);
        if (cartItem) {
            cartItem.remove();
            updateCartTotal();
        }
    }
}

/**
 * Get User Location
 */
function getUserLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                
                // Calculate distance from shop (shop coordinates would be fetched from database)
                calculateDistanceFromShop(lat, lng);
            },
            function(error) {
                alert('Unable to get your location. Please enter it manually.');
                console.error('Geolocation error:', error);
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

/**
 * Calculate Distance from Shop
 */
function calculateDistanceFromShop(lat, lng) {
    // Shop coordinates (would be fetched from database)
    const shopLat = 19.0760; // Mumbai coordinates
    const shopLng = 72.8777;
    
    const distance = calculateDistance(lat, lng, shopLat, shopLng);
    
    document.getElementById('distance').value = distance.toFixed(2);
    calculateDeliveryFee();
}

/**
 * Calculate Distance between two coordinates (Haversine formula)
 */
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const distance = R * c;
    
    return distance;
}

/**
 * Format Currency
 */
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2);
}

/**
 * Show Loading Spinner
 */
function showLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = 'flex';
    }
}

/**
 * Hide Loading Spinner
 */
function hideLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}
