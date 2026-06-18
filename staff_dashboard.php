<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_role(['staff']);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$patients = $pdo->query("SELECT patient_id, full_name, medical_id, phone_number FROM patients ORDER BY full_name")->fetchAll();
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("SELECT queue.*, patients.full_name, patients.medical_id FROM queue INNER JOIN patients ON patients.patient_id = queue.patient_id WHERE patients.full_name LIKE :search OR patients.medical_id LIKE :search OR queue.queue_number LIKE :search ORDER BY queue.arrival_time DESC");
    $stmt->execute([':search' => '%' . $search . '%']);
    $queueItems = $stmt->fetchAll();
} else {
    $queueItems = $pdo->query("SELECT queue.*, patients.full_name, patients.medical_id FROM queue INNER JOIN patients ON patients.patient_id = queue.patient_id WHERE queue.status <> 'cancelled' ORDER BY FIELD(queue.status, 'called', 'in_consultation', 'waiting', 'completed'), queue.arrival_time ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - SmartQueue</title>
    <link rel="stylesheet" href="../frontend/dashboard.css">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">SmartQueue</div>
            <a class="nav-link active" href="#registration">Patient Registration</a>
            <a class="nav-link" href="#queue-add">Add to Queue</a>
            <a class="nav-link" href="#queue">Queue Management</a>
        </aside>

        <main class="main">
            <div class="topbar">
                <div>
                    <p class="eyebrow">Reception</p>
                    <h1>Staff Queue Management</h1>
                </div>
                <a class="logout" href="logout.php">Log Out</a>
            </div>

            <?php if ($flash): ?>
                <p class="notice"><?php echo e($flash); ?></p>
            <?php endif; ?>

            <div class="grid">
                <section id="registration" class="panel">
                    <h2>Register New Patient</h2>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="register_patient">
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" required>
                        <div class="form-row">
                            <div>
                                <label for="phone_number">Phone Number</label>
                                <input id="phone_number" name="phone_number" required>
                            </div>
                            <div>
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" required>
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="date_of_birth">Date of Birth</label>
                                <input id="date_of_birth" name="date_of_birth" type="date" required>
                            </div>
                        </div>
                        <label for="medical_id">Medical/Patient ID</label>
                        <input id="medical_id" name="medical_id" placeholder="Leave blank to auto-generate">
                        <label for="password">Patient Login Password</label>
                        <input id="password" name="password" placeholder="Leave blank to auto-generate">
                        <button type="submit">Register Patient</button>
                    </form>
                </section>

                <section id="queue-add" class="panel">
                    <h2>Add Registered Patient to Queue</h2>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="add_to_queue">
                        <label for="patient_id">Patient</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo (int) $patient['patient_id']; ?>">
                                    <?php echo e($patient['full_name'] . ' - ' . $patient['medical_id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Generate Queue Number</button>
                    </form>
                    <p class="notice">Queue numbers are generated automatically for today's queue.</p>
                </section>
            </div>

            <section id="queue" class="panel">
                <div class="topbar">
                    <h2>Current Waiting Patients</h2>
                    <form method="GET">
                        <input name="search" placeholder="Search queue" value="<?php echo e($search); ?>">
                    </form>
                </div>
                <table>
                    <thead><tr><th>Queue No.</th><th>Patient</th><th>Patient ID</th><th>Status</th><th>Update Status</th><th>Cancel</th></tr></thead>
                    <tbody>
                        <?php foreach ($queueItems as $item): ?>
                            <tr>
                                <td><?php echo e($item['queue_number']); ?></td>
                                <td><?php echo e($item['full_name']); ?></td>
                                <td><?php echo e($item['medical_id']); ?></td>
                                <td><span class="badge badge-<?php echo e($item['status']); ?>"><?php echo e(str_replace('_', ' ', $item['status'])); ?></span></td>
                                <td>
                                    <form action="actions.php" method="POST">
                                        <input type="hidden" name="action" value="update_queue">
                                        <input type="hidden" name="queue_id" value="<?php echo (int) $item['queue_id']; ?>">
                                        <select name="status">
                                            <option value="waiting" <?php echo $item['status'] === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                                            <option value="called" <?php echo $item['status'] === 'called' ? 'selected' : ''; ?>>Called</option>
                                            <option value="in_consultation" <?php echo $item['status'] === 'in_consultation' ? 'selected' : ''; ?>>In consultation</option>
                                            <option value="completed" <?php echo $item['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <button type="submit">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form action="actions.php" method="POST">
                                        <input type="hidden" name="action" value="remove_queue">
                                        <input type="hidden" name="queue_id" value="<?php echo (int) $item['queue_id']; ?>">
                                        <button class="danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>
