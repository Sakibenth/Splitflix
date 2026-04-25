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
    $plan_id = (int)$_POST['plan_id'];
    $group_name = trim($_POST['group_name']);
    $max_members = (int)$_POST['max_members'];
    $cost_per_member = (float)$_POST['cost_per_member'];
    $validity_start = $_POST['validity_start'];
    $validity_end = $_POST['validity_end'];

    // Basic validation
    if (empty($group_name) || empty($validity_start) || empty($validity_end) || $max_members < 1) {
        $error = "Please fill in all required fields.";
    } elseif ($validity_start > $validity_end) {
        $error = "End date must be after the start date.";
    } else {
        // Calculate initial seats remaining (max members - 1 for the owner, or is the owner taking a seat? Usually owner takes a seat)
        // Let's say seats_remaining = max_members
        $seats_remaining = $max_members;

        $insert_query = "
            INSERT INTO subscription_group (owner_id, platform_id, plan_id, group_name, max_members, seats_remaining, cost_per_member, validity_start, validity_end, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ";
        
        if ($stmt = mysqli_prepare($conn, $insert_query)) {
            mysqli_stmt_bind_param($stmt, "iiisiidss", $owner_id, $platform_id, $plan_id, $group_name, $max_members, $seats_remaining, $cost_per_member, $validity_start, $validity_end);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Group created successfully!";
                // Optional: redirect to dashboard after a delay, or use a success state
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
    // Invalid platform
    header("Location: dashboard.php");
    exit();
}
mysqli_stmt_close($stmt);

// Fetch available plans for this platform
$plans = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM plans WHERE platform_id = ?");
mysqli_stmt_bind_param($stmt, "i", $platform_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $plans[] = $row;
    }
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Group - <?php echo htmlspecialchars($platform['platform_name']); ?> | Splitflix</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/auth.css"> <!-- Reusing some form styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .create-group-container {
            max-width: 600px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .create-group-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: <?php echo htmlspecialchars($platform['brand_color']); ?>;
        }

        .platform-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .platform-header .logo {
            font-size: 2.5rem;
        }

        .platform-header h2 {
            font-size: 1.6rem;
            color: #f0f0f5;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238888aa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }
        
        select.form-input option {
            background-color: #12121a;
            color: #e0e0e0;
        }

        .btn-create {
            width: 100%;
            padding: 14px;
            background: <?php echo htmlspecialchars($platform['brand_color']); ?>;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }

        .btn-create:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px <?php echo htmlspecialchars($platform['brand_color']); ?>40;
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
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-brand">
            <a href="../index.php" class="nav-brand-link">
                <span class="nav-logo">🎬</span>
                <span class="nav-title">Splitflix</span>
            </a>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">My Groups</a>
        </div>
    </nav>

    <main class="dashboard-page">
        <a href="dashboard.php" class="btn-back" style="margin-bottom: 1.5rem;">← Back to Dashboard</a>

        <div class="create-group-container">
            <div class="platform-header">
                <div class="logo" style="color: <?php echo htmlspecialchars($platform['brand_color']); ?>"><?php echo getPlatformIcon($platform['platform_name']); ?></div>
                <div>
                    <h2>Create <?php echo htmlspecialchars($platform['platform_name']); ?> Group</h2>
                    <p style="color: #7777aa; font-size: 0.9rem;">Set up your subscription sharing group</p>
                </div>
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
                        <input type="text" id="group_name" name="group_name" class="form-input" placeholder="e.g. <?php echo htmlspecialchars($platform['platform_name']); ?> Premium Share" required>
                    </div>

                    <div class="form-group">
                        <label for="plan_id">Select Plan</label>
                        <select id="plan_id" name="plan_id" class="form-input" required>
                            <option value="" disabled selected>-- Choose a Plan --</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['plan_id']; ?>">
                                    <?php echo htmlspecialchars($plan['plan_name']); ?> - $<?php echo htmlspecialchars($plan['monthly_cost']); ?>/<?php echo rtrim($plan['billing_type'], 'ly'); ?> 
                                    (Max <?php echo $plan['max_seats']; ?> seats)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_members">Total Allowed Members</label>
                            <input type="number" id="max_members" name="max_members" class="form-input" min="1" max="10" value="4" required>
                        </div>
                        <div class="form-group">
                            <label for="cost_per_member">Price per Member ($)</label>
                            <input type="number" id="cost_per_member" name="cost_per_member" class="form-input" step="0.01" min="0" placeholder="e.g. 5.00" required>
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
    </main>
</body>
</html>
