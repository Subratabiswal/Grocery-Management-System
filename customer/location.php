<?php
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/customer_header.php';

/**
 * Customer Location Page - Swiggy-like Location Selection
 * Modern location picker with search, GPS, and interactive map
 */

$customer_id = $_SESSION['customer_id'];
$error = '';
$success = '';

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth's radius in km
    $dLat = ($lat2 - $lat1) * M_PI / 180;
    $dLon = ($lon2 - $lon1) * M_PI / 180;

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos($lat1 * M_PI / 180) * cos($lat2 * M_PI / 180) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

function calculateDeliveryFee($distance) {
    $distance = (float) $distance;
    return $distance > 5 ? ($distance - 5) * 10 : 0;
}

function hasCoordinateValue($value) {
    return is_string($value) ? trim($value) !== '' : $value !== null;
}

function isValidCoordinate($latitude, $longitude) {
    return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
}

function buildLocationSummary($location) {
    if(!$location) {
        return '';
    }

    $parts = [];
    if(!empty($location['area'])) {
        $parts[] = trim($location['area']);
    }
    if(!empty($location['landmark'])) {
        $parts[] = trim($location['landmark']);
    }
    if(!empty($location['city'])) {
        $parts[] = trim($location['city']);
    }
    if(!empty($location['pin_code'])) {
        $parts[] = trim($location['pin_code']);
    }

    return implode(', ', $parts);
}

// Get customer's existing location
try {
    $stmt = $pdo->prepare("SELECT * FROM location WHERE user_id = ? AND user_type = 'customer' ORDER BY is_default DESC LIMIT 1");
    $stmt->execute([$customer_id]);
    $customer_location = $stmt->fetch();
} catch(PDOException $e) {
    $customer_location = null;
}

// Get shop location for distance calculation
try {
    $stmt = $pdo->query("SELECT * FROM shop_location ORDER BY id DESC LIMIT 1");
    $shop_location = $stmt->fetch();
} catch(PDOException $e) {
    $shop_location = null;
}

$formData = [
    'name' => $customer_location['name'] ?? $_SESSION['customer_name'],
    'mobile_no' => $customer_location['mobile_no'] ?? $_SESSION['customer_phone'],
    'city' => $customer_location['city'] ?? '',
    'pin_code' => $customer_location['pin_code'] ?? '',
    'landmark' => $customer_location['landmark'] ?? '',
    'area' => $customer_location['area'] ?? '',
    'latitude' => $customer_location['latitude'] ?? '',
    'longitude' => $customer_location['longitude'] ?? ''
];

$hasShopCoordinates = $shop_location
    && $shop_location['latitude'] !== null
    && $shop_location['longitude'] !== null;

$hasCustomerCoordinates = $customer_location
    && $customer_location['latitude'] !== null
    && $customer_location['longitude'] !== null;

