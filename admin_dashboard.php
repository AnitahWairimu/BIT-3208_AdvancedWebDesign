<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_role(['admin']);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$stats = [
    'patients' => 0,
    'staff' => 0,
    'queue' => 0,
    'completed' => 0,
    'avg_wait' => 0
];

$stats['patients'] = (int) $pdo->query("SELECT COUNT(*) AS total FROM patients")->fetch()['total'];
$stats['staff'] = (int) $pdo->query("SELECT COUNT(*) AS total FROM staff")->fetch()['total'];
$stats['queue'] = (int) $pdo->query("SELECT COUNT(*) AS total FROM queue WHERE status IN ('waiting', 'called', 'in_consultation')")->fetch()['total'];
$stats['completed'] = (int) $pdo->query("SELECT COUNT(*) AS total FROM queue WHERE status = 'completed'")->fetch()['total'];
$stats['avg_wait'] = (int) ($pdo->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, arrival_time, COALESCE(service_time, completed_at, NOW()))), 0) AS minutes FROM queue WHERE status <> 'cancelled'")->fetch()['minutes'] ?? 0);

$staffMembers = $pdo->query("SELECT users.user_id, users.email, staff.full_name, staff.department, users.created_at FROM users INNER JOIN staff ON staff.user_id = users.user_id WHERE users.role = 'staff' ORDER BY staff.full_name")->fetchAll();
$patients = $pdo->query("SELECT patients.*, users.email FROM patients INNER JOIN users ON users.user_id = patients.user_id ORDER BY patients.created_at DESC LIMIT 20")->fetchAll();
$allPatients = $pdo->query("SELECT patient_id, full_name, medical_id FROM patients ORDER BY full_name")->fetchAll();
$queueItems = $pdo->query("SELECT queue.*, patients.full_name, patients.medical_id FROM queue INNER JOIN patients ON patients.patient_id = queue.patient_id ORDER BY queue.arrival_time DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SmartQueue</title>
    <link rel="stylesheet" href="../frontend/dashboard.css">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">SmartQueue</div>
            <a class="nav-link active" href="#overview">Overview</a>
            <a class="nav-link" href="#staff">Staff Accounts</a>
            <a class="nav-link" href="#patients">Patient Records</a>
            <a class="nav-link" href="#queue">Queue Monitor</a>
            <a class="nav-link" href="#reports">Reports</a>
            <a class="nav-link" href="#settings">Settings</a>
        </aside>

        <main class="main">
            <div class="topbar">
                <div>
                    <p class="eyebrow">Administrator</p>
                    <h1>Full System Control</h1>
                </div>
                <a class="logout" href="logout.php">Log Out</a>
            </div>

            <?php if ($flash): ?>
                <p class="notice"><?php echo e($flash); ?></p>
            <?php endif; ?>

            <section id="overview" class="cards">
                <div class="card"><div class="card-label">Registered Patients</div><div class="card-value"><?php echo $stats['patients']; ?></div></div>
                <div class="card"><div class="card-label">Staff Members</div><div class="card-value"><?php echo $stats['staff']; ?></div></div>
                <div class="card"><div class="card-label">Currently Queued</div><div class="card-value"><?php echo $stats['queue']; ?></div></div>
                <div class="card"><div class="card-label">Completed Consultations</div><div class="card-value"><?php echo $stats['completed']; ?></div></div>
                <div class="card"><div class="card-label">Avg Wait Time</div><div class="card-value"><?php echo $stats['avg_wait']; ?>m</div></div>
            </section>

            <div class="grid">
                <section id="staff" class="panel">
                    <h2>Add Staff Member</h2>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="add_staff">
                        <label for="staff_name">Full Name</label>
                        <input id="staff_name" name="full_name" required>
                        <label for="staff_email">Email</label>
                        <input id="staff_email" name="email" type="email" required>
                        <label for="staff_department">Department</label>
                        <input id="staff_department" name="department" value="Reception">
                        <label for="staff_password">Temporary Password</label>
                        <input id="staff_password" name="password" placeholder="Leave blank to auto-generate">
                        <button type="submit">Add Staff</button>
                    </form>
                </section>

                <section class="panel">
                    <h2>Register Patient</h2>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="register_patient">
                        <label for="admin_patient_name">Full Name</label>
                        <input id="admin_patient_name" name="full_name" required>
                        <div class="form-row">
                            <div>
                                <label for="admin_patient_phone">Phone</label>
                                <input id="admin_patient_phone" name="phone_number" required>
                            </div>
                            <div>
                                <label for="admin_patient_email">Email</label>
                                <input id="admin_patient_email" name="email" type="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label for="admin_patient_gender">Gender</label>
                                <select id="admin_patient_gender" name="gender">
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="admin_patient_dob">Date of Birth</label>
                                <input id="admin_patient_dob" name="date_of_birth" type="date" required>
                            </div>
                        </div>
                        <label for="admin_patient_medical_id">Medical/Patient ID</label>
                        <input id="admin_patient_medical_id" name="medical_id" placeholder="Leave blank to auto-generate">
                        <label for="admin_patient_password">Patient Password</label>
                        <input id="admin_patient_password" name="password" placeholder="Leave blank to auto-generate">
                        <button type="submit">Create Patient</button>
                    </form>
                </section>
            </div>

            <section class="panel">
                <h2>Add Patient to Queue</h2>
                <form action="actions.php" method="POST">
                    <input type="hidden" name="action" value="add_to_queue">
                    <label for="admin_queue_patient">Patient</label>
                    <select id="admin_queue_patient" name="patient_id" required>
                        <option value="">Select patient</option>
                        <?php foreach ($allPatients as $patient): ?>
                            <option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo e($patient['full_name'] . ' - ' . $patient['medical_id']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Generate Queue Number</button>
                </form>
            </section>

            <section class="panel">
                <h2>Manage Staff Accounts</h2>
                <?php foreach ($staffMembers as $member): ?>
                    <form action="actions.php" method="POST" class="panel">
                        <input type="hidden" name="action" value="update_staff">
                        <input type="hidden" name="user_id" value="<?php echo (int) $member['user_id']; ?>">
                        <div class="form-row">
                            <div>
                                <label>Name</label>
                                <input name="full_name" value="<?php echo e($member['full_name']); ?>">
                            </div>
                            <div>
                                <label>Email</label>
                                <input name="email" type="email" value="<?php echo e($member['email']); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Department</label>
                                <input name="department" value="<?php echo e($member['department']); ?>">
                            </div>
                            <div>
                                <label>Assigned Role</label>
                                <input value="Staff" disabled>
                            </div>
                        </div>
                        <button type="submit">Save Staff Details</button>
                    </form>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="user_id" value="<?php echo (int) $member['user_id']; ?>">
                        <button class="danger" type="submit">Delete Staff Account</button>
                    </form>
                    <br>
                <?php endforeach; ?>
            </section>

            <section id="patients" class="panel">
                <h2>Manage Patient Records</h2>
                <?php foreach ($patients as $patient): ?>
                    <form action="actions.php" method="POST" class="panel">
                        <input type="hidden" name="action" value="update_patient">
                        <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
                        <div class="form-row">
                            <div>
                                <label>Name</label>
                                <input name="full_name" value="<?php echo e($patient['full_name']); ?>">
                            </div>
                            <div>
                                <label>Patient ID</label>
                                <input name="medical_id" value="<?php echo e($patient['medical_id']); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Phone</label>
                                <input name="phone_number" value="<?php echo e($patient['phone_number']); ?>">
                            </div>
                            <div>
                                <label>Date of Birth</label>
                                <input name="date_of_birth" type="date" value="<?php echo e($patient['date_of_birth']); ?>">
                            </div>
                        </div>
                        <label>Gender</label>
                        <select name="gender">
                            <option value="female" <?php echo $patient['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="male" <?php echo $patient['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="other" <?php echo $patient['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <button type="submit">Save Patient Details</button>
                    </form>
                    <form action="actions.php" method="POST">
                        <input type="hidden" name="action" value="delete_patient">
                        <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
                        <button class="danger" type="submit">Remove Patient Record</button>
                    </form>
                    <br>
                <?php endforeach; ?>
            </section>

            <section id="queue" class="panel">
                <h2>Queue Management Overview</h2>
                <table>
                    <thead><tr><th>Queue No.</th><th>Patient</th><th>Patient ID</th><th>Status</th><th>Arrival</th></tr></thead>
                    <tbody>
                        <?php foreach ($queueItems as $item): ?>
                            <tr>
                                <td><?php echo e($item['queue_number']); ?></td>
                                <td><?php echo e($item['full_name']); ?></td>
                                <td><?php echo e($item['medical_id']); ?></td>
                                <td><span class="badge badge-<?php echo e($item['status']); ?>"><?php echo e(str_replace('_', ' ', $item['status'])); ?></span></td>
                                <td><?php echo e($item['arrival_time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section id="reports" class="panel">
                <h2>Reports</h2>
                <div class="grid">
                    <div class="notice">Daily queue reports: <?php echo $stats['queue']; ?> active queue entries today.</div>
                    <div class="notice">Patient attendance reports: <?php echo $stats['patients']; ?> registered patients.</div>
                    <div class="notice">Service performance reports: average wait is <?php echo $stats['avg_wait']; ?> minutes.</div>
                </div>
            </section>

            <section id="settings" class="panel">
                <h2>System Settings</h2>
                <label>Facility Name</label>
                <input value="SmartQueue Healthcare Facility" disabled>
                <label>Default Consultation Duration</label>
                <input value="10 minutes" disabled>
                <p class="notice">Settings area is reserved for facility-wide configuration.</p>
            </section>
        </main>
    </div>
</body>
</html>
