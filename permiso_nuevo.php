<?php
session_start();
include "db.php";
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $usuario['id_usuario'];
    $id_tipo_permiso = $_POST['id_tipo_permiso'];
    $tipo_duracion = $_POST['tipo_duracion'];
    $fecha_desde = $_POST['fecha_desde'];
    $fecha_hasta = $_POST['fecha_hasta'];
    $hora_desde = $_POST['hora_desde'] ?? null;
    $hora_hasta = $_POST['hora_hasta'] ?? null;
    $observaciones = $_POST['observaciones'];

    $sql = "INSERT INTO permisos (id_usuario, id_tipo_permiso, tipo_duracion, fecha_desde, fecha_hasta, hora_desde, hora_hasta, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissssss", $id_usuario, $id_tipo_permiso, $tipo_duracion, $fecha_desde, $fecha_hasta, $hora_desde, $hora_hasta, $observaciones);
    $stmt->execute();

    echo "Permiso enviado correctamente. <a href='dashboard.php'>Volver</a>";
    exit;
}
?>

<form method="post">
    <label>Tipo de permiso:</label>
    <select name="id_tipo_permiso">
        <?php
        $result = $conn->query("SELECT * FROM tipos_permiso");
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['id_tipo_permiso']}'>{$row['nombre_permiso']}</option>";
        }
        ?>
    </select><br>

    <label>Duración:</label>
    <select name="tipo_duracion">
        <option value="Días">Días</option>
        <option value="Horas">Horas</option>
    </select><br>

    <label>Desde (fecha):</label>
    <input type="date" name="fecha_desde"><br>

    <label>Hasta (fecha):</label>
    <input type="date" name="fecha_hasta"><br>

    <label>Desde (hora):</label>
    <input type="time" name="hora_desde"><br>

    <label>Hasta (hora):</label>
    <input type="time" name="hora_hasta"><br>

    <label>Observaciones:</label>
    <textarea name="observaciones"></textarea><br>

    <button type="submit">Enviar solicitud</button>
</form>

