<?php
session_start();
include "db.php";

// --- NUEVO: CONFIGURACIÓN DE NGROK ---
// Reemplaza esta URL por la que te dio la consola de ngrok
$url_ngrok = "https://undaggled-unrecessive-ernesto.ngrok-free.dev/permisos3.0"; 
// ------------------------------------

$error = "";
$success = "";

// Procesar aprobación o rechazo
if (isset($_GET['accion'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['accion'] === 'aprobar') {
        $sql = "UPDATE usuarios SET estado='activo', rol='admin' WHERE id=$id AND estado='pendiente'";
        if ($conn->query($sql)) {
            $success = "Usuario aprobado como administrador.";
        } else {
            $error = "Error al aprobar usuario.";
        }
    } elseif ($_GET['accion'] === 'rechazar') {
        // Aquí puedes decidir si eliminar o marcar como rechazado, yo marco como rechazado y rol usuario
        $sql = "UPDATE usuarios SET estado='rechazado', rol='usuario' WHERE id=$id AND estado='pendiente'";
        if ($conn->query($sql)) {
            $success = "Usuario rechazado, se registrará como usuario normal.";
        } else {
            $error = "Error al rechazar usuario.";
        }
    }
}

// Obtener solicitudes pendientes
$sqlSolicitudes = "SELECT id, cedula, correo, fecha_nacimiento FROM usuarios WHERE estado='pendiente'";
$resSolicitudes = $conn->query($sqlSolicitudes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Solicitudes de Registro Pendientes</title>
<style>
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: #f5f5f5;
  padding: 30px;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}
th, td {
  padding: 12px;
  border: 1px solid #ccc;
  text-align: left;
}
th {
  background: #f15a29;
  color: white;
}
a.button {
  padding: 6px 12px;
  border-radius: 6px;
  color: white;
  text-decoration: none;
  margin-right: 8px;
  font-weight: bold;
}
.aprobar {
  background-color: #28a745;
}
.rechazar {
  background-color: #dc3545;
}
.firmar { background-color: #17a2b8; }
.message {
  margin-bottom: 15px;
  font-weight: bold;
}
.success {
  color: green;
}
.error {
  color: red;
}
</style>
</head>
<body>

<h2>Solicitudes de Registro Pendientes</h2>

<?php if ($error): ?>
  <div class="message error"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="message success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($resSolicitudes->num_rows > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Cédula</th>
        <th>Correo</th>
        <th>Fecha de nacimiento</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
     <?php while ($fila = $resSolicitudes->fetch_assoc()): 
        // --- NUEVO: LÓGICA PARA EL ENLACE DE FIRMA ---
        // Aquí enviamos a generar un PDF con los datos del usuario
        $url_doc = $url_ngrok . "/CERTIFICADOS/generar_certificado_registro.php?id=" . $fila['id'] . "&ext=.pdf";
        $url_retorno = $url_ngrok . "/recibir_firmado.php?id_usuario=" . $fila['id'];
        $link_firmaec = "firmaec://pades/firmar?url=" . urlencode($url_doc) . "&token=" . urlencode($url_retorno);
      ?>
      <tr>
        <td><?php echo htmlspecialchars($fila['cedula']); ?></td>
        <td><?php echo htmlspecialchars($fila['correo']); ?></td>
        <td><?php echo htmlspecialchars($fila['fecha_nacimiento']); ?></td>
        <td>
          <a class="button aprobar" href="?accion=aprobar&id=<?php echo $fila['id']; ?>" onclick="return confirm('¿Aprobar?')">Aprobar</a>
          <a class="button rechazar" href="?accion=rechazar&id=<?php echo $fila['id']; ?>" onclick="return confirm('¿Rechazar?')">Rechazar</a>
          
          <a class="button firmar" href="<?php echo $link_firmaec; ?>">
            ✍️ Firmar Registro
          </a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No hay solicitudes pendientes.</p>
<?php endif; ?>

</body>
</html>