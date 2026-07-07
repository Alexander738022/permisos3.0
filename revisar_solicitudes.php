<?php
session_start();
include "db.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['usuario']['rol'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo "Acceso no autorizado.";
    exit;
}

$usuario = $_SESSION['usuario'];

$cargo_rrhh = 'Jefe de Talento Humano';
$cargo_rector = 'Rector';

// --- NUEVA CONFIGURACIÓN FIRMAEC ---
$url_ngrok = "https://undaggled-unrecessive-ernesto.ngrok-free.dev/permisos3.0";


/* ============================
   FILTROS DE BÚSQUEDA
   ============================ */
$buscar = isset($_GET['buscar']) ? $conn->real_escape_string($_GET['buscar']) : "";


/* ============================
   PAGINACIÓN
   ============================ */
$registros_por_pagina = 5;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $registros_por_pagina;

/* ============================
   CONSTRUCCIÓN DE CONSULTA
   ============================ */

$sql_filtros = " WHERE 1 = 1 ";

if (!empty($buscar)) {
    $sql_filtros .= "
        AND (
            u.nombres LIKE '%$buscar%' OR
            u.apellidos LIKE '%$buscar%' OR
            u.cedula LIKE '%$buscar%'
        )
    ";
}


/* ============================
   CONSULTA PRINCIPAL CON LIMIT
   ============================ */

$sql_permisos = "
    SELECT 
    p.id_permiso,
    p.id_usuario,
    p.id_tipo_permiso,
    p.fecha_solicitud,
    p.fecha_desde,
    p.fecha_hasta,
    p.estado,
    p.estado_rrhh,
    p.estado_rector,
    p.archivo_justificativo,
    p.observaciones,
    u.nombres,
    u.apellidos,
    u.cedula,
    tp.nombre_permiso
FROM permisos p
JOIN usuarios u ON p.id_usuario = u.id_usuario
JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso

    $sql_filtros
    ORDER BY p.fecha_solicitud DESC
    LIMIT $offset, $registros_por_pagina
";

$result_permisos = $conn->query($sql_permisos);

/* ============================
   TOTAL PARA PAGINADOR
   ============================ */

$sql_total = "
    SELECT COUNT(*) AS total
    FROM permisos p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    $sql_filtros
";

