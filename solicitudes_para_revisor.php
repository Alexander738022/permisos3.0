<?php
session_start();
include "db.php";

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['cedula'] !== '1724063092') {
    die("No autorizado.");
}

$sql = "SELECT p.id_permiso, u.nombres, u.apellidos, tp.nombre_permiso, 
               p.fecha_solicitud, p.fecha_desde, p.fecha_hasta, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
        ORDER BY p.fecha_solicitud DESC";

$result = $conn->query($sql);
?>

<h2>Todas las solicitudes</h2>
<table border="1">
<tr>
    <th>ID</th><th>Solicitante</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Estado</th><th>Acción</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['id_permiso'] ?></td>
    <td><?= $row['nombres'] ?> <?= $row['apellidos'] ?></td>
    <td><?= $row['nombre_permiso'] ?></td>
    <td><?= $row['fecha_desde'] ?></td>
    <td><?= $row['fecha_hasta'] ?></td>
    <td><?= $row['estado'] ?></td>
    <td>
        <?php if ($row['estado'] === 'Pendiente'): ?>
            <a href="aprobar_rechazar.php?id=<?= $row['id_permiso'] ?>">Revisar</a> |
            <a href="eliminar_permiso.php?id=<?= $row['id_permiso'] ?>" onclick="return confirm('¿Seguro que deseas eliminar esta solicitud?')">Eliminar</a>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</table>

<br><br>
<form action="dashboard.php">
    <button type="submit">Volver al inicio</button>
</form>
