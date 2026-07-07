<?php
session_start();
include "db.php";


if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit;
}

$usuario = $_SESSION['usuario'];
$id_user = $usuario['id_usuario']; // Definimos el ID primero que nada

// Tipos de permisos que NO cuentan para el límite de 16 horas
$tipos_no_cuentan_16h = ['Permiso Personal', 'Trámites Particulares', 'Asuntos Familiares', 'Permisos sin Justificar', 'Permisos por Horas sin Respaldo Legal'];

// --- 1. CONFIGURACIÓN DE PAGINACIÓN ---
$registros_por_pagina = 5; 
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $registros_por_pagina;

// Contar total de registros (Uso de query simple para asegurar el dato)
$sql_count = "SELECT COUNT(*) as total FROM permisos p JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso WHERE p.id_usuario = $id_user AND tp.nombre_permiso NOT IN ('" . implode("','", $tipos_no_cuentan_16h) . "')";
$res_count = $conn->query($sql_count);
$total_registros = $res_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// --- 2. LÓGICA DE CÁLCULO Y LÍMITES LOSEP ---
$mes_actual = date('m');
$anio_actual = date('Y');

$sqlLimite = "SELECT SUM(p.horas_solicitadas) as total_mes 
              FROM permisos p
              JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
              WHERE p.id_usuario = ? 
              AND MONTH(p.fecha_solicitud) = ? 
              AND YEAR(p.fecha_solicitud) = ? 
              AND p.estado != 'Rechazado'
              AND tp.nombre_permiso = 'Permiso Personal'";

$stmtLimite = $conn->prepare($sqlLimite);
$stmtLimite->bind_param("iii", $id_user, $mes_actual, $anio_actual);
$stmtLimite->execute();
$resLimite = $stmtLimite->get_result()->fetch_assoc();
$horasUsadasMes = $resLimite['total_mes'] ?? 0;

$limiteMaximo = 16; 
$excedido = ($horasUsadasMes >= $limiteMaximo);

// --- 3. CONSULTA PRINCIPAL (TABLA CON PAGINACIÓN) ---
$sql = "SELECT p.id_permiso, tp.nombre_permiso, p.fecha_solicitud, p.fecha_desde, p.fecha_hasta, p.estado, p.tipo_duracion, p.hora_desde, p.hora_hasta, p.estado_rector, p.estado_rrhh, p.archivo_justificativo
        FROM permisos p
        JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
        WHERE p.id_usuario = ?
        AND tp.nombre_permiso NOT IN ('" . implode("','", $tipos_no_cuentan_16h) . "')
        ORDER BY p.fecha_solicitud DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $id_user, $registros_por_pagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

// --- 4. CÁLCULO DE VACACIONES ---
$totalDiasAprobados = 0;
$totalHorasAprobadas = 0;

$sqlAprobados = "SELECT p.tipo_duracion, p.fecha_desde, p.fecha_hasta, p.hora_desde, p.hora_hasta
                 FROM permisos p
                 JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
                 WHERE p.id_usuario = ? 
                 AND p.estado = 'aprobado'
                 AND tp.nombre_permiso IN ('" . implode("','", $tipos_no_cuentan_16h) . "')";

$stmt2 = $conn->prepare($sqlAprobados);
$stmt2->bind_param("i", $id_user);
$stmt2->execute();
$resultAprobados = $stmt2->get_result();

while ($rowAprobado = $resultAprobados->fetch_assoc()) {
  if ($rowAprobado['tipo_duracion'] === 'Días') {
    $fechaDesde = new DateTime($rowAprobado['fecha_desde']);
    $fechaHasta = new DateTime($rowAprobado['fecha_hasta']);
    $diferencia = $fechaDesde->diff($fechaHasta);
    $totalDiasAprobados += $diferencia->days + 1;
  } elseif ($rowAprobado['tipo_duracion'] === 'Horas') {
    $horaDesde = strtotime($rowAprobado['hora_desde']);
    $horaHasta = strtotime($rowAprobado['hora_hasta']);
    $totalHorasAprobadas += ($horaHasta - $horaDesde) / 3600;
  }
}
$vacacionesConsumidas = $totalDiasAprobados + floor($totalHorasAprobadas / 8);
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mis Solicitudes - Sistema de Permisos</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    header {
    position: sticky;
    top: 0;
    /* Gradiente dinámico de azul */
    background: linear-gradient(to right, #002D5A, #0056B3); 
    height: 80px; /* Un poco más alto para mayor impacto */
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 50px;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25);
    z-index: 1000;
    /* La línea naranja característica del nuevo diseño */
    border-bottom: 4px solid #f15a29; 
}

