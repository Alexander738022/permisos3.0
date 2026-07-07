<?php
session_start();
include "db.php";

// Solo el administrador o RRHH debería ver esto
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    die("Acceso denegado");
}

// 1. Identificar el mes pasado
$mes_pasado = date('m', strtotime("-1 month"));
$anio_pasado = date('Y', strtotime("-1 month"));

// 2. Consulta para obtener el resumen de todos los usuarios
$sql = "SELECT 
            u.nombres, 
            u.apellidos, 
            u.cedula,
            SUM(CASE WHEN tp.nombre_permiso LIKE '%Personal%' THEN p.horas_solicitadas ELSE 0 END) as horas_particulares,
            SUM(CASE WHEN p.tipo_duracion = 'Días' THEN 1 ELSE 0 END) as dias_totales
        FROM usuarios u
        LEFT JOIN permisos p ON u.id_usuario = p.id_usuario 
            AND MONTH(p.fecha_solicitud) = ? 
            AND YEAR(p.fecha_solicitud) = ?
            AND p.estado = 'Aprobado'
        LEFT JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
        GROUP BY u.id_usuario";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $mes_pasado, $anio_pasado);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Reporte Mensual de Permisos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Informe Mensual de Talento Humano</h1>
        <p>Periodo: <?= $mes_pasado ?> / <?= $anio_pasado ?></p>

        <table border="1">
            <thead>
                <tr>
                    <th>Funcionario</th>
                    <th>Cédula</th>
                    <th>Horas Particulares</th>
                    <th>Días a Descontar (Vacaciones)</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    // Regla LOSEP: 8 horas = 1 día
                    $descuento = floor($row['horas_particulares'] / 8);
                ?>
                <tr>
                    <td><?= $row['nombres'] . " " . $row['apellidos'] ?></td>
                    <td><?= $row['cedula'] ?></td>
                    <td><?= number_format($row['horas_particulares'], 2) ?> h</td>
                    <td><strong><?= $descuento ?> día(s)</strong></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <button onclick="window.print()">Imprimir para Archivo</button>
    </div>
</body>
</html>