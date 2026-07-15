<?php
// Datos de conexión adaptados para tu contenedor Docker
$host = "db"; 
$user = "root";
$pass = "root_password_segura"; // La misma contraseña de tu docker-compose.yml
$db   = "proyecto";             // El nombre de la base de datos en phpMyAdmin

// Crear la conexión con la base de datos
$conn = new mysqli($host, $user, $pass, $db);

// Comprobar si hay errores de conexión
if ($conn->connect_error) {
    die("Error de conexión en Docker: " . $conn->connect_error);
}

// Variable de conexión final para tu sistema
$conexion = $conn; 
?>