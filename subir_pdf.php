<?php
session_start();
include "db.php";

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    die("No autorizado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Valores recibidos
    $id_permiso = $_POST['id_permiso'] ?? null;
    $decision = $_POST['decision'] ?? null; // aprobar o rechazar
    $cargo_reviewer = $_POST['cargo_reviewer'] ?? null;

    if (!$id_permiso || !$decision || !$cargo_reviewer) {
        die("Datos incompletos.");
    }

    // Resultado del revisor
    $estado_nuevo = ($decision === 'aprobar') ? 'Aprobado' : 'Rechazado';

    // Nombres exactos de tu BD real
    $cargo_rrhh = 'Jefe de Talento Humano';
    $cargo_rector = 'Rector';

    // Detectar qué columna actualizar
    if (strtolower($cargo_reviewer) === strtolower($cargo_rrhh)) {
        $update_column = 'estado_rrhh';
    } elseif (strtolower($cargo_reviewer) === strtolower($cargo_rector)) {
        $update_column = 'estado_rector';
    } else {
        die("Cargo no válido.");
    }

    // Actualizar estado (RRHH o Rector)
    $stmt = $conn->prepare("UPDATE permisos SET $update_column = ? WHERE id_permiso = ?");
    $stmt->bind_param("si", $estado_nuevo, $id_permiso);
    $stmt->execute();
    $stmt->close();

    // Obtener estado actualizado
    $stmt2 = $conn->prepare("SELECT estado_rrhh, estado_rector FROM permisos WHERE id_permiso = ?");
    $stmt2->bind_param("i", $id_permiso);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $permiso = $result->fetch_assoc();
    $stmt2->close();

    // Calcular estado final
    if ($permiso['estado_rrhh'] === 'Rechazado' || $permiso['estado_rector'] === 'Rechazado') {
        $estado_final = 'Rechazado';
    } elseif ($permiso['estado_rrhh'] === 'Aprobado' && $permiso['estado_rector'] === 'Aprobado') {
        $estado_final = 'Aprobado';
    } elseif ($permiso['estado_rrhh'] === 'Pendiente' || $permiso['estado_rector'] === 'Pendiente') {
        $estado_final = 'Pendiente';
    } else {
        $estado_final = 'Pendiente';
    }

    // ❗ NOTA IMPORTANTE:
    // Tu BD REAL NO tiene columna "estado".
    // NO actualizamos estado general porque NO existe.
    // LOSEP tampoco requiere un estado global.

    header("Location: revisar_solicitudes.php");
    exit();

} else {
    die("Método no permitido.");
}
?>
