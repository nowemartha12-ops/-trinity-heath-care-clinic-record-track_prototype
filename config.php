<?php
$host = 'localhost';
$db   = 'trinity_clinic';
$user = 'root';
$pass = '';

date_default_timezone_set('Africa/Kampala');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Could not connect to the database. Did you import database.sql? ' . $e->getMessage());
}