<?php
include "db.php";

$sqlUsuarios = "SELECT DISTINCT u.id_usuario, u.nombres, u.apellidos, u.cedula
                FROM permisos p
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                ORDER BY u.apellidos ASC";
$resultUsuarios = $conn->query($sqlUsuarios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Reporte Detallado de Permisos - Yavirac</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans&display=swap" rel="stylesheet" />
    <style>
        /* RESET */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f7f9fc;
            color: #333;
            padding: 20px 30px;
        }

        /* HEADER sin logo, texto blanco y centrado */
        header {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #003366; /* Azul oscuro Yavirac */
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        header h1 {
            font-family: 'Montserrat', sans-serif;
            color: #fff; /* Letras blancas */
            font-weight: 700;
            font-size: 2rem;
            user-select: none;
            margin: 0;
        }

        /* BOTÓN VOLVER */
        a.btn-volver {
            display: inline-block;
            background-color: #ff6600;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            box-shadow:
                3px 3px 8px rgba(255,102,0,0.6),
                -3px -3px 8px rgba(255,140,51,0.6);
            transition: background-color 0.3s ease;
            user-select: none;
            margin-bottom: 30px;
        }
        a.btn-volver:hover {
            background-color: #cc5200;
            box-shadow:
                2px 2px 6px rgba(204,82,0,0.7),
                -2px -2px 6px rgba(255,115,51,0.7);
        }

        /* BOTÓN IMPRIMIR GLOBAL */
        .btn-print-global {
            text-align: center;
            margin-bottom: 40px;
        }
        button {
            background-color: #ff6600;
            color: white;
            font-weight: 700;
            border: none;
            border-radius: 50px;
            padding: 14px 45px;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow:
                4px 4px 12px rgba(255,102,0,0.7),
                -4px -4px 12px rgba(255,140,51,0.7);
            transition: background-color 0.3s ease;
            user-select: none;
        }
        button:hover {
            background-color: #cc5200;
            box-shadow:
                2px 2px 8px rgba(204,82,0,0.8),
                -2px -2px 8px rgba(255,115,51,0.8);
        }

        /* SECCIÓN USUARIO */
        section.print-section {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            margin-bottom: 40px;
            padding: 30px 40px;
            transition: box-shadow 0.3s ease;
        }
        section.print-section:hover {
            box-shadow: 0 14px 40px rgba(0,0,0,0.2);
        }
        section.print-section h3 {
            font-family: 'Montserrat', sans-serif;
            color: #003366;
            font-weight: 700;
            font-size: 1.9rem;
            margin-bottom: 20px;
            user-select: none;
        }

        .resumen {
            background: #eaf1fc;
            border-left: 8px solid #ff6600;
            padding: 15px 25px;
            margin-bottom: 25px;
            font-weight: 700;
            font-size: 1.2rem;
            color: #003366;
            border-radius: 12px;
            user-select: none;
            box-shadow: inset 3px 3px 7px rgba(255,102,0,0.2);
        }

        .btn-user-print {
            text-align: right;
            margin-bottom: 15px;
        }
        .btn-user-print button {
            padding: 10px 30px;
            font-size: 1rem;
            border-radius: 40px;
            box-shadow:
                4px 4px 10px rgba(255,102,0,0.5),
                -4px -4px 10px rgba(255,140,51,0.5);
        }
        .btn-user-print button:hover {
            background: #cc5200;
            box-shadow:
                2px 2px 8px rgba(204,82,0,0.7),
                -2px -2px 8px rgba(255,115,51,0.7);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            font-size: 1rem;
        }
        thead tr {
            background: linear-gradient(90deg, #003366 0%, #0055a5 100%);
            color: white;
            user-select: none;
        }
        thead th {
            padding: 14px 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: inset 0 -2px 6px rgba(0,0,0,0.2);
        }
        tbody tr {
            background: #fff;
            transition: background-color 0.25s ease;
        }
        tbody tr:hover {
            background: #fef3c7; /* suave amarillo */
            cursor: default;
        }
        tbody td {
            padding: 14px 20px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            color: #444;
            font-weight: 600;
        }
        tbody td:last-child {
            font-weight: 700;
            color: #003366;
            text-transform: capitalize;
        }

        /* Estado colores */
        tbody td.estado-aprobado {
            color: #27ae60;
            font-weight: 700;
        }
        tbody td.estado-rechazado {
            color: #e74c3c;
            font-weight: 700;
        }
        tbody td.estado-pendiente {
            color: #f39c12;
            font-weight: 700;
        }
        tbody td.estado-anulado {
            color: #7f8c8d;
            font-weight: 700;
        }

        /* Impresión */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 10mm 20mm;
                font-size: 12pt;
            }
            header, a.btn-volver, .btn-print-global, .btn-user-print {
                display: none !important;
            }
            section.print-section {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin-bottom: 40px !important;
                padding: 0 !important;
                page-break-inside: avoid;
            }
            table {
                box-shadow: none !important;
                border-collapse: collapse !important;
                font-size: 11pt;
            }
            thead th {
                background: #444 !important;
                color: white !important;
                position: static !important;
                box-shadow: none !important;
            }
            tbody tr:hover {
                background: transparent !important;
            }
            .resumen {
                border-left: 4px solid #000 !important;
                background: transparent !important;
                color: #000 !important;
                box-shadow: none !important;
                padding: 5px 10px !important;
                font-size: 1rem !important;
            }
        }
    </style>

    <script>
        function printSection(id) {
            const section = document.getElementById(id);
            if (!section) {
                alert("Sección no encontrada");
                return;
            }

            const contenido = section.cloneNode(true);
            const btn = contenido.querySelector('.btn-user-print');
            if (btn) btn.remove();

            const titulo = contenido.querySelector('h3')?.innerText || '';
            const resumen = contenido.querySelector('.resumen')?.innerText || '';
            const tabla = contenido.querySelector('table')?.outerHTML || '';

            const logoURL = 'img/logo_yavira.png';

            const printWindow = window.open('', '', 'width=900,height=700');
            printWindow.document.write(`
                <html lang="es">
                <head>
                    <title>Imprimir Usuario</title>
                    <style>
                        @page { margin: 20mm; }
                        body {
                            font-family: 'Open Sans', sans-serif;
                            margin: 0; padding: 20px;
                            color: #000;
                            background: #fff;
                        }
                        header {
                            display: flex;
                            align-items: center;
                            margin-bottom: 20px;
                            border-bottom: 2px solid #003366;
                            padding-bottom: 10px;
                            justify-content: center;
                        }
                        header img {
                            height: 50px;
                            margin-right: 15px;
                        }
                        header h1 {
                            font-family: 'Montserrat', sans-serif;
                            font-weight: 700;
                            font-size: 1.8rem;
                            color: #003366;
                            margin: 0;
                        }
                        h3 {
                            text-align: center;
                            color: #003366;
                            margin: 30px 0 15px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.04em;
                        }
                        .resumen {
                            background: #eaf1fc;
                            border-left: 8px solid #ff6600;
                            padding: 15px 25px;
                            margin-bottom: 25px;
                            font-weight: 700;
                            font-size: 1.2rem;
                            color: #003366;
                            border-radius: 12px;
                            user-select: none;
                            box-shadow: inset 3px 3px 7px rgba(255,102,0,0.2);
                        }
                        table {
                            width: 100%;
                            border-collapse: separate;
                            border-spacing: 0;
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                            font-size: 1rem;
                        }
                        thead tr {
                            background: linear-gradient(90deg, #003366 0%, #0055a5 100%);
                            color: white;
                            user-select: none;
                        }
                        thead th {
                            padding: 14px 20px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.07em;
                            position: sticky;
                            top: 0;
                            z-index: 10;
                            box-shadow: inset 0 -2px 6px rgba(0,0,0,0.2);
                        }
                        tbody tr {
                            background: #fff;
                            transition: background-color 0.25s ease;
                        }
                        tbody tr:hover {
                            background: #fef3c7;
                        }
                        tbody td {
                            padding: 14px 20px;
                            border-bottom: 1px solid #eee;
                            vertical-align: middle;
                            color: #444;
                            font-weight: 600;
                        }
                        tbody td:last-child {
                            font-weight: 700;
                            color: #003366;
                            text-transform: capitalize;
                        }
                    </style>
                </head>
                <body>
                    <header>
                        <h1>Reporte de Permisos - Usuario</h1>
                    </header>
                    <h3>${titulo}</h3>
                    <div class="resumen">${resumen}</div>
                    ${tabla}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
    </script>
</head>
<body>

<header>
    <h1>Reporte Detallado de Permisos</h1>
</header>

<a href="dashboard.php" class="btn-volver" aria-label="Volver al inicio">← Volver al inicio</a>

<div class="btn-print-global">
    <button onclick="window.print()">🖨️ Imprimir todo el documento</button>
</div>

<?php $contador = 1; ?>
<?php while ($usuario = $resultUsuarios->fetch_assoc()): ?>
    <?php 
        $id_usuario = $usuario['id_usuario']; 

        $sqlPermisos = "SELECT p.*, tp.nombre_permiso 
                        FROM permisos p
                        JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
                        WHERE p.id_usuario = ?
                        ORDER BY p.fecha_solicitud DESC";
        $stmtPermisos = $conn->prepare($sqlPermisos);
        $stmtPermisos->bind_param("i", $id_usuario);
        $stmtPermisos->execute();
        $resPermisos = $stmtPermisos->get_result();

        $sqlResumen = "SELECT 
                        COALESCE(SUM(CASE WHEN tipo_duracion = 'Días' 
                                 THEN DATEDIFF(fecha_hasta, fecha_desde) + 1 
                                 ELSE 0 END),0) AS total_dias,
                        COALESCE(SUM(CASE WHEN tipo_duracion = 'Horas' 
                                             THEN TIME_TO_SEC(TIMEDIFF(hora_hasta, hora_desde)) 
                                             ELSE 0 END),0) AS total_segundos_horas
                      FROM permisos
                      WHERE id_usuario = ? AND estado = 'Aprobado'";
        $stmtResumen = $conn->prepare($sqlResumen);
        $stmtResumen->bind_param("i", $id_usuario);
        $stmtResumen->execute();
        $resumen = $stmtResumen->get_result()->fetch_assoc();

        $totalSegundos = (int)$resumen['total_segundos_horas'];
        $horas = floor($totalSegundos / 3600);
        $minutos = floor(($totalSegundos % 3600) / 60);
        $segundos = $totalSegundos % 60;
        $formatoHoras = sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
    ?>
    <section class="print-section" id="usuario<?= $contador ?>">
        <h3><?= htmlspecialchars($usuario['apellidos'] . ' ' . $usuario['nombres']) ?> (<?= htmlspecialchars($usuario['cedula']) ?>)</h3>

        <div class="resumen">
            Total aprobado: <?= $resumen['total_dias'] ?> día(s),
            <?= $formatoHoras ?> hora(s)
        </div>

        <div class="btn-user-print">
            <button onclick="printSection('usuario<?= $contador ?>')">🖨️ Imprimir este usuario</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo Permiso</th>
                    <th>Duración</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Hora Desde</th>
                    <th>Hora Hasta</th>
                    <th>Observaciones</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($permiso = $resPermisos->fetch_assoc()): ?>
                    <?php
                        $estadoClass = '';
                        switch (strtolower($permiso['estado'])) {
                            case 'aprobado':
                                $estadoClass = 'estado-aprobado';
                                break;
                            case 'rechazado':
                                $estadoClass = 'estado-rechazado';
                                break;
                            case 'pendiente':
                                $estadoClass = 'estado-pendiente';
                                break;
                            case 'anulado':
                                $estadoClass = 'estado-anulado';
                                break;
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($permiso['id_permiso']) ?></td>
                        <td><?= htmlspecialchars($permiso['nombre_permiso']) ?></td>
                        <td><?= htmlspecialchars($permiso['tipo_duracion']) ?></td>
                        <td><?= htmlspecialchars($permiso['fecha_desde']) ?></td>
                        <td><?= htmlspecialchars($permiso['fecha_hasta']) ?></td>
                        <td><?= !empty($permiso['hora_desde']) ? htmlspecialchars($permiso['hora_desde']) : '-' ?></td>
                        <td><?= !empty($permiso['hora_hasta']) ? htmlspecialchars($permiso['hora_hasta']) : '-' ?></td>
                        <td><?= nl2br(htmlspecialchars($permiso['observaciones'])) ?></td>
                        <td class="<?= $estadoClass ?>"><?= htmlspecialchars($permiso['estado']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>
    <?php $contador++; ?>
<?php endwhile; ?>

</body>
</html>
