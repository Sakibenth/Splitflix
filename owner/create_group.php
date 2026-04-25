<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/icons.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$platform_id = isset($_GET['platform_id']) ? (int)$_GET['platform_id'] : 0;

// Handle Form Submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_description = trim($_POST['plan_description']);
    $group_name = trim($_POST['group_name']);
    $max_members = (int)$_POST['max_members'];
    $cost_per_member = (float)$_POST['cost_per_member'];
    $validity_start = $_POST['validity_start'];
    $validity_end = $_POST['validity_end'];

    // Basic validation
    if (empty($group_name) || empty($plan_description) || empty($validity_start) || empty($validity_end) || $max_members < 1) {
        $error = "Please fill in all required fields.";
    } elseif ($validity_start > $validity_end) {
        $error = "End date must be after the start date.";
    } else {
        $seats_remaining = $max_members;

        $insert_query = "
            INSERT INTO subscription_group (owner_id, platform_id, plan_description, group_name, max_members, seats_remaining, cost_per_member, validity_start, validity_end, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ";
        
        if ($stmt = mysqli_prepare($conn, $insert_query)) {
            mysqli_stmt_bind_param($stmt, "iissiidss", $owner_id, $platform_id, $plan_description, $group_name, $max_members, $seats_remaining, $cost_per_member, $validity_start, $validity_end);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Group created successfully!";
            } else {
                $error = "Error creating group: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database preparation failed.";
        }
    }
}

// Fetch platform info
$platform = null;
$stmt = mysqli_prepare($conn, "SELECT * FROM platforms WHERE platform_id = ?");
mysqli_stmt_bind_param($stmt, "i", $platform_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && mysqli_num_rows($res) > 0) {
    $platform = mysqli_fetch_assoc($res);
} else {
    header("Location: dashboard.php");
    exit();
}
mysqli_stmt_close($stmt);

$brand_color = htmlspecialchars($platform['brand_color'] ?? '#e50914');
$platform_name = htmlspecialchars($platform['platform_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Group - <?php echo $platform_name; ?> | Splitflix</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #0a0a14;
            color: #e0e0e0;
            display: flex;
            min-height: 100vh;
            overflow: hidden; /* Prevent body scroll if possible */
        }

        /* Split Screen Layout */
        .left-panel {
            width: 50vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 2rem 3rem;
            overflow-y: auto;
            position: relative;
        }

        .right-panel {
            width: 50vw;
            height: 100vh;
            background: linear-gradient(135deg, rgba(10, 10, 20, 0.9), rgba(0, 0, 0, 0.95)), <?php echo $brand_color; ?>20;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            border-left: 1px solid rgba(255, 255, 255, 0.05);
        }

        .right-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, <?php echo $brand_color; ?>40 0%, transparent 70%);
            pointer-events: none;
        }

        .right-panel .big-icon {
            transform: scale(2.5);
            margin-bottom: 2rem;
            color: <?php echo $brand_color; ?>;
            filter: drop-shadow(0 0 30px <?php echo $brand_color; ?>80);
        }

        .right-panel h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            letter-spacing: -1px;
        }

        /* Left Panel Content */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #8888aa;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
            align-self: flex-start;
        }
        .back-link:hover { color: #fff; }

        .form-container {
            width: 100%;
            max-width: 500px;
            margin: auto; /* Vertically center in the remaining space */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 2rem;
        }
        
        .form-header h2 {
            font-size: 1.8rem;
            color: #fff;
            margin-bottom: 6px;
        }
        
        .form-header p {
            color: #8888aa;
            font-size: 0.95rem;
        }

        /* Robust Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #ccccee;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #f0f0f5;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 80px;
        }

        .form-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: <?php echo $brand_color; ?>;
            box-shadow: 0 0 0 4px <?php echo $brand_color; ?>20;
        }

        .form-input::placeholder { color: #555577; }

        .form-row {
            display: flex;
            gap: 1.5rem;
        }

        .form-row .form-group { flex: 1; }

        .btn-create {
            width: 100%;
            padding: 16px;
            background: <?php echo $brand_color; ?>;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        .btn-create:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px <?php echo $brand_color; ?>40;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        @media (max-width: 900px) {
            body { flex-direction: column; overflow: auto; }
            .left-panel, .right-panel { width: 100%; height: auto; min-height: 50vh; }
            .left-panel { order: 2; padding: 2rem 1.5rem; }
            .right-panel { order: 1; padding: 3rem 1.5rem; }
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

    <div class="left-panel">
        <a href="dashboard.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Dashboard
        </a>

        <div class="form-container">
            <div class="form-header">
                <h2>Create Group</h2>
                <p>Set up your subscription sharing rules</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <div style="margin-top: 10px;">
                        <a href="dashboard.php" style="color: #fff; text-decoration: underline;">Go back to dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <form action="create_group.php?platform_id=<?php echo $platform_id; ?>" method="POST">
                    
                    <div class="form-group">
                        <label for="group_name">Group Name</label>
                        <input type="text" id="group_name" name="group_name" class="form-input" placeholder="e.g. <?php echo $platform_name; ?> Premium Share" required>
                    </div>

                    <div class="form-group">
                        <label for="plan_description">Plan Description (Viewable by Members)</label>
                        <textarea id="plan_description" name="plan_description" class="form-input" placeholder="e.g. 4K HDR Premium Plan, auto-renews monthly" required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_members">Total Allowed Members</label>
                            <input type="number" id="max_members" name="max_members" class="form-input" min="1" max="10" value="4" required>
                        </div>
                        <div class="form-group">
                            <label for="cost_per_member">Price per Member (৳)</label>
                            <input type="number" id="cost_per_member" name="cost_per_member" class="form-input" step="1" min="0" placeholder="e.g. 150" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="validity_start">Validity Start Date</label>
                            <input type="date" id="validity_start" name="validity_start" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="validity_end">Validity End Date</label>
                            <input type="date" id="validity_end" name="validity_end" class="form-input" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-create">Create Group</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="right-panel">
        <div class="big-icon">
            <?php echo getPlatformIcon($platform['platform_name']); ?>
        </div>
        <h1><?php echo $platform_name; ?></h1>
    </div>

</body>
</html>
