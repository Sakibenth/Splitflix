<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    die("Error: Please login to make a payment.");
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    die("Error: Invalid Group ID.");
}

// Fetch group details and cost
$query = "SELECT group_name, cost_per_member FROM subscription_group WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($result);

if (!$group) {
    die("Error: Group not found.");
}

// Transaction details
$tran_id = "TXN_" . uniqid() . "_" . $user_id . "_" . $group_id;
$total_amount = $group['cost_per_member'];
$currency = "BDT";

// Prepare data for SSLCommerz
$post_data = array();
$post_data['store_id'] = SSLC_STORE_ID;
$post_data['store_passwd'] = SSLC_STORE_PASSWORD;
$post_data['total_amount'] = $total_amount;
$post_data['currency'] = $currency;
$post_data['tran_id'] = $tran_id;

// Return URLs
$post_data['success_url'] = SSLC_SUCCESS_URL;
$post_data['fail_url'] = SSLC_FAIL_URL;
$post_data['cancel_url'] = SSLC_CANCEL_URL;
$post_data['ipn_url'] = SSLC_IPN_URL;

// Customer Information (Fetching from session/db if available)
$user_query = "SELECT name, email, phone FROM users WHERE user_id = ?";
$u_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($u_stmt, "i", $user_id);
mysqli_stmt_execute($u_stmt);
$user_res = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));

$post_data['cus_name'] = $user_res['name'] ?? 'Customer';
$post_data['cus_email'] = $user_res['email'] ?? 'test@test.com';
$post_data['cus_phone'] = $user_res['phone'] ?? '01700000000';
$post_data['cus_add1'] = 'Dhaka';
$post_data['cus_city'] = 'Dhaka';
$post_data['cus_country'] = 'Bangladesh';

// Ship and Product info (required but can be generic for digital services)
$post_data['shipping_method'] = 'NO';
$post_data['num_of_item'] = '1';
$post_data['product_name'] = $group['group_name'] . " Subscription";
$post_data['product_category'] = "Subscription";
$post_data['product_profile'] = "general";

// Custom values to store IDs for the callback
$post_data['value_a'] = $group_id;
$post_data['value_b'] = $user_id;

// Call SSLCommerz API
$handle = curl_init();
curl_setopt($handle, CURLOPT_URL, SSLC_API_URL);
curl_setopt($handle, CURLOPT_POST, 1);
curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false); # Keep false for local testing

$content = curl_exec($handle);
$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

if ($code == 200 && !(curl_errno($handle))) {
    curl_close($handle);
    $response = json_decode($content, true);

    if (isset($response['GatewayPageURL']) && $response['GatewayPageURL'] != "") {
        // Redirect to SSLCommerz Gateway
        header("Location: " . $response['GatewayPageURL']);
        exit;
    } else {
        echo "JSON Data parsing error: " . $response['failedreason'];
    }
} else {
    curl_close($handle);
    echo "FAILED TO CONNECT WITH SSLCOMMERZ API";
}
?>
