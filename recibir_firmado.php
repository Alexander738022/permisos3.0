<?php
include "db.php";

// 1. Obtener el ID del permiso
$id_permiso = isset($_GET['id_permiso']) ? intval($_GET['id_permiso']) : null;

// 2. Obtener el PDF firmado
$documento_firmado = file_get_contents('php://input');

if ($documento_firmado && $id_permiso) {

    // 3. Crear nombre del archivo
    $nombre_archivo = "permiso_" . $id_permiso . "_firmado.pdf";
    $ruta_destino = "uploads/" . $nombre_archivo;

    if (file_put_contents($ruta_destino, $documento_firmado)) {

        // 4. Actualizar BD con prepared statement
        $stmt = $conn->prepare(
            "UPDATE permisos SET archivo_justificativo = ? WHERE id_permiso = ?"
        );
        $stmt->bind_param("si", $ruta_destino, $id_permiso);

        if ($stmt->execute()) {
            http_response_code(200);
            echo "Archivo firmado guardado correctamente";
        } else {
            http_response_code(500);
            echo "Error al actualizar BD";
        }

        $stmt->close();

    } else {
        http_response_code(500);
        echo "No se pudo guardar el archivo";
    }

} else {
    http_response_code(400);
    echo "Faltan datos";
}
?>
