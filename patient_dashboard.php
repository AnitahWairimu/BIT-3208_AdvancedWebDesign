<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_role(['patient']);

$stmt = $pdo->prepare("SELECT patients.*, users.email FROM patients INNER JOIN users ON users.user_id = patients.user_id WHERE patients.user_id = :user_id LIMIT 1");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    echo "<h2>Profile Missing</h2>";
    echo "<p>Your patient profile has not been linked. Please contact reception.</p>";
    echo "<br><a href='logout.php'>Log out</a>";
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM queue WHERE patient_id = :patient_id AND status IN ('waiting', 'called', 'in_consultation') ORDER BY arrival_time DESC LIMIT 1");
$stmt->execute([':patient_id' => $patient['patient_id']]);
$myQueue = $stmt->fetch();

$currentServing = $pdo->query("SELECT queue_number FROM queue WHERE status IN ('called', 'in_consultation') ORDER BY FIELD(status, 'in_consultation', 'called'), arrival_time ASC LIMIT 1")->fetch();
$peopleAhead = 0;
$estimatedWait = 0;

if ($myQueue) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS ahead FROM queue WHERE status IN ('waiting', 'called', 'in_consultation') AND arrival_time < :arrival_time");
    $stmt->execute([':arrival_time' => $myQueue['arrival_time']]);
    $peopleAhead = (int) ($stmt->fetch()['ahead'] ?? 0);
    $estimatedWait = $peopleAhead * 10;
}

$announcements = $pdo->query("SELECT message FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Patient Dashboard - SmartQueue</title>
    <link rel="stylesheet" href="../frontend/dashboard.css">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">SmartQueue</div>
            <a class="nav-link active" href="#status">Queue Status</a>
            <a class="nav-link" href="#profile">My Profile</a>
            <a class="nav-link" href="#announcements">Announcements</a>
        </aside>

        <main class="main">
            <div class="topbar">
                <div>
                    <p class="eyebrow">Patient Portal</p>
                    <h1>Read-Only Queue Dashboard</h1>
                </div>
                <a class="logout" href="logout.php">Log Out</a>
            </div>

            <section id="status" class="cards">
                <div class="card">
                    <div class="card-label">Currently Being Served</div>
                    <div class="card-value"><?php echo e($currentServing['queue_number'] ?? 'None'); ?></div>
                </div>
                <div class="card">
                    <div class="card-label">Your Queue Number</div>
                    <div class="card-value"><?php echo e($myQueue['queue_number'] ?? 'Not queued'); ?></div>
                </div>
                <div class="card">
                    <div class="card-label">People Ahead</div>
                    <div class="card-value"><?php echo $peopleAhead; ?></div>
                </div>
                <div class="card">
                    <div class="card-label">Estimated Wait</div>
                    <div class="card-value"><?php echo $estimatedWait; ?>m</div>
                </div>
                <div class="card">
                    <div class="card-label">Your Status</div>
                    <div class="card-value"><?php echo e($myQueue ? str_replace('_', ' ', $myQueue['status']) : 'Waiting list'); ?></div>
                </div>
            </section>

            <div class="grid">
                <section id="profile" class="panel">
                    <h2>Profile Information</h2>
                    <div class="readonly-metric"><span>Name</span><strong><?php echo e($patient['full_name']); ?></strong></div>
                    <br>
                    <div class="readonly-metric"><span>Patient ID</span><strong><?php echo e($patient['medical_id']); ?></strong></div>
                    <br>
                    <div class="readonly-metric"><span>Email</span><strong><?php echo e($patient['email']); ?></strong></div>
                    <br>
                    <div class="readonly-metric"><span>Phone</span><strong><?php echo e($patient['phone_number']); ?></strong></div>
                </section>

                <section id="announcements" class="panel">
                    <h2>Hospital Announcements</h2>
                    <?php if (empty($announcements)): ?>
                        <p class="notice">No active announcements.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <p class="notice"><?php echo e($announcement['message']); ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <p>The dashboard refreshes automatically every 30 seconds.</p>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
