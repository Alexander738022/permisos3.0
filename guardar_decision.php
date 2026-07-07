<?php

// Nota: Este archivo está diseñado para ser incluido (include) en otro script, no para ser accedido directamente.
function guardar_decision($conn, $id_permiso, $accion, $cargo_reviewer) {
    // Definir la decisión en el formato de la base de datos
    $decision = ($accion === 'aprobar') ? 'Aprobado' : 'Rechazado';
    $columna_aprobacion = ($cargo_reviewer === 'Rector') ? 'aprobacion_rector' : 'aprobacion_rhh';

    // 1. Actualizar la columna de aprobación específica
    $sql_update_aprobacion = "UPDATE permisos SET $columna_aprobacion = ? WHERE id_permiso = ?";
    $stmt_update_aprobacion = $conn->prepare($sql_update_aprobacion);
    $stmt_update_aprobacion->bind_param("si", $decision, $id_permiso);
    
    if (!$stmt_update_aprobacion->execute()) {
        error_log("Error al actualizar la aprobación de $cargo_reviewer: " . $stmt_update_aprobacion->error);
        $stmt_update_aprobacion->close();
        return false;
    }
    $stmt_update_aprobacion->close();

    // 2. Leer las aprobaciones actuales para ambos cargos
    $sql_read_aprobaciones = "SELECT aprobacion_rector, aprobacion_rhh FROM permisos WHERE id_permiso = ?";
    $stmt_read_aprobaciones = $conn->prepare($sql_read_aprobaciones);
    $stmt_read_aprobaciones->bind_param("i", $id_permiso);
    $stmt_read_aprobaciones->execute();
    $result_aprobaciones = $stmt_read_aprobaciones->get_result();
    $aprobaciones = $result_aprobaciones->fetch_assoc();
    $stmt_read_aprobaciones->close();

    // 3. Determinar el nuevo estado general
    $nuevo_estado_general = 'Pendiente';
    $rector_decision = $aprobaciones['aprobacion_rector'];
    $rhh_decision = $aprobaciones['aprobacion_rhh'];

    // Si ambos han tomado una decisión
    if ($rector_decision !== NULL && $rhh_decision !== NULL) {
        if ($rector_decision === 'Aprobado' && $rhh_decision === 'Aprobado') {
            $nuevo_estado_general = 'Aceptado';
        } else {
            $nuevo_estado_general = 'Rechazado';
        }
    } 
    // Si solo uno ha tomado una decisión
    else if ($rector_decision !== NULL || $rhh_decision !== NULL) {
        if ($rector_decision === 'Aprobado' || $rhh_decision === 'Aprobado') {
             $nuevo_estado_general = 'Parcialmente Aceptado';
        } else {
            $nuevo_estado_general = 'Parcialmente Aceptado';
        }
    }

    // 4. Actualizar el estado general en la base de datos
    $sql_update_estado = "UPDATE permisos SET estado = ? WHERE id_permiso = ?";
    $stmt_update_estado = $conn->prepare($sql_update_estado);
    $stmt_update_estado->bind_param("si", $nuevo_estado_general, $id_permiso);

    if (!$stmt_update_estado->execute()) {
        error_log("Error al actualizar el estado general: " . $stmt_update_estado->error);
        $stmt_update_estado->close();
        return false;
    }
    $stmt_update_estado->close();

    return true; // Éxito
}

?>