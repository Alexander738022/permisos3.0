<?php
// Datos adaptados para Docker (sin tocar la estructura de tu código)
$host = "db"; // Docker busca el contenedor 'db' en lugar de 'localhost'
$user = "root";
$pass = "root_password_segura"; // La contraseña que le asignamos en el docker-compose
$db = "proyecto";

// Tu misma conexión original
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error de conexión en Docker: " . $conn->connect_error);
}
$conexion = $conn; 
?>