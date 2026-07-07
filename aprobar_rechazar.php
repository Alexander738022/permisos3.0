<?php
session_start();
include "db.php";

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    die("No autorizado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------
    // 1. Validación de datos recibidos
    // ------------------------------
    $id_permiso = $_POST['id_permiso'] ?? null;
    $decision = $_POST['decision'] ?? null;
    $cargo_reviewer = $_POST['cargo_reviewer'] ?? null;

    if (!$id_permiso || !$decision || !$cargo_reviewer) {
        die("Datos incompletos.");
    }

    // Resultado que se guardará
    $estado_nuevo = ($decision === 'aprobar') ? 'Aprobado' : 'Rechazado';

    // Cargos válidos según la BD real
    $cargo_rrhh  = 'Jefe de Talento Humano';
    $cargo_rector = 'Rector';

    // ------------------------------
    // 2. Determinar qué campo actualizar
    // ------------------------------
    if (strtolower($cargo_reviewer) === strtolower($cargo_rrhh)) {
        $update_column = 'estado_rrhh';
    } elseif (strtolower($cargo_reviewer) === strtolower($cargo_rector)) {
        $update_column = 'estado_rector';
    } else {
        die("Cargo de revisor no válido.");
    }

    // ------------------------------
    // 3. Actualizar estado RRHH o Rector
    // ------------------------------
    $stmt = $conn->prepare("UPDATE permisos SET {$update_column} = ? WHERE id_permiso = ?");
$stmt->bind_param("si", $estado_nuevo, $id_permiso);
$stmt->execute();
$stmt->close();

// ------------------------------
// 3b. Si RRHH rechaza, actualizar automáticamente el estado del Rector
// ------------------------------
if (strtolower($cargo_reviewer) === strtolower($cargo_rrhh) && $estado_nuevo === 'Rechazado') {
    $stmt_rector = $conn->prepare("UPDATE permisos SET estado_rector = 'Rechazado' WHERE id_permiso = ?");
    $stmt_rector->bind_param("i", $id_permiso);
    $stmt_rector->execute();
    $stmt_rector->close();
}

    // ------------------------------
    // 4. Obtener estados actuales del permiso
    // ------------------------------
    $stmt_sel = $conn->prepare("SELECT estado_rrhh, estado_rector FROM permisos WHERE id_permiso = ?");
    $stmt_sel->bind_param("i", $id_permiso);
    $stmt_sel->execute();
    $res = $stmt_sel->get_result();
    $permiso = $res->fetch_assoc();
    $stmt_sel->close();

    // ------------------------------
    // 5. Calcular estado final
    // ------------------------------
    if ($permiso['estado_rrhh'] === 'Rechazado' || $permiso['estado_rector'] === 'Rechazado') {
        $estado_final = 'Rechazado';
    } elseif ($permiso['estado_rrhh'] === 'Aprobado' && $permiso['estado_rector'] === 'Aprobado') {
        $estado_final = 'Aprobado';
    } elseif ($permiso['estado_rrhh'] === 'Pendiente' || $permiso['estado_rector'] === 'Pendiente') {
        $estado_final = 'Pendiente';
    } else {
        $estado_final = 'Pendiente';
    }

    // ------------------------------
    // 6. Guardar estado general (columna estado)
    // ------------------------------
    $stmt_final = $conn->prepare("UPDATE permisos SET estado = ? WHERE id_permiso = ?");
    $stmt_final->bind_param("si", $estado_final, $id_permiso);
    $stmt_final->execute();
    $stmt_final->close();

    // ------------------------------
    // 7. Redirigir de vuelta a revisar_solicitudes.php
    // ------------------------------
    header("Location: revisar_solicitudes.php");
    exit;
    
} else {
    die("Método no permitido.");
}
?>
