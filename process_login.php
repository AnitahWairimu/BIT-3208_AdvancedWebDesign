<?php

session_start();

require_once 'db_connect.php';
require_once 'auth.php';


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}


$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';



$stmt = $pdo->prepare("
    SELECT user_id, email, password_hash, role
    FROM users
    WHERE email = ?
    LIMIT 1
");


$stmt->execute([$email]);


$user = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$user) {
    die("User not found");
}



if (!password_verify($password, $user['password_hash'])) {
    die("Password incorrect");
}



// Login successful

session_regenerate_id(true);

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];



redirect_to_role_dashboard($user['role']);

?>