$total_result = $conn->query($sql_total);
$total_filas = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total_filas / $registros_por_pagina);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Solicitudes - Sistema de Permisos</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }

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
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #003366;
        }

        .permisos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .permisos-table th,
        .permisos-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }

        .permisos-table th {
            background-color: #003366;
            color: white;
        }

        .permisos-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .permisos-table tr:hover {
            background-color: #f1f1f1;
        }

        .btn-aprobar,
        .btn-rechazar {
            padding: 8px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            color: white;
            font-weight: bold;
            margin-right: 6px;
        }

        .btn-aprobar {
            background-color: #28a745;
        }

        .btn-rechazar {
            background-color: #dc3545;
        }

        .btn-firmar {
            background-color: #17a2b8;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 5px;
        }

        .btn-firmar:hover {
            background-color: #138496;
        }

        .pdf-link {
            color: #007bff;
            font-weight: bold;
            text-decoration: none;
        }

        .pdf-link:hover {
            text-decoration: underline;
        }

        .certificado-link {
            display: inline-block;
            background-color: #f15a29;
            color: white;
            padding: 6px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .certificado-link:hover {
            background-color: #d24e1f;
        }

        .file-upload-form {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .file-upload-form input[type="file"] {
            max-width: 180px;
        }

        .btn-upload {
            background-color: #007bff;
            color: white;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
        }

        /* BOTÓN VOLVER ARRIBA */
        .btn-firmar {
            display: inline-block;
            padding: 12px 20px;
            background-color: #2c7be5;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .btn-firmar:hover {
            background-color: #1a5fc1;
        }

        .small {
            font-size: 0.85rem;
            color: #555;
        }
    </style>
</head>

<body>

    <header>
        <a href="dashboard.php" class="home-link-left">🏠Inicio</a>
        <nav>
            <a href="logout.php">🔒 Cerrar Sesión</a>
        </nav>
    </header>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">

            <h1 style="margin:0; color:#003366;">Revisar Solicitudes de Permiso</h1>

            <form method="GET"
                style="margin-bottom: 20px; display:flex; gap:15px; align-items:center;">

                <input
                    id="campo_buscar"
                    type="text"
                    name="buscar"
                    placeholder="Buscar..."
                    value="<?= htmlspecialchars($buscar) ?>"
                    style="
        padding:6px 8px; 
        width:150px; 
        font-size:14px; 
        border:1px solid #ccc; 
        border-radius:5px;
        transition:0.3s ease;
    "
                    onmouseover="ampliarBuscador()"
                    onmouseout="restaurarBuscador()"
                    oninput="ampliarBuscador()" />

                <button type="submit"
                    style="padding:6px 12px; background:#003366; color:white; border:none; border-radius:5px; font-size:14px;">
                    Buscar
                </button>

                <a href="<?= basename($_SERVER['PHP_SELF']) ?>"
                    style="padding:6px 12px; background:#999; color:white; border-radius:5px; text-decoration:none; font-size:14px;">
                    Limpiar
                </a>

            </form>

            <script>
                const inputBuscar = document.getElementById("campo_buscar");

                function ampliarBuscador() {
                    inputBuscar.style.width = "260px";
                    inputBuscar.style.transform = "scale(1.05)";
                }

                function restaurarBuscador() {
                    // Solo se achica si está VACÍO
                    if (inputBuscar.value.trim() === "") {
                        inputBuscar.style.width = "150px";
                        inputBuscar.style.transform = "scale(1)";
                    }
                }
            </script>

        </div>


        <table class="permisos-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Solicitante</th>
                    <th>CI</th>
                    <th>Tipo de Permiso</th>
                    <th>Fecha Solicitud</th>
                    <th>Periodo</th>
                    <th>Estado RRHH</th>
                    <th>Estado Rector</th>
                   
                    <th>Certificado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if ($result_permisos && $result_permisos->num_rows > 0) {
                    while ($permiso = $result_permisos->fetch_assoc()) {

                        // Determinar si el tipo de permiso requiere justificativo obligatorio.
                        // Ajusta los IDs según tu tabla tipos_permiso.
                        $tipos_requieren_doc = [1, 2, 3]; // ejemplo: 1=Permiso Médico,2=Calamidad,3=Fuerza Mayor
                        $requiere_doc = in_array((int)$permiso['id_tipo_permiso'], $tipos_requieren_doc);

                        // Permite mostrar botones: solo si sesión está activa y cargo coincide con autorización.
                        $show_buttons = false;
                        $user_cargo = strtolower($usuario['cargo']);

                        // RRHH puede aprobar/rechazar aunque falte documento
                        if ($user_cargo === strtolower($cargo_rrhh) && strtolower($permiso['estado_rrhh']) === 'pendiente') {
                            $show_buttons = true; // ahora siempre puede actuar
                        }

                        // Rector puede aprobar/rechazar si RRHH ya aprobó
                        if ($user_cargo === strtolower($cargo_rector) && strtolower($permiso['estado_rector']) === 'pendiente' && strtolower($permiso['estado_rrhh']) === 'aprobado') {
                            $show_buttons = true;
                        }
                ?>
                        <tr>
                            <td><?= htmlspecialchars($permiso['id_permiso']) ?></td>
                            <td><?= htmlspecialchars($permiso['nombres'] . " " . $permiso['apellidos']) ?></td>
                            <td class="small"><?= htmlspecialchars($permiso['cedula']) ?></td>
                            <td><?= htmlspecialchars($permiso['nombre_permiso']) ?></td>
                            <td><?= htmlspecialchars(date("Y-m-d", strtotime($permiso['fecha_solicitud']))) ?></td>
                            <td class="small"><?= htmlspecialchars($permiso['fecha_desde']) ?> <?= !empty($permiso['fecha_hasta']) ? " - " . htmlspecialchars($permiso['fecha_hasta']) : "" ?></td>
                            <td class="small"><?= htmlspecialchars(ucfirst($permiso['estado_rrhh'])) ?></td>
                            <td class="small"><?= htmlspecialchars(ucfirst($permiso['estado_rector'])) ?></td>

                           

                            <td>
                                <a href="certificados/generar_certificado.php?id_permiso=<?= $permiso['id_permiso'] ?>" class="certificado-link" target="_blank">
                                    🖨️ Imprimir
                                </a>
                            </td>

                            <td>
                                <!-- BOTONES DE APROBAR / RECHAZAR -->
                                <?php if ($show_buttons): ?>
                                    <form action="aprobar_rechazar.php" method="post" style="display:inline;">
                                        <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">
                                        <input type="hidden" name="decision" value="aprobar">
                                        <input type="hidden" name="cargo_reviewer" value="<?= $usuario['cargo'] ?>">
                                        <button type="submit" class="btn-aprobar">Aprobar</button>
                                    </form>

                                    <form action="aprobar_rechazar.php" method="post" style="display:inline;">
                                        <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">
                                        <input type="hidden" name="decision" value="rechazar">
                                        <input type="hidden" name="cargo_reviewer" value="<?= $usuario['cargo'] ?>">
                                        <button type="submit" class="btn-rechazar">Rechazar</button>
                                    </form>
                                <?php endif; ?>


                                <!-- FIRMA RRHH -->
                                <?php if (
                                    $permiso['estado_rrhh'] === 'Aprobado' &&
                                    $permiso['estado_rector'] === 'Pendiente' &&
                                    $usuario['cargo'] === 'Jefe de Talento Humano'
                                ): ?>
                                    <a href="CERTIFICADOS/firmar_simulado.php?id_permiso=<?= $permiso['id_permiso'] ?>"
                                        class="btn-firmar">
                                        ✍️ Firmar RRHH
                                    </a>
                                <?php endif; ?>


                                <!-- FIRMA RECTOR -->
                                <?php if (
                                    $permiso['estado_rrhh'] === 'Aprobado' &&
                                    $permiso['estado_rector'] === 'Aprobado' &&
                                    $usuario['cargo'] === 'Rector'
                                ): ?>
                                    <a href="CERTIFICADOS/firmar_simulado.php?id_permiso=<?= $permiso['id_permiso'] ?>"
                                        class="btn-firmar">
                                        ✍️ Firmar Rector
                                    </a>
                                <?php endif; ?>

                            </td>
                        </tr>
                <?php
                    }
                } else {
                    echo "<tr><td colspan='11' style='text-align:center;'>No hay solicitudes para revisar.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top:20px; text-align:center;">

        <?php
        // Mantener el parámetro de búsqueda en la URL
        $parametros = "";
        if (!empty($buscar)) {
            $parametros = "&buscar=" . urlencode($buscar);
        }
        ?>

        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?><?= $parametros ?>"
                style="padding:10px 15px; background:#003366; color:white; text-decoration:none; border-radius:5px;">
                ⬅️ Anterior
            </a>
        <?php endif; ?>

        <span style="margin:0 15px; font-weight:bold;">
            Página <?= $pagina ?> de <?= $total_paginas ?>
        </span>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?><?= $parametros ?>"
                style="padding:10px 15px; background:#003366; color:white; text-decoration:none; border-radius:5px;">
                Siguiente ➡️
            </a>
        <?php endif; ?>

    </div>



    <!-- BOTÓN VOLVER ARRIBA -->
    <button id="btnTop" onclick="scrollToTop()">⬆️</button>

    <script>
        const btnTop = document.getElementById("btnTop");

        window.addEventListener("scroll", () => {
            btnTop.style.display = window.scrollY > 300 ? "block" : "none";
        });

        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        }
    </script>

</body>

</html>