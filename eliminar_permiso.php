<?php
session_start();
include "db.php";

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    die("No autorizado.");
}

$usuario = $_SESSION['usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_permiso'] ?? null;
    if (!$id) {
        die("ID de permiso no especificado.");
    }

    // Verificar que el permiso pertenezca al usuario
    $stmt = $conn->prepare("SELECT id_usuario FROM permisos WHERE id_permiso = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $permiso = $result->fetch_assoc();
    if (!$permiso || $permiso['id_usuario'] != $usuario['id_usuario']) {
        die("No tienes permiso para eliminar este registro.");
    }

    // Eliminar el permiso directamente
    $stmt = $conn->prepare("DELETE FROM permisos WHERE id_permiso = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Redirigir a mis_solicitudes.php
    header("Location: mis_solicitudes.php");
    exit;
} else {
    die("Solicitud inválida.");
}
?>
