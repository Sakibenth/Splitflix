<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - Splitflix</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f0f0f; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1a1a1a; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); max-width: 400px; width: 90%; }
        .icon { font-size: 60px; color: #ff9800; margin-bottom: 20px; }
        h1 { margin: 0 0 10px; font-size: 24px; }
        p { color: #aaa; margin-bottom: 30px; }
        .btn { background: #333; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background 0.3s; }
        .btn:hover { background: #444; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠</div>
        <h1>Payment Cancelled</h1>
        <p>You have cancelled the transaction. No payment was made.</p>
        <a href="../user/dashboard.php" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>
