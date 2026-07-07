<?php
session_start();
include "db.php";

$cedula = $_POST['cedula'] ?? '';

if (!$cedula) {
    die("Cédula requerida. <a href='login.php'>Volver</a>");
}

$sql = "SELECT * FROM usuarios WHERE cedula = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $cedula);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $_SESSION['usuario'] = $result->fetch_assoc();
    header("Location: dashboard.php");
    exit;
} else {
    echo "Usuario no encontrado. <a href='login.php'>Volver</a>";
}
?>
