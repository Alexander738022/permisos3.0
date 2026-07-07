<?php
// Configuración para Docker - ¡No cambies 'db'!
$host = 'db'; 
$dbname = 'proyecto';               // <-- Aquí ya está el nombre real de tu base de datos
$username = 'root';                // Usaremos el usuario root de Docker
$password = 'root_password_segura'; // Contraseña que coincidirá con Docker

try {
    // Conexión mediante PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error crítico de conexión en Docker: " . $e->getMessage());
}
?>