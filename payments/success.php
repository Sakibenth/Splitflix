<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

// Check if it's a POST request from SSLCommerz
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['status']) && $_POST['status'] == 'VALID') {
    
    $tran_id = $_POST['tran_id'];
    $amount = $_POST['amount'];
    $val_id = $_POST['val_id'];
    
    // The group_id and user_id were passed as value_a and value_b in checkout.php
    $group_id = (int)$_POST['value_a'];
    $user_id = (int)$_POST['value_b'];

    // VALIDATION: Call SSLCommerz to verify this transaction is genuine
    $validation_url = SSLC_VALIDATION_URL . "?val_id=" . $val_id . "&store_id=" . SSLC_STORE_ID . "&store_passwd=" . SSLC_STORE_PASSWORD . "&format=json";
    
    $handle = curl_init();
    curl_setopt($handle, CURLOPT_URL, $validation_url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($handle);
    $result = json_decode($result, true);

    if ($result['status'] == 'VALID' || $result['status'] == 'AUTHENTICATED') {
        
        // PAYMENT SUCCESSFUL!
        // Update the database: Set payment_status to 'cleared' for this member in this group
        $update_query = "UPDATE group_members SET payment_status = 'cleared' WHERE group_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
        } else {
            $error = "Payment verified but database update failed: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Transaction Validation Failed!";
    }
} else {
    $error = "Invalid Request or Payment Failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Splitflix</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f0f0f; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1a1a1a; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); max-width: 400px; width: 90%; }
        .icon { font-size: 60px; color: #4CAF50; margin-bottom: 20px; }
        h1 { margin: 0 0 10px; font-size: 24px; }
        p { color: #aaa; margin-bottom: 30px; }
        .btn { background: #e50914; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background 0.3s; }
        .btn:hover { background: #b20710; }
        .error-icon { color: #f44336; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (isset($success)): ?>
            <div class="icon">✔</div>
            <h1>Payment Successful!</h1>
            <p>Your subscription for Group #<?php echo $group_id; ?> has been cleared automatically.</p>
            <a href="../user/dashboard.php" class="btn">Go to Dashboard</a>
        <?php else: ?>
            <div class="icon error-icon">✘</div>
            <h1>Payment Error</h1>
            <p><?php echo $error; ?></p>
            <a href="../user/dashboard.php" class="btn">Return Home</a>
        <?php endif; ?>
    </div>
</body>
</html>