.home-link-left, nav a {
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    padding: 12px 22px;
    /* Botones redondeados tipo cápsula */
    border-radius: 30px; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    display: flex;
    align-items: center;
    gap: 10px;
    /* Efecto Glassmorphism sutil */
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(5px);
}

.home-link-left:hover,
.home-link-left:focus,
nav a:hover,
nav a:focus {
    background-color: #f15a29; /* Naranja institucional al pasar el mouse */
    color: #fff;
    outline: none;
    /* Efecto de elevación y brillo */
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 10px 20px rgba(241, 90, 41, 0.6);
    border-color: #ff7345;
}

nav {
    display: flex;
    gap: 20px; /* Espaciado entre botones del menú */
}

    .container {
      width: 95%;
      max-width: 1200px;
      margin: 20px auto;
      padding: 25px;
      background-color: white;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f4f7f6;
      margin: 0;
      padding: 0;
      color: #333;
    }

    .container {
      width: 95%;
      max-width: 1300px;
      margin: 20px auto;
      padding: 20px;
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    h1 {
      color: #003366;
      border-bottom: 2px solid #f15a29;
      padding-bottom: 10px;
      margin-bottom: 20px;
      text-align: center;
    }

    .resumen {
      text-align: right;
      margin-bottom: 20px;
      font-weight: bold;
    }

    .resumen p {
      margin: 5px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    th,
    td {
      padding: 12px;
      border: 1px solid #ddd;
      text-align: left;
    }

    th {
      background-color: #003366;
      color: white;
      font-weight: 600;
      text-transform: uppercase;
      position: sticky;
      top: 0;
    }

    tr:nth-child(even) {
      background-color: #f2f2f2;
    }

    tr:hover {
      background-color: #e9e9e9;
    }

    .estado {
      font-weight: bold;
      text-align: center;
      padding: 5px;
      border-radius: 5px;
    }

    .estado.aprobado {
      background-color: #d4edda;
      color: #155724;
    }

    .estado.rechazado {
      background-color: #f8d7da;
      color: #721c24;
    }

    .estado.pendiente,
    .estado.parcialmente-aceptado {
      background-color: #fff3cd;
      color: #856404;
    }

    .firmado {
      color: #28a745;
      font-weight: bold;
    }

    .no-firmado {
      color: #dc3545;
      font-weight: bold;
    }

    .certificado-link {
      display: inline-block;
      background-color: #f15a29;
      color: white;
      padding: 8px 12px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s;
    }

    .certificado-link:hover {
      background-color: #d24e1f;
    }

    .no-certificado {
      color: #6c757d;
      font-style: italic;
    }

    .back-link {
      display: inline-block;
      margin-top: 20px;
      padding: 10px 20px;
      background-color: #003366;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
      transition: background-color 0.3s;
    }

    .back-link:hover {
      background-color: #002244;
    }
    /* Estilos para las alertas */
    .alerta-limite {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 8px;
      border: 1px solid #f5c6cb;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .badge-vacaciones {
      background: #003366;
      color: white;
      padding: 10px 20px;
      border-radius: 50px;
      display: inline-block;
      font-size: 0.9rem;
    }
.paginacion-wrapper {
        margin-top: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    .info-pag {
        font-size: 0.85rem;
        color: #666;
    }
    .pagination {
        display: flex;
        gap: 5px;
    }
    .btn-num, .btn-nav {
        padding: 8px 14px;
        border: 1px solid #003366;
        text-decoration: none;
        color: #003366;
        border-radius: 4px;
        font-weight: bold;
        transition: 0.3s;
        background: white;
    }
    .btn-num.active {
        background-color: #003366;
        color: white;
    }
    .btn-num:hover, .btn-nav:hover {
        background-color: #f15a29;
        color: white;
        border-color: #f15a29;
    }

  </style>
</head>

<header>
  <a href="dashboard.php" class="home-link-left">🏠Inicio</a>
  <nav>
    <a href="logout.php">🔒 Cerrar Sesión</a>
  </nav>
</header>

<body>

<div class="container">
    <h1>Mis Solicitudes de Permiso</h1>

    <?php if ($excedido): ?>
      <div class="alerta-limite">
        <span>⚠️</span>
        <div>
          <strong>¡Límite Mensual Alcanzado!</strong><br>
          Has utilizado <?= number_format($horasUsadasMes, 1) ?> horas de permisos personales este mes. No podrás realizar nuevas solicitudes hasta el próximo mes.
        </div>
      </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div class="badge-vacaciones">
            🏖️ Total días restados de vacaciones: <strong><?= $vacacionesConsumidas ?> días</strong>
        </div>
        <div class="resumen" style="text-align: right; margin-bottom: 0;">
          <p>Días Aprobados: <?= htmlspecialchars($totalDiasAprobados) ?></p>
          <p>Horas Aprobadas: <?= number_format($totalHorasAprobadas, 1) ?>h</p>
        </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Tipo</th>
          <th>Fecha Solicitud</th>
          <th>Fecha Desde</th>
          <th>Fecha Hasta</th>
          <th>Estado (Rector)</th>
          <th>Estado (RHH)</th>
          <th>Estado General</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <?php
            // ... dentro de tu ciclo while o foreach ...
            $estado = strtolower($row['estado']);
            $emoji = '🕒';
            if ($estado === 'aprobado') {
              $emoji = '✅';
            } elseif ($estado === 'rechazado') {
              $emoji = '❌';
            } elseif ($estado === 'parcialmente aceptado') {
              $emoji = '⚠️';
            }
            ?>

            <tr>
              <td><?= htmlspecialchars($row['id_permiso']) ?></td>
              <td><?= htmlspecialchars($row['nombre_permiso']) ?></td>
              <td><?= htmlspecialchars($row['fecha_solicitud']) ?></td>
              <td><?= htmlspecialchars($row['fecha_desde']) ?></td>
              <td><?= htmlspecialchars($row['fecha_hasta'] ?? '-') ?></td>

              <td>
                <?php if ($row['estado_rector'] === 'Aprobado'): ?>
                  <span class="firmado">✅ Aprobado</span>
                <?php elseif ($row['estado_rector'] === 'Rechazado'): ?>
                  <span class="no-firmado">❌ Rechazado</span>
                <?php else: ?>
                  <span>🕒 Pendiente</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($row['estado_rrhh'] === 'Aprobado'): ?>
                  <span class="firmado">✅ Aprobado</span>
                <?php elseif ($row['estado_rrhh'] === 'Rechazado'): ?>
                  <span class="no-firmado">❌ Rechazado</span>
                <?php else: ?>
                  <span>🕒 Pendiente</span>
                <?php endif; ?>
              </td>

              <td class="estado <?= str_replace(' ', '-', $estado) ?>"><?= $emoji ?> <?= ucfirst($estado) ?></td>

              <td>
                <?php
                // Solo permitir descarga si AMBOS aprobaron
                if ($row['estado_rector'] === 'Aprobado' && $row['estado_rrhh'] === 'Aprobado'):
                  // Llamamos al script que genera el PDF con el diseño de doble firma
                  $url_certificado = "CERTIFICADOS/generar_certificado.php?id_permiso=" . $row['id_permiso'];
                ?>
                  <a href="<?= $url_certificado ?>" target="_blank" class="btn-descargar" style="color: #007bff; text-decoration: none; font-weight: bold;">
                    <i class="fas fa-file-pdf"></i> Ver Certificado Final
                  </a>
                <?php else: ?>
                  <span class="no-certificado" style="color: #888; font-style: italic;">
                    <?= ($estado === 'rechazado') ? 'No disponible' : 'En proceso de firma' ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
            </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" style="text-align: center;">No tienes solicitudes de permiso.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table> 
   <div style="margin: 30px 0; text-align: center; font-family: sans-serif;">
    <div style="margin-bottom: 10px; color: #666; font-size: 14px;">
        Página <?= $pagina ?> de <?= max(1, $total_paginas) ?> (Total: <?= $total_registros ?> permisos)
    </div>
    
    <div style="display: flex; justify-content: center; gap: 8px;">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?>" style="padding: 10px 15px; border: 1px solid #003366; text-decoration: none; color: #003366; border-radius: 4px; font-weight: bold;">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?pagina=<?= $i ?>" 
               style="padding: 10px 15px; border: 1px solid #003366; text-decoration: none; border-radius: 4px; font-weight: bold; <?= ($pagina == $i) ? 'background: #003366; color: white;' : 'background: white; color: #003366;' ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?>" style="padding: 10px 15px; border: 1px solid #003366; text-decoration: none; color: #003366; border-radius: 4px; font-weight: bold;">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
</div>

    <a href="dashboard.php" class="back-link">← Volver al Dashboard</a>
  </div>

</body>

</html>