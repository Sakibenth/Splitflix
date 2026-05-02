<?php
// SSLCommerz Configuration for Splitflix
// This file is separate to keep integration clean and easy to remove

// Sandbox Credentials - Replace these with your own from developer.sslcommerz.com
define('SSLC_STORE_ID', 'sakib69f2de5d7dd2d'); // Your Store ID
define('SSLC_STORE_PASSWORD', 'sakib69f2de5d7dd2d@ssl'); // Your Store Password
define('SSLC_IS_SANDBOX', true);

// Site URLs
// IMPORTANT: Update this to match your actual local URL (e.g., http://localhost/splitflix)
define('SITE_URL', 'http://localhost/splitflix');

define('SSLC_SUCCESS_URL', SITE_URL . '/payments/success.php');
define('SSLC_FAIL_URL', SITE_URL . '/payments/fail.php');
define('SSLC_CANCEL_URL', SITE_URL . '/payments/cancel.php');
define('SSLC_IPN_URL', SITE_URL . '/payments/ipn.php');

// API Endpoints
if (SSLC_IS_SANDBOX) {
    define('SSLC_API_URL', 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php');
    define('SSLC_VALIDATION_URL', 'https://sandbox.sslcommerz.com/validator/api/validationserverphp.php');
} else {
    define('SSLC_API_URL', 'https://securepay.sslcommerz.com/gwprocess/v4/api.php');
    define('SSLC_VALIDATION_URL', 'https://securepay.sslcommerz.com/validator/api/validationserverphp.php');
}
?>
