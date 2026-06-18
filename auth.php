<?php
// SmartQueue - Authentication and Role Guards

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../frontend/login.html");
        exit();
    }
}

function require_role(array $allowedRoles): void
{
    require_login();

    if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        echo "<h2>Access Denied</h2>";
        echo "<p>You do not have permission to access this page.</p>";
        exit();
    }
}

function dashboard_for_role(string $role): string
{
    $dashboards = [
        'admin' => 'admin_dashboard.php',
        'staff' => 'staff_dashboard.php',
        'patient' => 'patient_dashboard.php'
    ];

    return $dashboards[$role] ?? '../frontend/login.html';
}

function redirect_to_role_dashboard(string $role): void
{
    header("Location: " . dashboard_for_role($role));
    exit();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>