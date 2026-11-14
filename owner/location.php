<?php
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/owner_header.php';

/**
 * Owner Location Settings Page - Swiggy-like Location Selection
 * Modern location picker with search, GPS, and interactive map
 */

$error = '';
$success = '';

function hasCoordinateValue($value) {
    return is_string($value) ? trim($value) !== '' : $value !== null;
}

function isValidCoordinate($latitude, $longitude) {
    return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
}

// Get current shop location
try {
    $stmt = $pdo->query("SELECT * FROM shop_location ORDER BY id DESC LIMIT 1");
    $shop_location = $stmt->fetch();
} catch(PDOException $e) {
    $shop_location = null;
}

$hasShopCoordinates = $shop_location
    && $shop_location['latitude'] !== null
    && $shop_location['longitude'] !== null;

ensureCsrfToken();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isValidCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    }

    $shop_name = trim($_POST['shop_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pin_code = trim($_POST['pin_code'] ?? '');
    $latitude = hasCoordinateValue($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
    $longitude = hasCoordinateValue($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
    
    // Validation
    if(!empty($error)) {
        // CSRF error already set above.
    } elseif(empty($shop_name) || empty($address) || empty($city) || empty($pin_code)) {
        $error = 'Please fill in all required fields';
    } elseif($latitude === null || $longitude === null) {
        $error = 'Please select a location on the map or use current location';
    } elseif(!isValidCoordinate($latitude, $longitude)) {
        $error = 'Please choose a valid location from the map or GPS.';
    } else {
        try {
            if($shop_location) {
                // Update existing shop location
                $stmt = $pdo->prepare("
                    UPDATE shop_location 
                    SET shop_name = ?, address = ?, city = ?, pin_code = ?, latitude = ?, longitude = ?
                    WHERE id = ?
                ");
                $stmt->execute([$shop_name, $address, $city, $pin_code, $latitude, $longitude, $shop_location['id']]);
            } else {
                // Insert new shop location
                $stmt = $pdo->prepare("
                    INSERT INTO shop_location (shop_name, address, city, pin_code, latitude, longitude)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$shop_name, $address, $city, $pin_code, $latitude, $longitude]);
            }
            
            $success = 'Shop location updated successfully!';
            
            // Refresh shop location data
            $stmt = $pdo->query("SELECT * FROM shop_location ORDER BY id DESC LIMIT 1");
            $shop_location = $stmt->fetch();
            $hasShopCoordinates = $shop_location
                && $shop_location['latitude'] !== null
                && $shop_location['longitude'] !== null;
            
        } catch(PDOException $e) {
            error_log('Owner shop location save failed: ' . $e->getMessage());
            $error = 'Unable to save the shop location right now. Please try again.';
        }
    }
}
?>

<style>
    .location-hero {
        background: linear-gradient(135deg, #28a745, #20c997);
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
        background: rgba(40, 167, 69, 0.12);
        color: #28a745;
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
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .gps-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
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
                Shop Location Setup
            </span>
            <h2 class="fw-bold mb-2">Set your shop location</h2>
            <p class="mb-0 opacity-75">Use the interactive map to pinpoint your exact shop location for accurate delivery distance calculations.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <?php if($shop_location): ?>
                <div class="hero-chip">
                    <i class="bi bi-check-circle-fill"></i>
                    Location set: <?php echo htmlspecialchars($shop_location['city']); ?>
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
                            <h5 class="mb-1">Search for your shop location</h5>
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
                    <span id="geoStatusText">Search for a location or use your current location to set shop coordinates.</span>
                </div>
            </div>
        </div>

        <!-- Map Card -->
        <div class="card location-map-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="mb-0">Interactive Map</h5>
                    <small class="text-muted">Drag the marker to adjust your exact location</small>
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
                <h5 class="mb-1">Shop Location Details</h5>
                <p class="text-muted mb-0">Review and confirm your shop location information</p>
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
                                <p class="text-muted mb-1">Shop Name</p>
                                <h6 id="displayShopName"><?php echo $shop_location ? htmlspecialchars($shop_location['shop_name']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Complete Address</p>
                                <h6 id="displayAddress"><?php echo $shop_location ? htmlspecialchars($shop_location['address']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1">Area / Locality</p>
                                <h6 id="displayArea">Not set</h6>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1">City</p>
                                <h6 id="displayCity"><?php echo $shop_location ? htmlspecialchars($shop_location['city']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1">PIN Code</p>
                                <h6 id="displayPinCode"><?php echo $shop_location ? htmlspecialchars($shop_location['pin_code']) : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Latitude</p>
                                <h6 id="displayLatitude"><?php echo $shop_location ? $shop_location['latitude'] : 'Not set'; ?></h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Longitude</p>
                                <h6 id="displayLongitude"><?php echo $shop_location ? $shop_location['longitude'] : 'Not set'; ?></h6>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields for automatic coordinate management -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo $shop_location ? $shop_location['latitude'] : ''; ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo $shop_location ? $shop_location['longitude'] : ''; ?>">
                    
                    <div class="mb-3">
                        <label for="shop_name" class="form-label">Shop Name *</label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name" value="<?php echo $shop_location ? htmlspecialchars($shop_location['shop_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Complete Address *</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $shop_location ? htmlspecialchars($shop_location['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City *</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo $shop_location ? htmlspecialchars($shop_location['city']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="pin_code" class="form-label">PIN Code *</label>
                            <input type="text" class="form-control" id="pin_code" name="pin_code" value="<?php echo $shop_location ? htmlspecialchars($shop_location['pin_code']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Note:</strong> Coordinates are automatically managed when you select a location on the map or use GPS. Manual entry is not required.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-success" id="saveButton">
                            <i class="bi bi-check-circle"></i> Save Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card location-info-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Delivery Information</h5>
                <div class="location-stat mb-3">
                    <p class="text-muted mb-1">Delivery Policy</p>
                    <ul class="mb-0 ps-3">
                        <li><strong>Delivery Radius:</strong> Orders can only be placed within 10 km of shop</li>
                        <li><strong>Free Delivery:</strong> Within 5 km radius</li>
                        <li><strong>Paid Delivery:</strong> ₹10 per km beyond 5 km (up to 10 km)</li>
                        <li><strong>Delivery Areas:</strong> Based on distance from shop</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card location-info-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Current Shop Location</h5>
                <?php if($shop_location): ?>
                    <div class="location-stat mb-3">
                        <p class="text-muted mb-1">Shop Name</p>
                        <h6><?php echo htmlspecialchars($shop_location['shop_name']); ?></h6>
                    </div>
                    <div class="location-stat mb-3">
                        <p class="text-muted mb-1">Address</p>
                        <p class="mb-0"><?php echo htmlspecialchars($shop_location['address']); ?></p>
                    </div>
                    <div class="location-stat mb-3">
                        <p class="text-muted mb-1">City & PIN Code</p>
                        <p class="mb-0"><?php echo htmlspecialchars($shop_location['city']); ?> - <?php echo htmlspecialchars($shop_location['pin_code']); ?></p>
                    </div>
                    <?php if($hasShopCoordinates): ?>
                        <div class="location-stat">
                            <p class="text-muted mb-1">Coordinates</p>
                            <p class="mb-0"><?php echo $shop_location['latitude']; ?>, <?php echo $shop_location['longitude']; ?></p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Shop location not set yet. Use the map or search to set your location.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card location-info-card">
            <div class="card-body p-4">
                <h5 class="mb-3">How to Use</h5>
                <ol class="mb-0 ps-3">
                    <li class="mb-2">Search for your location or use "Use Current Location"</li>
                    <li class="mb-2">Drag the map marker to your exact shop location</li>
                    <li class="mb-2">Fill in the shop details in the form</li>
                    <li>Click "Save Location" to confirm</li>
                </ol>
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
const MAX_DELIVERY_RADIUS = 10; // 10 km

// Initialize map
function initMap() {
    const defaultLat = <?php echo $hasShopCoordinates ? $shop_location['latitude'] : '19.0760'; ?>;
    const defaultLng = <?php echo $hasShopCoordinates ? $shop_location['longitude'] : '72.8777'; ?>;
    
    map = L.map('map').setView([defaultLat, defaultLng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add marker if coordinates exist
    if (<?php echo $hasShopCoordinates ? 'true' : 'false'; ?>) {
        updateCoordinates(defaultLat, defaultLng);
        addMarker(defaultLat, defaultLng);
        
        // Add 10 km delivery radius circle
        deliveryCircle = L.circle([defaultLat, defaultLng], {
            color: '#28a745',
            fillColor: '#28a745',
            fillOpacity: 0.1,
            radius: MAX_DELIVERY_RADIUS * 1000, // Convert km to meters
            weight: 2
        }).addTo(map).bindPopup('10 km Delivery Radius');
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
        
        // Update delivery circle to center on new shop location
        if (deliveryCircle) {
            deliveryCircle.setLatLng([position.lat, position.lng]);
        }
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
        document.getElementById('searchSuggestions').classList.remove('show');
        return;
    }
    
    const statusText = document.getElementById('geoStatusText');
    statusText.textContent = 'Searching for location...';
    
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            displaySuggestions(data);
            statusText.textContent = 'Search results found. Select a location from the suggestions.';
        })
        .catch(error => {
            console.error('Search error:', error);
            statusText.textContent = 'Error searching for location. Please try again.';
        });
}

// Display search suggestions
function displaySuggestions(results) {
    const suggestionsDiv = document.getElementById('searchSuggestions');
    suggestionsDiv.innerHTML = '';
    
    if (results.length === 0) {
        suggestionsDiv.classList.remove('show');
        return;
    }
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'suggestion-item';
        
        const mainText = result.display_name.split(',')[0];
        const subText = result.display_name;
        
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
    
    document.getElementById('searchSuggestions').classList.remove('show');
    document.getElementById('locationSearch').value = result.display_name;
    document.getElementById('geoStatusText').textContent = 'Location selected successfully! Review the details below.';
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
    document.getElementById('address').value = address;
    document.getElementById('city').value = city;
    document.getElementById('pin_code').value = pinCode;
    
    // Update display
    document.getElementById('displayAddress').textContent = address || 'Not set';
    document.getElementById('displayCity').textContent = city || 'Not set';
    document.getElementById('displayPinCode').textContent = pinCode || 'Not set';
    document.getElementById('displayArea').textContent = area || 'Not set';
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
    
    // Update display when form fields change
    document.getElementById('shop_name').addEventListener('input', function() {
        document.getElementById('displayShopName').textContent = this.value || 'Not set';
    });
    
    document.getElementById('address').addEventListener('input', function() {
        document.getElementById('displayAddress').textContent = this.value || 'Not set';
    });
    
    document.getElementById('city').addEventListener('input', function() {
        document.getElementById('displayCity').textContent = this.value || 'Not set';
    });
    
    document.getElementById('pin_code').addEventListener('input', function() {
        document.getElementById('displayPinCode').textContent = this.value || 'Not set';
    });
    
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

<?php require_once '../includes/owner_footer.php'; ?>
