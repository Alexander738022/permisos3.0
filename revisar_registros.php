<?php
session_start();
include "db.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];

$error = "";
$success = "";

// Solo permitir acceso a administradores
if ($usuario['rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Procesar aprobación o rechazo
if (isset($_GET['accion'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['accion'] === 'aprobar') {
        $sql = "UPDATE usuarios SET estado='activo', rol='admin' WHERE id_usuario=$id AND estado='pendiente'";
        if ($conn->query($sql)) {
            $success = "Usuario aprobado como administrador.";
        } else {
            $error = "Error al aprobar usuario.";
        }
    } elseif ($_GET['accion'] === 'rechazar') {
        $sql = "UPDATE usuarios SET estado='rechazado', rol='usuario' WHERE id_usuario=$id AND estado='pendiente'";
        if ($conn->query($sql)) {
            $success = "Usuario rechazado, será registrado como usuario normal.";
        } else {
            $error = "Error al rechazar usuario.";
        }
    }
}

// Obtener solicitudes pendientes
$sqlSolicitudes = "SELECT id_usuario, cedula, correo, fecha_nacimiento FROM usuarios WHERE estado='pendiente'";
$resSolicitudes = $conn->query($sqlSolicitudes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Solicitudes de Registro Pendientes - Sistema de Permisos Yavirac</title>

<!-- Fuente Poppins -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />

<style>
  * {
    box-sizing: border-box;
    margin: 0; padding: 0;
  }

  body {
    font-family: 'Poppins', sans-serif;
    background-color: #fff;
    color: #003366;
    min-height: 100vh;
    padding: 30px 20px;
    position: relative;
  }

  /* Fondo logo grande y sutil */
  body::before {
    content: "";
    position: fixed;
    top: 50%;
    left: 50%;
    width: 480px;
    height: 480px;
    background: url('img/logo_yavira.png') no-repeat center center;
    background-size: contain;
    opacity: 0.08;
    transform: translate(-50%, -50%);
    pointer-events: none;
    user-select: none;
    z-index: 0;
    filter: grayscale(60%) brightness(130%);
  }

  h2 {
    color: #f15a29;
    margin-bottom: 25px;
    font-weight: 700;
    font-size: 2rem;
    user-select: none;
    position: relative;
    z-index: 1;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
    z-index: 1;
  }

  th, td {
    padding: 12px 15px;
    border: 1px solid #ddd;
    text-align: left;
    font-size: 1rem;
  }

  th {
    background-color: #f15a29;
    color: white;
  }

  tr:nth-child(even) {
    background-color: #f9f9f9;
  }

  a.button {
    padding: 8px 16px;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    margin-right: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    user-select: none;
    display: inline-block;
    transition: background-color 0.3s ease;
  }

  .aprobar {
    background-color: #28a745;
  }

  .aprobar:hover {
    background-color: #218838;
  }

  .rechazar {
    background-color: #dc3545;
  }

  .rechazar:hover {
    background-color: #c82333;
  }

  .volver {
    background-color: #003366;
    margin-top: 15px;
    padding: 10px 24px;
    font-size: 1rem;
    display: inline-block;
  }

  .volver:hover {
    background-color: #002244;
  }

  .message {
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 1rem;
    position: relative;
    z-index: 1;
  }

  .success {
    color: #28a745;
  }

  .error {
    color: #dc3545;
  }

  /* Responsive */
  @media (max-width: 650px) {
    table, thead, tbody, th, td, tr {
      display: block;
    }

    th {
      position: absolute;
      top: -9999px;
      left: -9999px;
    }

    tr {
      margin-bottom: 20px;
      border-bottom: 2px solid #f15a29;
    }

    td {
      border: none;
      position: relative;
      padding-left: 50%;
      font-size: 0.9rem;
    }

    td:before {
      position: absolute;
      top: 12px;
      left: 12px;
      width: 45%;
      padding-right: 10px;
      white-space: nowrap;
      font-weight: 600;
      content: attr(data-label);
      color: #f15a29;
    }
  }
</style>
</head>
<body>

<h2>Solicitudes de Registro Pendientes</h2>

<?php if ($error): ?>
  <div class="message error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="message success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($resSolicitudes && $resSolicitudes->num_rows > 0): ?>
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
      <?php while ($fila = $resSolicitudes->fetch_assoc()): ?>
      <tr>
        <td data-label="Cédula"><?= htmlspecialchars($fila['cedula']) ?></td>
        <td data-label="Correo"><?= htmlspecialchars($fila['correo']) ?></td>
        <td data-label="Fecha de nacimiento"><?= htmlspecialchars($fila['fecha_nacimiento']) ?></td>
        <td data-label="Acciones">
          <a class="button aprobar" href="?accion=aprobar&id=<?= $fila['id_usuario'] ?>" onclick="return confirm('¿Aprobar registro como administrador?')">Aprobar</a>
          <a class="button rechazar" href="?accion=rechazar&id=<?= $fila['id_usuario'] ?>" onclick="return confirm('¿Rechazar registro como administrador?')">Rechazar</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No hay solicitudes pendientes.</p>
<?php endif; ?>

<a href="dashboard.php" class="button volver">← Volver al inicio</a>

</body>
</html>