ensureCsrfToken();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isValidCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    }

    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'mobile_no' => trim($_POST['mobile_no'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'pin_code' => trim($_POST['pin_code'] ?? ''),
        'landmark' => trim($_POST['landmark'] ?? ''),
        'area' => trim($_POST['area'] ?? ''),
        'latitude' => trim($_POST['latitude'] ?? ''),
        'longitude' => trim($_POST['longitude'] ?? '')
    ];

    $name = $formData['name'];
    $mobile_no = $formData['mobile_no'];
    $city = $formData['city'];
    $pin_code = $formData['pin_code'];
    $landmark = $formData['landmark'];
    $area = $formData['area'];
    $latitude = hasCoordinateValue($formData['latitude']) ? (float) $formData['latitude'] : null;
    $longitude = hasCoordinateValue($formData['longitude']) ? (float) $formData['longitude'] : null;

    // Validation
    if(!empty($error)) {
        // CSRF error already set above.
    } elseif(empty($name) || empty($mobile_no) || empty($city) || empty($pin_code) || empty($area)) {
        $error = 'Please fill in all required fields';
    } elseif(!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $error = 'Please enter a valid 10 digit mobile number';
    } elseif($latitude === null || $longitude === null) {
        $error = 'Please select a location on the map or use current location';
    } elseif(!isValidCoordinate($latitude, $longitude)) {
        $error = 'Please choose a valid location from the map or GPS.';
    } elseif($hasShopCoordinates) {
        // Check if location is within 10 km radius
        $distance = calculateDistance($latitude, $longitude, $shop_location['latitude'], $shop_location['longitude']);
        if($distance > 10) {
            $error = 'Your location is beyond our 10 km delivery radius. Please select a location within 10 km of the shop.';
        }
    }
    
    // Process form if no errors
    if(empty($error)) {
        try {
            // Calculate distance from shop
            $distance = 0;
            if($hasShopCoordinates && $latitude !== null && $longitude !== null) {
                $distance = calculateDistance($latitude, $longitude, $shop_location['latitude'], $shop_location['longitude']);
            }
            
            if($customer_location) {
                // Update existing location
                $stmt = $pdo->prepare("
                    UPDATE location 
                    SET name = ?, mobile_no = ?, city = ?, pin_code = ?, landmark = ?, area = ?, latitude = ?, longitude = ?, distance_from_shop = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $mobile_no, $city, $pin_code, $landmark, $area, $latitude, $longitude, $distance, $customer_location['id']]);
            } else {
                // Insert new location
                $stmt = $pdo->prepare("
                    INSERT INTO location (user_type, user_id, name, mobile_no, city, pin_code, landmark, area, latitude, longitude, distance_from_shop, is_default)
                    VALUES ('customer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$customer_id, $name, $mobile_no, $city, $pin_code, $landmark, $area, $latitude, $longitude, $distance]);
            }
            
            $success = 'Location saved successfully! Delivery distance: ' . number_format($distance, 2) . ' km';
            
            // Refresh location data
            $stmt = $pdo->prepare("SELECT * FROM location WHERE user_id = ? AND user_type = 'customer' ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$customer_id]);
            $customer_location = $stmt->fetch();

            $formData = [
                'name' => $customer_location['name'] ?? $_SESSION['customer_name'],
                'mobile_no' => $customer_location['mobile_no'] ?? $_SESSION['customer_phone'],
                'city' => $customer_location['city'] ?? '',
                'pin_code' => $customer_location['pin_code'] ?? '',
                'landmark' => $customer_location['landmark'] ?? '',
                'area' => $customer_location['area'] ?? '',
                'latitude' => $customer_location['latitude'] ?? '',
                'longitude' => $customer_location['longitude'] ?? ''
            ];

        } catch(PDOException $e) {
            error_log('Customer location save failed: ' . $e->getMessage());
            $error = 'Unable to save the location right now. Please try again.';
        }
    }
}

$locationSummary = buildLocationSummary($customer_location);
$deliveryFee = $customer_location ? calculateDeliveryFee($customer_location['distance_from_shop']) : 0;
?>

<style>
    .location-hero {
        background: linear-gradient(135deg, #198754, #20c997);
        border-radius: 24px;
        color: #fff;
        overflow: hidden;
    }
    .location-hero .hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        font-size: 0.95rem;
    }
    .location-search-card,
    .location-map-card,
    .location-form-card,
    .location-info-card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
    }
    .location-map-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fff9 100%);
    }
    .location-highlight {
        border: 1px solid #d1e7dd;
        background: #f8fff9;
        border-radius: 16px;
        padding: 16px;
    }
    .location-stat {
        border-radius: 16px;
        background: #f8f9fa;
        padding: 16px;
        height: 100%;
    }
    .location-pin {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(25, 135, 84, 0.12);
        color: #198754;
        font-size: 1.5rem;
    }
    .search-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }
    .search-suggestions.show {
        display: block;
    }
    .suggestion-item {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }
    .suggestion-item:hover {
        background: #f8fff9;
    }
    .suggestion-item:last-child {
        border-bottom: none;
    }
    .suggestion-item .suggestion-main {
        font-weight: 500;
        color: #333;
    }
    .suggestion-item .suggestion-sub {
        font-size: 0.85rem;
        color: #666;
        margin-top: 4px;
    }
    #map {
        height: 400px;
        border-radius: 16px;
        border: 2px solid #e9ecef;
    }
    .location-loading {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        z-index: 1001;
    }
    .location-loading.show {
        display: block;
    }
    .gps-button {
        background: linear-gradient(135deg, #198754, #20c997);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .gps-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
    }
    .gps-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
</style>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="location-hero p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="hero-chip mb-3">
                <i class="bi bi-geo-alt-fill"></i>
                Delivery Location Setup
            </span>
            <h2 class="fw-bold mb-2">Set your delivery location</h2>
            <p class="mb-0 opacity-75">Use the interactive map to pinpoint your exact delivery address for accurate distance calculation and delivery fees.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <?php if($customer_location): ?>
                <div class="hero-chip">
                    <i class="bi bi-check-circle-fill"></i>
                    Delivering to <?php echo htmlspecialchars($customer_location['city']); ?>
                </div>
            <?php else: ?>
                <div class="hero-chip">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    Location not set
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Location Search Card -->
        <div class="card location-search-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-3">
                        <div class="location-pin">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Search for your delivery address</h5>
                            <p class="text-muted mb-0">Search by area, street, landmark, or PIN code</p>
                        </div>
                    </div>
                    <button type="button" class="gps-button" onclick="getCurrentLocation()" id="gpsButton">
                        <i class="bi bi-crosshair"></i> Use Current Location
                    </button>
                </div>

                <div class="mt-4 position-relative">
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="locationSearch" placeholder="Search for your area, street, landmark..." autocomplete="off">
                    </div>
                    <div class="search-suggestions" id="searchSuggestions"></div>
                </div>

                <div id="geoStatus" class="mt-3 text-muted">
                    <i class="bi bi-info-circle"></i> 
                    <span id="geoStatusText">Search for a location or use your current location to set delivery coordinates.</span>
                </div>
            </div>
        </div>

        <!-- Map Card -->
        <div class="card location-map-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="mb-0">Interactive Map</h5>
                    <small class="text-muted">Drag the marker to adjust your exact delivery location</small>
                </div>
                <div class="position-relative">
                    <div id="map"></div>
                    <div class="location-loading" id="mapLoading">
                        <div class="text-center">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading map...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Form Card -->
        <div class="card location-form-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-1">Delivery Address Details</h5>
                <p class="text-muted mb-0">Review and confirm your delivery address information</p>
            </div>
            <div class="card-body p-4">
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
                
                <form method="POST" action="" class="needs-validation" novalidate id="locationForm">
                    <?php echo csrfInput(); ?>
                    <div class="location-highlight mb-4">
                        <h6 class="mb-3"><i class="bi bi-info-circle"></i> Selected Location Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Recipient Name</p>
                                <h6 id="displayName"><?php echo $customer_location ? htmlspecialchars($customer_location['name']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Mobile Number</p>
                                <h6 id="displayMobile"><?php echo $customer_location ? htmlspecialchars($customer_location['mobile_no']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">House No / Area / Street</p>
                                <h6 id="displayArea"><?php echo $customer_location ? htmlspecialchars($customer_location['area']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Landmark</p>
                                <h6 id="displayLandmark"><?php echo $customer_location ? htmlspecialchars($customer_location['landmark']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1">City</p>
                                <h6 id="displayCity"><?php echo $customer_location ? htmlspecialchars($customer_location['city']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1">PIN Code</p>
                                <h6 id="displayPinCode"><?php echo $customer_location ? htmlspecialchars($customer_location['pin_code']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1">Distance from Shop</p>
                                <h6 id="displayDistance"><?php echo $customer_location ? number_format($customer_location['distance_from_shop'], 2) . ' km' : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Latitude</p>
                                <h6 id="displayLatitude"><?php echo $hasCustomerCoordinates ? $customer_location['latitude'] : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Longitude</p>
                                <h6 id="displayLongitude"><?php echo $hasCustomerCoordinates ? $customer_location['longitude'] : 'Not set'; ?></h6>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields for automatic coordinate management -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo $hasCustomerCoordinates ? $customer_location['latitude'] : ''; ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo $hasCustomerCoordinates ? $customer_location['longitude'] : ''; ?>">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="area" class="form-label">House No / Area / Street *</label>
                            <input type="text" class="form-control" id="area" name="area" value="<?php echo htmlspecialchars($formData['area']); ?>" placeholder="Flat 12, MG Road, Near City Mall" required>
                            <small class="text-muted">This is the main delivery address shown during checkout.</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="landmark" class="form-label">Landmark</label>
                            <input type="text" class="form-control" id="landmark" name="landmark" value="<?php echo htmlspecialchars($formData['landmark']); ?>" placeholder="Near bus stand">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Recipient Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="mobile_no" class="form-label">Mobile Number *</label>
                            <input type="tel" class="form-control" id="mobile_no" name="mobile_no" value="<?php echo htmlspecialchars($formData['mobile_no']); ?>" required pattern="[0-9]{10}" title="10 digit mobile number">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City *</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($formData['city']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="pin_code" class="form-label">PIN Code *</label>
                            <input type="text" class="form-control" id="pin_code" name="pin_code" value="<?php echo htmlspecialchars($formData['pin_code']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Note:</strong> Coordinates are automatically managed when you select a location on the map or use GPS. Manual entry is not required.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo $baseUrl; ?>/customer/dashboard/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success" id="saveButton">
                            <i class="bi bi-check-circle"></i> Save & Use This Address
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card location-info-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Delivery Summary</h5>
                <div class="location-stat mb-3">
                    <p class="text-muted mb-1">Current status</p>
                    <?php if($customer_location): ?>
                        <h6 class="mb-1">Ready for checkout</h6>
                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($locationSummary); ?></p>
                    <?php else: ?>
                        <h6 class="mb-1">Address needed</h6>
                        <p class="mb-0 text-muted">Save your delivery address before adding items to cart.</p>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="location-stat text-center">
                            <div class="fw-bold text-success"><?php echo $customer_location ? number_format($customer_location['distance_from_shop'], 2) : '0.00'; ?> km</div>
                            <small class="text-muted">From shop</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="location-stat text-center">
                            <div class="fw-bold text-success">₹<?php echo number_format($deliveryFee, 2); ?></div>
                            <small class="text-muted">Delivery fee</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card location-info-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Delivery Information</h5>
                <ul class="mb-0 ps-3">
                    <li><strong>Delivery Radius:</strong> Orders can only be placed within 10 km of shop</li>
                    <li><strong>Free Delivery:</strong> Within 5 km radius</li>
                    <li><strong>Paid Delivery:</strong> ₹10 per km beyond 5 km (up to 10 km)</li>
                    <li><strong>Faster checkout:</strong> Saved address appears on the cart page</li>
                </ul>
            </div>
        </div>

        <div class="card location-info-card">
            <div class="card-body p-4">
                <h5 class="mb-3">Store Address</h5>
                <?php if($shop_location): ?>
                    <p><strong><?php echo htmlspecialchars($shop_location['shop_name']); ?></strong></p>
                    <p><?php echo htmlspecialchars($shop_location['address']); ?></p>
                    <p><?php echo htmlspecialchars($shop_location['city']); ?> - <?php echo htmlspecialchars($shop_location['pin_code']); ?></p>
                <?php else: ?>
                    <p class="text-muted">Shop location not set by owner.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let marker;
let deliveryCircle;
let selectedCoordinates = { lat: null, lng: null };
const hasShopCoordinates = <?php echo $hasShopCoordinates ? 'true' : 'false'; ?>;
const shopLat = hasShopCoordinates ? <?php echo json_encode($shop_location['latitude']); ?> : null;
const shopLng = hasShopCoordinates ? <?php echo json_encode($shop_location['longitude']); ?> : null;
const MAX_DELIVERY_RADIUS = 10; // 10 km

// Initialize map
function initMap() {
    const defaultLat = <?php echo $hasCustomerCoordinates ? $customer_location['latitude'] : '19.0760'; ?>;
    const defaultLng = <?php echo $hasCustomerCoordinates ? $customer_location['longitude'] : '72.8777'; ?>;
    
    map = L.map('map').setView([defaultLat, defaultLng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add shop location marker if available
    if (hasShopCoordinates) {
        L.marker([shopLat, shopLng], {
            icon: L.divIcon({
                className: 'shop-marker',
                html: '<div style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">Shop</div>',
                iconSize: [40, 20],
                iconAnchor: [20, 10]
            })
        }).addTo(map).bindPopup('Shop Location');
        
        // Add 10 km delivery radius circle
        deliveryCircle = L.circle([shopLat, shopLng], {
            color: '#28a745',
            fillColor: '#28a745',
            fillOpacity: 0.1,
            radius: MAX_DELIVERY_RADIUS * 1000, // Convert km to meters
            weight: 2
        }).addTo(map).bindPopup('10 km Delivery Radius');
    }
    
    // Add customer location marker if coordinates exist
    if (<?php echo $hasCustomerCoordinates ? 'true' : 'false'; ?>) {
        updateCoordinates(defaultLat, defaultLng);
        addMarker(defaultLat, defaultLng);
        calculateAndDisplayDistance(defaultLat, defaultLng);
        validateDeliveryRadius(defaultLat, defaultLng);
    }
    
    // Hide loading indicator
    document.getElementById('mapLoading').classList.remove('show');
}

// Add marker to map
function addMarker(lat, lng) {
    if (marker) {
        map.removeLayer(marker);
    }
    
    marker = L.marker([lat, lng], {
        draggable: true
    }).addTo(map);
    
    // Update coordinates when marker is dragged
    marker.on('dragend', function(event) {
        const position = marker.getLatLng();
        updateCoordinates(position.lat, position.lng);
        reverseGeocode(position.lat, position.lng);
        calculateAndDisplayDistance(position.lat, position.lng);
        validateDeliveryRadius(position.lat, position.lng);
    });
    
    // Center map on marker
    map.setView([lat, lng], 16);
}

// Update coordinates in form and display
function updateCoordinates(lat, lng) {
    selectedCoordinates.lat = lat;
    selectedCoordinates.lng = lng;
    
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('displayLatitude').textContent = lat.toFixed(6);
    document.getElementById('displayLongitude').textContent = lng.toFixed(6);
}

// Calculate and display distance from shop
function calculateAndDisplayDistance(lat, lng) {
    if (!hasShopCoordinates) {
        document.getElementById('displayDistance').textContent = 'Shop location not set';
        return;
    }
    
    const distance = calculateDistance(lat, lng, shopLat, shopLng);
    document.getElementById('displayDistance').textContent = distance.toFixed(2) + ' km';
}

// Calculate distance between two points
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;

    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
         Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
         Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

// Validate if location is within 10 km delivery radius
function validateDeliveryRadius(lat, lng) {
    if (!hasShopCoordinates) {
        return true; // No shop location set, allow any location
    }
    
    const distance = calculateDistance(lat, lng, shopLat, shopLng);
    const statusText = document.getElementById('geoStatusText');
    
    if (distance > MAX_DELIVERY_RADIUS) {
        statusText.innerHTML = `<i class="bi bi-exclamation-triangle text-danger"></i> Your location is ${distance.toFixed(2)} km from shop. Orders can only be placed within ${MAX_DELIVERY_RADIUS} km radius.`;
        return false;
    } else {
        statusText.innerHTML = `<i class="bi bi-check-circle text-success"></i> Your location is ${distance.toFixed(2)} km from shop. Within delivery radius.`;
        return true;
    }
}

// Get current location using GPS
function getCurrentLocation() {
    const gpsButton = document.getElementById('gpsButton');
    const statusText = document.getElementById('geoStatusText');
    
    if (!navigator.geolocation) {
        statusText.textContent = 'Geolocation is not supported by your browser.';
        return;
    }
    
    gpsButton.disabled = true;
    gpsButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Getting Location...';
    statusText.textContent = 'Requesting location permission...';
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            updateCoordinates(lat, lng);
            addMarker(lat, lng);
            reverseGeocode(lat, lng);
            calculateAndDisplayDistance(lat, lng);
            validateDeliveryRadius(lat, lng);
            
            gpsButton.disabled = false;
            gpsButton.innerHTML = '<i class="bi bi-crosshair"></i> Use Current Location';
            statusText.textContent = 'Location captured successfully! Address details filled automatically.';
        },
        function(error) {
            gpsButton.disabled = false;
            gpsButton.innerHTML = '<i class="bi bi-crosshair"></i> Use Current Location';
            
            let errorMessage = 'Unable to get location: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += 'Permission denied. Please allow location access.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMessage += 'Location request timed out.';
                    break;
                default:
                    errorMessage += 'Unknown error occurred.';
            }
            statusText.textContent = errorMessage;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Search location using Nominatim
function searchLocation(query) {
    if (query.length < 3) {
        const suggestionsDiv = document.getElementById('searchSuggestions');
        if(suggestionsDiv) suggestionsDiv.classList.remove('show');
        return;
    }
    
    const statusText = document.getElementById('geoStatusText');
    if(statusText) statusText.textContent = 'Searching for location...';
    
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            displaySuggestions(data);
            if(statusText) statusText.textContent = 'Search results found. Select a location from the suggestions.';
        })
        .catch(error => {
            console.error('Search error:', error);
            if(statusText) statusText.textContent = 'Error searching for location. Please try again.';
        });
}

// Display search suggestions
function displaySuggestions(results) {
    const suggestionsDiv = document.getElementById('searchSuggestions');
    if(!suggestionsDiv) return;
    
    suggestionsDiv.innerHTML = '';
    
    if (!results || results.length === 0) {
        suggestionsDiv.classList.remove('show');
        return;
    }
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'suggestion-item';
        
        const mainText = result.display_name ? result.display_name.split(',')[0] : 'Unknown location';
        const subText = result.display_name || 'No address details';
        
        item.innerHTML = `
            <div class="suggestion-main">${mainText}</div>
            <div class="suggestion-sub">${subText}</div>
        `;
        
        item.addEventListener('click', () => {
            selectLocation(result);
        });
        
        suggestionsDiv.appendChild(item);
    });
    
    suggestionsDiv.classList.add('show');
}

// Select location from search results
function selectLocation(result) {
    if(!result || result.lat === undefined || result.lon === undefined) {
        console.error('Invalid location data received');
        return;
    }
    
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);
    
    if(isNaN(lat) || isNaN(lng)) {
        console.error('Invalid coordinates received');
        return;
    }
    
    updateCoordinates(lat, lng);
    addMarker(lat, lng);
    fillAddressDetails(result);
    calculateAndDisplayDistance(lat, lng);
    validateDeliveryRadius(lat, lng);
    
    const suggestionsDiv = document.getElementById('searchSuggestions');
    if(suggestionsDiv) suggestionsDiv.classList.remove('show');
    
    const searchInput = document.getElementById('locationSearch');
    if(searchInput) searchInput.value = result.display_name || '';
    
    const statusText = document.getElementById('geoStatusText');
    if(statusText) statusText.textContent = 'Location selected successfully! Review the details below.';
}

// Fill address details from geocoding result
function fillAddressDetails(data) {
    const address = data.display_name || '';
    const addressParts = address.split(',');
    
    // Try to extract city, pin code, etc. from the address
    let city = '';
    let pinCode = '';
    let area = '';
    
    if (data.address) {
        city = data.address.city || data.address.town || data.address.village || data.address.county || '';
        pinCode = data.address.postcode || '';
        area = data.address.suburb || data.address.district || data.address.road || '';
    }
    
    // Update form fields
    document.getElementById('area').value = area || addressParts[0] || '';
    document.getElementById('city').value = city;
    document.getElementById('pin_code').value = pinCode;
    
    // Update display
    document.getElementById('displayArea').textContent = area || addressParts[0] || 'Not set';
    document.getElementById('displayCity').textContent = city || 'Not set';
    document.getElementById('displayPinCode').textContent = pinCode || 'Not set';
}

// Reverse geocoding to get address from coordinates
function reverseGeocode(lat, lng) {
    const statusText = document.getElementById('geoStatusText');
    statusText.textContent = 'Fetching address details...';
    
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            fillAddressDetails(data);
            statusText.textContent = 'Address details updated automatically.';
        })
        .catch(error => {
            console.error('Reverse geocoding error:', error);
            statusText.textContent = 'Could not fetch address details. Please fill them manually.';
        });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    initMap();
    
    // Search input listener
    const searchInput = document.getElementById('locationSearch');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchLocation(this.value);
        }, 500);
    });
    
    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.position-relative')) {
            document.getElementById('searchSuggestions').classList.remove('show');
        }
    });
    
    // Update display when form fields change (with null checks)
    const nameField = document.getElementById('name');
    if(nameField) {
        nameField.addEventListener('input', function() {
            const displayElement = document.getElementById('displayName');
            if(displayElement) displayElement.textContent = this.value || 'Not set';
        });
    }
    
    const mobileField = document.getElementById('mobile_no');
    if(mobileField) {
        mobileField.addEventListener('input', function() {
            const displayElement = document.getElementById('displayMobile');
            if(displayElement) displayElement.textContent = this.value || 'Not set';
        });
    }
    
    const areaField = document.getElementById('area');
    if(areaField) {
        areaField.addEventListener('input', function() {
            const displayElement = document.getElementById('displayArea');
            if(displayElement) displayElement.textContent = this.value || 'Not set';
        });
    }
    
    const landmarkField = document.getElementById('landmark');
    if(landmarkField) {
        landmarkField.addEventListener('input', function() {
            const displayElement = document.getElementById('displayLandmark');
            if(displayElement) displayElement.textContent = this.value || 'Not set';
        });
    }
    
    const cityField = document.getElementById('city');
    if(cityField) {
        cityField.addEventListener('input', function() {
            const displayElement = document.getElementById('displayCity');
            if(displayElement) displayElement.textContent = this.value || 'Not set';
        });
    }
    
    const pinCodeField = document.getElementById('pin_code');
    if(pinCodeField) {
        pinCodeField.addEventListener('input', function() {
            const displayElement = document.getElementById('displayPinCode');
            if(displayElement) displayElement.textContent = this.value || 'Not set';
        });
    }
    
    // Form validation
    const form = document.getElementById('locationForm');
    form.addEventListener('submit', function(e) {
        if (selectedCoordinates.lat === null || selectedCoordinates.lng === null) {
            e.preventDefault();
            alert('Please select a location on the map or use current location before saving.');
            return;
        }
    });
});
</script>

<?php require_once '../includes/customer_footer.php'; ?>
