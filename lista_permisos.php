<?php
session_start();
include "db.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];

// Permitir acceso solo a cédula 1724063092
if ($usuario['cedula'] !== '1724063092') {
    die("No tienes permiso para acceder a esta página.");
}

$sql = "SELECT p.id_permiso, u.nombres, u.apellidos, tp.nombre_permiso, p.fecha_desde, p.fecha_hasta, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
        WHERE p.estado = 'Pendiente'";

$result = $conn->query($sql);
?>

<h2>Permisos pendientes</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Solicitante</th>
        <th>Tipo de permiso</th>
        <th>Desde</th>
        <th>Hasta</th>
        <th>Estado</th>
        <th>Acción</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['id_permiso'] ?></td>
        <td><?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?></td>
        <td><?= htmlspecialchars($row['nombre_permiso']) ?></td>
        <td><?= $row['fecha_desde'] ?></td>
        <td><?= $row['fecha_hasta'] ?></td>
        <td><?= $row['estado'] ?></td>
        <td><a href="aprobar_rechazar.php?id=<?= $row['id_permiso'] ?>">Revisar</a></td>
    </tr>
    <?php endwhile; ?>
</table>

<a href="logout.php">Cerrar sesión</a>
