<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include "db.php";

$id_usuario = $_SESSION['usuario']['id_usuario'];
$id_tipo_permiso = $_POST['id_tipo_permiso'] ?? '';
$tipo_duracion = $_POST['tipo_duracion'] ?? '';
$fecha_desde = $_POST['fecha_desde'] ?? '';
$fecha_hasta = $_POST['fecha_hasta'] ?? '';
$hora_desde = $_POST['hora_desde'] ?? null;
$hora_hasta = $_POST['hora_hasta'] ?? null;
$observaciones = $_POST['observaciones'] ?? null;
$estado = 'Pendiente';

// Validaciones básicas
if (!$id_tipo_permiso || !$tipo_duracion || !$fecha_desde || !$fecha_hasta) {
    die("Faltan datos obligatorios. <a href='permiso_nuevo.php'>Volver</a>");
}

// Si tipo_duracion es Días, ignoramos horas
if ($tipo_duracion === 'Días') {
    $hora_desde = null;
    $hora_hasta = null;
}

$sql = "INSERT INTO permisos (
    id_usuario, id_tipo_permiso, tipo_duracion, fecha_desde, fecha_hasta,
    hora_desde, hora_hasta, observaciones, estado
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iisssssss",
    $id_usuario, $id_tipo_permiso, $tipo_duracion, $fecha_desde, $fecha_hasta,
    $hora_desde, $hora_hasta, $observaciones, $estado
);

if ($stmt->execute()) {
    $id_permiso = $stmt->insert_id;

// redirigir para generar y firmar automáticamente
header("Location: CERTIFICADOS/generar_certificado.php?id_permiso=$id_permiso");
exit;

} else {
    echo "Error al guardar: " . $conn->error;

}

?>
