<?php
session_start();
include "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Debug: PHP started -->";

// Constantes basadas en LOSEP
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_DIR', 'uploads/');
define('HORAS_JORNADA_DIARIA', 8);
define('MAX_DIAS_CALAMIDAD', 3);
define('MAX_DIAS_PERMISO_PERSONAL', 2); // Por mes
define('MIN_HORAS_ANTICIPO', 24); // Horas de anticipación para solicitud
define('HORA_INICIO_LABORAL', '08:00'); // Inicio de jornada laboral
define('HORA_FIN_LABORAL', '17:00'); // Fin de jornada laboral (8 horas + 1 hora almuerzo)

// Redirigir si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || !isset($_SESSION['usuario']['id_usuario'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Su sesión ha expirado o no está completa. Por favor, inicie sesión de nuevo.'];
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_usuario = $usuario['id_usuario'];

if (empty($id_usuario)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Error: ID de usuario no encontrado en la sesión.'];
    header("Location: login.php");
    exit;
}

// Obtener información del usuario
$sql_get_user_info = "SELECT CONCAT(nombres, ' ', apellidos) AS nombres_apellidos, cedula, cargo, direccion_area, regimen_laboral FROM usuarios WHERE id_usuario = ?";
$stmt_user = $conn->prepare($sql_get_user_info);
$stmt_user->bind_param("i", $id_usuario);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows > 0) {
    $user_data_db = $result_user->fetch_assoc();
} else {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Error al obtener los datos del usuario.'];
    header("Location: login.php");
    exit;
}
$stmt_user->close();

// --- CÁLCULO DE SALDO MENSUAL ---
// Tipos de permisos que NO cuentan para el límite de 16 horas
$tipos_no_cuentan_16h = ['Permiso personal', 'Trámites particulares', 'Asuntos familiares', 'Permisos sin justificar', 'Permisos por horas sin respaldo legal'];

$mes_actual = date('m');
$anio_actual = date('Y');

// Sumamos horas de permisos personales aprobados o pendientes (no rechazados) del mes en curso
$sql_saldo = "SELECT SUM(p.horas_solicitadas) as total_usado 
              FROM permisos p
              JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
              WHERE p.id_usuario = ? 
              AND MONTH(p.fecha_solicitud) = ? 
              AND YEAR(p.fecha_solicitud) = ? 
              AND p.estado != 'Rechazado'
              AND tp.nombre_permiso = 'Permiso Personal'";

$stmt_saldo = $conn->prepare($sql_saldo);
$stmt_saldo->bind_param("iii", $id_usuario, $mes_actual, $anio_actual);
$stmt_saldo->execute();
$res_saldo = $stmt_saldo->get_result();
$data_saldo = $res_saldo->fetch_assoc();

$uso_mensual_horas = $data_saldo['total_usado'] ?? 0;
$limite_maximo_horas = MAX_DIAS_PERMISO_PERSONAL * 8; // 16 horas según tus constantes
$horas_disponibles = $limite_maximo_horas - $uso_mensual_horas;
$bloqueo_total = ($horas_disponibles <= 0);

// Función para validar cédula ecuatoriana
function validarCedulaEcuatoriana($cedula) {
    if (strlen($cedula) != 10) return false;
    
    $provincia = substr($cedula, 0, 2);
    if ($provincia < 1 || $provincia > 24) return false;
    
    $tercerDigito = substr($cedula, 2, 1);
    if ($tercerDigito > 6) return false;
    
    $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    $suma = 0;
    
    for ($i = 0; $i < 9; $i++) {
        $valor = substr($cedula, $i, 1) * $coeficientes[$i];
        $suma += ($valor > 9) ? ($valor - 9) : $valor;
    }
    
    $residuo = $suma % 10;
    $resultado = ($residuo == 0) ? 0 : (10 - $residuo);
    $digitoVerificador = substr($cedula, 9, 1);
    
    return $resultado == $digitoVerificador;
}

// Función para contar días laborables (lunes a viernes) - INCLUSIVO
function contarDiasLaborables($fechaInicio, $fechaFin) {
    $inicio = new DateTime($fechaInicio);
    $fin = new DateTime($fechaFin);
    $fin->setTime(0, 0, 0); // Asegurar que incluya el último día
    $count = 0;
    
    while ($inicio <= $fin) {
        $diaSemana = (int)$inicio->format('N'); // 1 = lunes, 7 = domingo
        if ($diaSemana >= 1 && $diaSemana <= 5) { // Lunes a viernes
            $count++;
        }
        $inicio->modify('+1 day');
    }
    
    return $count;
}

// Función para validar tipo de permiso según LOSEP
function validarPermisoLOSEP($tipoPermiso, $diasSolicitados, $horasSolicitadas, $fechaInicio, $requiereDocumento) {
    $errores = [];
    
    switch ($tipoPermiso) {
        case 'Permiso por Calamidad Doméstica':
            if ($diasSolicitados > MAX_DIAS_CALAMIDAD) {
                $errores[] = "Según LOSEP Art. 27, el permiso por calamidad doméstica no puede exceder " . MAX_DIAS_CALAMIDAD . " días.";
            }
            if (!$requiereDocumento) {
                $errores[] = "El permiso por calamidad doméstica requiere documentación de respaldo (Art. 27 LOSEP).";
            }
            break;
            
        case 'Permiso por Maternidad':
            if ($diasSolicitados < 84) {
                $errores[] = "Según LOSEP Art. 27, la licencia por maternidad es de 12 semanas (84 días).";
            }
            if (!$requiereDocumento) {
                $errores[] = "La licencia por maternidad requiere certificado médico (Art. 27 LOSEP).";
            }
            break;
            
        case 'Permiso por Paternidad':
            if ($diasSolicitados > 10) {
                $errores[] = "Según LOSEP Art. 27, la licencia por paternidad es de 10 días.";
            }
            if (!$requiereDocumento) {
                $errores[] = "La licencia por paternidad requiere certificado de nacimiento (Art. 27 LOSEP).";
            }
            break;
            
        case 'Permiso Médico':
            if (!$requiereDocumento) {
                $errores[] = "El permiso médico requiere certificado médico del IESS (Art. 27 LOSEP).";
            }
            break;
            
        case 'Permiso por Enfermedad Catastrófica':
            if (!$requiereDocumento) {
                $errores[] = "Requiere certificado médico del IESS que acredite la enfermedad catastrófica (Art. 27 LOSEP).";
            }
            break;
            
        case 'Permiso para Estudios':
            if ($diasSolicitados > 365) {
                $errores[] = "La comisión de servicios con remuneración para estudios no puede exceder un año (Art. 28 LOSEP).";
            }
            if (!$requiereDocumento) {
                $errores[] = "Requiere certificado de matrícula o aceptación de la institución educativa (Art. 28 LOSEP).";
            }
            break;
    }
    
    return $errores;
}

$pendientes = 0;
if ($usuario['rol'] === 'admin') {
    $sqlPendientes = "SELECT COUNT(*) AS total FROM permisos WHERE estado='Pendiente'";
    $res = $conn->query($sqlPendientes);
    if ($res && $row = $res->fetch_assoc()) {
        $pendientes = $row['total'];
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erroresValidacion = [];
    
    // Recoger datos
    $apellidos_nombres = trim(htmlspecialchars($_POST['apellidos_nombres']));
    $cedula = trim(htmlspecialchars($_POST['cedula']));
    $cargo = trim(htmlspecialchars($_POST['cargo']));
    $direccion_area = trim(htmlspecialchars($_POST['direccion_area']));
    $regimen_laboral = htmlspecialchars($_POST['regimen_laboral']);
    
    // Validar régimen laboral LOSEP
    if ($regimen_laboral !== 'LOSEP') {
        $erroresValidacion[] = "Este sistema está configurado únicamente para personal bajo el régimen LOSEP.";
    }
    
    // Validar cédula
    if (!validarCedulaEcuatoriana($cedula)) {
        $erroresValidacion[] = "La cédula ingresada no es válida.";
    }
    
    $tipo_permiso_nombre = htmlspecialchars($_POST['tipo_permiso']);
    $fecha_desde = htmlspecialchars($_POST['fecha_inicio']);
    $fecha_hasta = !empty($_POST['fecha_fin']) ? htmlspecialchars($_POST['fecha_fin']) : NULL;
    $hora_desde = !empty($_POST['hora_inicio']) ? htmlspecialchars($_POST['hora_inicio']) : NULL;
    $hora_hasta = !empty($_POST['hora_fin']) ? htmlspecialchars($_POST['hora_fin']) : NULL;
    
    // Validar anticipación (LOSEP requiere solicitud previa)
    $fechaActual = new DateTime();
    $fechaSolicitud = new DateTime($fecha_desde);
    $diferencia = $fechaActual->diff($fechaSolicitud);
    $horasDiferencia = ($diferencia->days * 24) + $diferencia->h;
    
    // Excepciones: emergencias médicas y calamidad doméstica
    $excepcionesAnticipacion = ['Permiso Médico', 'Permiso por Calamidad Doméstica', 'Permiso por Enfermedad Catastrófica'];
    
    if (!in_array($tipo_permiso_nombre, $excepcionesAnticipacion) && $horasDiferencia < MIN_HORAS_ANTICIPO) {
        $erroresValidacion[] = "Según normativa institucional, debe solicitar el permiso con al menos 24 horas de anticipación.";
    }
    
    // Validación de fechas
    $fecha_actual = date('Y-m-d');
    if ($fecha_desde < $fecha_actual) {
        $erroresValidacion[] = "La fecha de inicio no puede ser anterior a la fecha actual.";
    }
    
    if ($fecha_hasta && $fecha_hasta < $fecha_desde) {
        $erroresValidacion[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    }
    
    // Validar que no sean fines de semana
    $inicioDateTime = new DateTime($fecha_desde);
    if ($inicioDateTime->format('N') >= 6) {
        $erroresValidacion[] = "No se pueden solicitar permisos que inicien en fin de semana.";
    }
    
    if ($fecha_hasta) {
        $finDateTime = new DateTime($fecha_hasta);
        if ($finDateTime->format('N') >= 6) {
            $erroresValidacion[] = "No se pueden solicitar permisos que terminen en fin de semana.";
        }
    }
    
// ==============================================
// CÁLCULO OFICIAL — NO SE DUPLICA, NO SE REINICIA
// ==============================================

// RAW desde el formulario
$dias_raw  = $_POST['dias_solicitados'] ?? "0";
$horas_raw = $_POST['horas_solicitadas'] ?? "0";

// Valores finales reales
$dias_solicitados  = 0;
$horas_solicitadas = 0;

// ---------- 1) DÍAS -----------
if (is_numeric($dias_raw) && $dias_raw > 0) {
    $dias_solicitados = (float)$dias_raw;
}

// ---------- 2) HORAS -----------
if (!empty($horas_raw)) {

    // Si es un número directo
    if (is_numeric($horas_raw)) {

        $horas_solicitadas = (float)$horas_raw;

    } else {

        // Texto tipo "2 horas 30 minutos"
        $h = 0;
        $m = 0;

        if (preg_match('/(\d+)\s*hora/', $horas_raw, $match1)) {
            $h = (int)$match1[1];
        }
        if (preg_match('/(\d+)\s*minuto/', $horas_raw, $match2)) {
            $m = (int)$match2[1];
        }

        $horas_solicitadas = $h + ($m / 60);
    }
}

// ---------- 3) Regla LOSEP ----------
if ($dias_solicitados > 0) {
    // 1 día = 8 horas
    $horas_solicitadas = $dias_solicitados * 8;
}

// ---------- 4) Observaciones ----------
$observaciones = htmlspecialchars($_POST['justificacion']);

    
    // Validar justificación
    if (strlen($observaciones) < 20) {
        $erroresValidacion[] = "La justificación debe tener al menos 20 caracteres (Art. 27 LOSEP - fundamentación de la solicitud).";
    }
    
    // Determinar tipo_duracion
    $tipo_duracion = '';
    if ($dias_solicitados > 0) {
        $tipo_duracion = 'Días';
    } elseif ($horas_solicitadas > 0) {
        $tipo_duracion = 'Horas';
    } else {
        $erroresValidacion[] = 'Debe especificar la duración del permiso.';
    }
    
    // Obtener id_tipo_permiso
    $sql_get_tipo_permiso_id = "SELECT id_tipo_permiso FROM tipos_permiso WHERE nombre_permiso = ?";
    $stmt_tipo = $conn->prepare($sql_get_tipo_permiso_id);
    $stmt_tipo->bind_param("s", $tipo_permiso_nombre);
    $stmt_tipo->execute();
    $result_tipo = $stmt_tipo->get_result();
    $row_tipo = $result_tipo->fetch_assoc();
    $id_tipo_permiso = $row_tipo['id_tipo_permiso'] ?? null;
    $stmt_tipo->close();
    
    if (!$id_tipo_permiso) {
        $erroresValidacion[] = 'Tipo de permiso seleccionado no válido.';
    }
    
    // Validar archivo PDF
    $archivo_justificativo = NULL;
    $requiereDocumento = false;
    
    if (isset($_FILES['justificacion_pdf']) && $_FILES['justificacion_pdf']['error'] == UPLOAD_ERR_OK) {
        $requiereDocumento = true;
        $file_tmp_path = $_FILES['justificacion_pdf']['tmp_name'];
        $file_name = $_FILES['justificacion_pdf']['name'];
        $file_size = $_FILES['justificacion_pdf']['size'];
        
        // Validar tipo MIME real del archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_tmp_path);
        finfo_close($finfo);
        
        if ($mime !== 'application/pdf') {
            $erroresValidacion[] = 'El archivo debe ser un PDF válido.';
        }
        
        if ($file_size > MAX_FILE_SIZE) {
            $erroresValidacion[] = 'El tamaño del archivo no puede exceder los 5 MB.';
        }
        
        if (empty($erroresValidacion)) {
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = md5(time() . $file_name . $id_usuario) . '.' . $file_extension;
            $dest_path = UPLOAD_DIR . $new_file_name;
            
            if (!move_uploaded_file($file_tmp_path, $dest_path)) {
                $erroresValidacion[] = 'Error al mover el archivo subido.';
            } else {
                $archivo_justificativo = $dest_path;
            }
        }
        
    }
    
    // Validaciones específicas LOSEP
    $erroresLOSEP = validarPermisoLOSEP($tipo_permiso_nombre, $dias_solicitados, $horas_solicitadas, $fecha_desde, $requiereDocumento);
    $erroresValidacion = array_merge($erroresValidacion, $erroresLOSEP);
    
    // Si hay errores, mostrarlos
    if (!empty($erroresValidacion)) {
        $_SESSION['form_errors'] = $erroresValidacion;
        $_SESSION['form_data'] = $_POST; // Preservar datos del formulario
        header("Location: solicitar_permiso.php");
        exit;
    }
    // --- VALIDACIÓN DE LÍMITES ACUMULADOS (AÑADIR ANTES DEL INSERT) ---
if ($tipo_permiso_nombre === 'Permiso Personal') {
    // 1. Obtener el total de horas solicitadas este mes para este usuario
    $mes_actual = date('m');
    $anio_actual = date('Y');
    
    $sql_acumulado = "SELECT SUM(horas_solicitadas) as total_horas 
                      FROM permisos 
                      WHERE id_usuario = ? 
                      AND id_tipo_permiso = ? 
                      AND MONTH(fecha_solicitud) = ? 
                      AND YEAR(fecha_solicitud) = ?
                      AND estado != 'Rechazado'"; // No contamos los que fueron negados

    $stmt_acum = $conn->prepare($sql_acumulado);
    $stmt_acum->bind_param("iiii", $id_usuario, $id_tipo_permiso, $mes_actual, $anio_actual);
    $stmt_acum->execute();
    $res_acum = $stmt_acum->get_result();
    $data_acum = $res_acum->fetch_assoc();
    $horas_ya_usadas = $data_acum['total_horas'] ?? 0;

    // Límite mensual: Supongamos 2 días (16 horas) según tu constante MAX_DIAS_PERMISO_PERSONAL
    $limite_horas_mes = MAX_DIAS_PERMISO_PERSONAL * 8; 

    if (($horas_ya_usadas + $horas_solicitadas) > $limite_horas_mes) {
        $horas_restantes = $limite_horas_mes - $horas_ya_usadas;
        $erroresValidacion[] = "ALERTA DE LEY: Ha excedido el límite mensual de permisos personales. " .
                               "Le quedan " . ($horas_restantes > 0 ? $horas_restantes : 0) . " horas disponibles este mes. " .
                               "Esta solicitud será imputada directamente a vacaciones o debe ser recuperada.";
        
        // Si quieres BLOQUEAR totalmente el envío:
        // $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Límite legal excedido. No puede enviar más solicitudes este mes.'];
        // header("Location: solicitar_permiso.php"); exit;
    }
}

    // Insertar en la base de datos con transacción
    $conn->begin_transaction();
    
    try {
        $sql_insert_permiso = "INSERT INTO permisos (id_usuario, id_tipo_permiso, fecha_solicitud, tipo_duracion, fecha_desde, fecha_hasta, hora_desde, hora_hasta, dias_solicitados, horas_solicitadas, observaciones, archivo_justificativo, estado) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
        
        $stmt_insert = $conn->prepare($sql_insert_permiso);
        $stmt_insert->bind_param("iisssssddss",
            $id_usuario,
            $id_tipo_permiso,
            $tipo_duracion,
            $fecha_desde,
            $fecha_hasta,
            $hora_desde,
            $hora_hasta,
            $dias_solicitados,
            $horas_solicitadas,
            $observaciones,
            $archivo_justificativo
        );
        
        $stmt_insert->execute();
        $stmt_insert->close();
        
        $conn->commit();
        
        $_SESSION['form_message'] = ['type' => 'success', 'text' => 'Solicitud enviada con éxito. Se ha registrado conforme al Art. 27 de la LOSEP.'];
        unset($_SESSION['form_errors']);
        unset($_SESSION['form_data']);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error al insertar permiso: " . $e->getMessage());
        $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Error al guardar la solicitud. Por favor, intente nuevamente.'];
    }
    
    header("Location: mis_solicitudes.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Permiso LOSEP - Sistema de Permisos Yavirac</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
    <style>
        /* Estilos previos mantenidos */
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
        @media (max-width: 650px) {
            header {
                flex-direction: column;
                height: auto;
                padding: 10px 15px;
            }
            .home-link-left {
                width: 100%;
                text-align: center;
                margin-bottom: 10px;
            }
            nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 15px;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 300px;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        .modal-icon {
            font-size: 4rem;
            display: block;
            margin-bottom: 15px;
            animation: bounceIn 0.6s ease-out;
        }

        .modal-content.success .modal-icon {
            color: #28a745;
        }

        .modal-content.error .modal-icon {
            color: #dc3545;
        }

        .modal-content p {
            font-size: 1.2rem;
            color: #333;
            font-weight: 600;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); }
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            width: 800px;
            max-width: 95%;
            margin: 20px auto;
            background-color: white;
            padding: 25px 35px;
            border: 1px solid #999;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }
        
        /* Alerta de errores generales */
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 10pt;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-error ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .alert-error li {
            margin: 5px 0;
        }
        
        /* Mensaje de error debajo de cada campo */
        .field-error {
            color: #dc3545;
            font-size: 8.5pt;
            margin-top: 3px;
            display: block;
            font-weight: 600;
        }
        
        .input-error {
            border-color: #dc3545 !important;
            background-color: #fff5f5 !important;
        }
        
        /* Mensaje de ayuda LOSEP */
        .field-help {
            color: #0066cc;
            font-size: 8pt;
            margin-top: 3px;
            display: block;
            font-style: italic;
        }
        
        .losep-info {
            background-color: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 12px;
            margin: 15px 0;
            font-size: 9pt;
            border-radius: 3px;
        }
        
        .losep-info strong {
            color: #003366;
        }
        
        h1 {
            font-size: 14pt;
            text-align: center;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        th, td {
            border: 1px solid #e0e0e0;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 9pt;
        }

        .section-title {
            background-color: #002060;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #002060;
            font-size: 12pt;
            border-radius: 5px;
        }
        
        .subheader {
            font-weight: bold;
            font-size: 10.5pt;
            margin-top: 20px;
            margin-bottom: 10px;
            padding: 8px 12px;
            background-color: #e9e9e9;
            border: 1px solid #ccc;
            text-transform: uppercase;
            border-radius: 3px;
        }
        
        input[type="text"],
        input[type="date"],
        input[type="time"],
        input[type="file"],
        textarea,
        select {
            width: calc(100% - 20px);
            padding: 8px 10px;
            margin: 2px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 9.5pt;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        textarea:focus,
        select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }
        
        textarea {
            resize: vertical;
            min-height: 70px;
        }
        
        .signature-box {
            border: 1px solid #aaa;
            height: 90px;
            margin-top: 15px;
            padding: 8px;
            text-align: center;
            font-size: 8.5pt;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            border-radius: 4px;
        }
        
        .signature-box p {
            margin: 0;
            line-height: 1.2;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 85%;
            margin: 8px auto 4px;
        }
        
        .signature-text {
            font-weight: bold;
            font-size: 9.5pt;
        }
        
        .instructions {
            font-size: 9pt;
            margin-top: 25px;
            border: 1px solid #000;
            padding: 12px;
            background-color: #fcfcfc;
            line-height: 1.5;
            border-radius: 5px;
        }
        
        .instructions strong {
            font-size: 9.5pt;
        }
        
        .button-container {
            text-align: center;
            margin-top: 30px;
        }
        
        .button-container button {
            padding: 12px 30px;
            font-size: 12pt;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
        }
        
        .button-container button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 123, 255, 0.3);
        }
        
        .button-container button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
        }
        
        .required {
            color: #dc3545;
            font-weight: bold;
        }
        
        .col-25 { width: 25%; }
        .col-50 { width: 50%; }
        .col-75 { width: 75%; }
    </style>
</head>
<body>

<div id="modal-container" class="modal-overlay">
    <div id="success-modal" class="modal-content success" style="display: none;">
        <span class="modal-icon">✔</span>
        <p></p>
    </div>
    <div id="error-modal" class="modal-content error" style="display: none;">
        <span class="modal-icon">✖</span>
        <p></p>
    </div>
</div>

<header>
  <a href="dashboard.php" class="home-link-left" title="Ir a Inicio">🏠 Inicio</a>
  <nav>
    <a href="logout.php">🔒 Cerrar Sesión</a>
  </nav>
</header>
<div class="losep-info" style="<?= $bloqueo_total ? 'border-left-color: #dc3545; background-color: #fff5f5;' : '' ?>">
    <strong>📊 Estado de Permisos Personales (Mes Actual):</strong><br>
    Uso actual: <?= number_format($uso_mensual_horas, 1) ?> horas.<br>
    Disponibles: <?= number_format(max(0, $horas_disponibles), 1) ?> horas.
    
    <?php if ($bloqueo_total): ?>
        <p style="color: #dc3545; font-weight: bold; margin-top: 10px;">
            ⚠️ ATENCIÓN: Ha alcanzado el límite máximo de permisos personales permitidos por mes (Art. 33 LOSEP). 
            No se permiten nuevas solicitudes hasta el próximo mes.
        </p>
    <?php endif; ?>
</div>

<div class="container">
    <div class="section-title">FORMULARIO DE PERMISO / LICENCIA (LOSEP)</div>
    
    <div class="losep-info">
        <strong>📋 Información LOSEP:</strong> Este formulario está regulado por la Ley Orgánica del Servicio Público (LOSEP), 
        específicamente el Art. 27 sobre permisos y licencias. Asegúrese de cumplir con todos los requisitos y adjuntar la documentación de respaldo correspondiente.
    </div>
    
    <?php if (isset($_SESSION['form_errors']) && !empty($_SESSION['form_errors'])): ?>
    <div class="alert alert-error">
        <strong>⚠️ Se encontraron los siguientes errores:</strong>
        <ul>
            <?php foreach ($_SESSION['form_errors'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <?php unset($_SESSION['form_errors']); ?>
    <?php endif; ?>
    

    <form action="" method="post" enctype="multipart/form-data" id="permisoForm">
        <div class="subheader">IDENTIFICACIÓN DEL SERVIDOR</div>
        <table>
            <colgroup>
                <col class="col-25">
                <col class="col-75">
            </colgroup>
            <tr>
                <td><span class="required">*</span> Apellidos y Nombres:</td>
                <td>
                    <input type="text" name="apellidos_nombres" id="apellidos_nombres" 
                           value="<?= htmlspecialchars($user_data_db['nombres_apellidos'] ?? '') ?>" 
                           required readonly>
                    <span class="field-help">Campo no editable - Datos del sistema</span>
                </td>
            </tr>
        </table>

        <table>
            <colgroup>
                <col class="col-25">
                <col class="col-25">
                <col class="col-25">
                <col class="col-25">
            </colgroup>
            <tr>
                <td><span class="required">*</span> Cédula de Identidad:</td>
                <td>
                    <input type="text" name="cedula" id="cedula" 
                           value="<?= htmlspecialchars($user_data_db['cedula'] ?? '') ?>" 
                           pattern="[0-9]{10}" maxlength="10" required>
                    <span class="field-error" id="error-cedula"></span>
                    <span class="field-help">10 dígitos - Se valida automáticamente</span>
                </td>
                <td><span class="required">*</span> Cargo/Puesto:</td>
                <td>
                    <input type="text" name="cargo" id="cargo" 
                           value="<?= htmlspecialchars($user_data_db['cargo'] ?? '') ?>" 
                           minlength="3" required>
                    <span class="field-error" id="error-cargo"></span>
                </td>
            </tr>
            <tr>
                <td><span class="required">*</span> Dirección/Área:</td>
                <td>
                    <input type="text" name="direccion_area" id="direccion_area" 
                           value="<?= htmlspecialchars($user_data_db['direccion_area'] ?? '') ?>" 
                           minlength="3" required>
                    <span class="field-error" id="error-direccion"></span>
                </td>
                <td><span class="required">*</span> Régimen Laboral:</td>
                <td>
                    <select name="regimen_laboral" id="regimen_laboral" required>
                        <option value="">Seleccione</option>
                        <option value="LOSEP" <?= ($user_data_db['regimen_laboral'] ?? '') === 'LOSEP' ? 'selected' : '' ?>>LOSEP</option>
                      
                    </select>
                    <span class="field-error" id="error-regimen"></span>
                    <span class="field-help">Solo personal LOSEP puede usar este sistema</span>
                </td>
            </tr>
        </table>

        <div class="subheader">SOLICITUD DE PERMISO / LICENCIA</div>
        <table>
            <colgroup>
                <col class="col-25">
                <col class="col-75">
            </colgroup>
            <tr>
                <td><span class="required">*</span> Tipo de Permiso / Licencia:</td>
                <td>
                    <select name="tipo_permiso" id="tipo_permiso" required>
                        <option value="">Seleccione</option>
                        <?php
                        $tipos_no_cuentan_16h = ['Permiso personal', 'Trámites particulares', 'Asuntos familiares', 'Permisos sin justificar', 'Permisos por horas sin respaldo legal'];
                        
                        $sql_tipos_permiso = "SELECT id_tipo_permiso, nombre_permiso FROM tipos_permiso ORDER BY nombre_permiso";
                        $result_tipos_permiso = $conn->query($sql_tipos_permiso);
                        
                        echo "<!-- Debug: num_rows = " . $result_tipos_permiso->num_rows . " -->";
                        
                        $tipos_cuentan = [];
                        $tipos_no_cuentan = [];
                        
                        if ($result_tipos_permiso->num_rows > 0) {
                            while($row_tipo = $result_tipos_permiso->fetch_assoc()) {
                                if (in_array(strtolower($row_tipo['nombre_permiso']), array_map('strtolower', $tipos_no_cuentan_16h))) {
                                    $tipos_no_cuentan[] = $row_tipo;
                                } else {
                                    $tipos_cuentan[] = $row_tipo;
                                }
                            }
                        }
                        
                        echo "<!-- Debug: cuentan = " . count($tipos_cuentan) . ", no cuentan = " . count($tipos_no_cuentan) . " -->";
                        
                        // Permisos que cuentan para límite de 16 horas
                        echo '<optgroup label="Permisos que cuentan para límite de 16 horas">';
                        foreach($tipos_cuentan as $row_tipo) {
                            $selected = (isset($_SESSION['form_data']['tipo_permiso']) && $_SESSION['form_data']['tipo_permiso'] == $row_tipo['nombre_permiso']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row_tipo['nombre_permiso']) . "' $selected>" . htmlspecialchars($row_tipo['nombre_permiso']) . "</option>";
                        }
                        echo '</optgroup>';
                        
                        // Permisos que NO cuentan para límite de 16 horas
                        echo '<optgroup label="Permisos que NO cuentan para límite de 16 horas">';
                        foreach($tipos_no_cuentan as $row_tipo) {
                            $selected = (isset($_SESSION['form_data']['tipo_permiso']) && $_SESSION['form_data']['tipo_permiso'] == $row_tipo['nombre_permiso']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($row_tipo['nombre_permiso']) . "' $selected>" . htmlspecialchars($row_tipo['nombre_permiso']) . "</option>";
                        }
                        echo '</optgroup>';
                        ?>
                        <option value="Otro">Otro (Especificar en Motivo)</option>
                    </select>
                    <span class="field-error" id="error-tipo-permiso"></span>
                    <span class="field-help" id="help-tipo-permiso"></span>
                </td>
            </tr>
        </table>

        <table>
            <colgroup>
                <col class="col-25">
                <col class="col-25">
                <col class="col-25">
                <col class="col-25">
            </colgroup>
            <tr>
                <td><span class="required">*</span> Fecha Inicio:</td>
                <td>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" required>
                    <span class="field-error" id="error-fecha-inicio"></span>
                    <span class="field-help">No puede ser fin de semana</span>
                </td>
                <td>Hora Inicio:</td>
                <td>
                    <input type="time" name="hora_inicio" id="hora_inicio" min="08:00" max="17:00" step="900">
                    <span class="field-error" id="error-hora-inicio"></span>
                    <span class="field-help">Horario laboral: 08:00 - 17:00 (intervalos de 15 min)</span>
                </td>
            </tr>
            <tr>
                <td><span class="required">*</span> Fecha Fin:</td>
                <td>
                    <input type="date" name="fecha_fin" id="fecha_fin" required>
                    <span class="field-error" id="error-fecha-fin"></span>
                    <span class="field-help">No puede ser fin de semana</span>
                </td>
                <td>Hora Fin:</td>
                <td>
                    <input type="time" name="hora_fin" id="hora_fin" min="08:00" max="17:00" step="900">
                    <span class="field-error" id="error-hora-fin"></span>
                    <span class="field-help">Horario laboral: 08:00 - 17:00 (intervalos de 15 min)</span>
                </td>
            </tr>
            <tr>
                <td>Total Días Solicitados:</td>
                <td>
                    <input type="text" name="dias_solicitados" id="dias_solicitados" readonly>
                    <span class="field-error" id="error-dias"></span>
                    <span class="field-help">Calculado automáticamente (días laborables)</span>
                </td>
                <td>Total Horas Solicitadas:</td>
                <td>
                    <input type="text" name="horas_solicitadas" id="horas_solicitadas" readonly>
                    <span class="field-error" id="error-horas"></span>
                    <span class="field-help">Calculado automáticamente</span>
                </td>
            </tr>
            <tr>
                <td><span class="required">*</span> Motivo / Justificación:</td>
                <td colspan="3">
                    <textarea name="justificacion" id="justificacion" required minlength="20"></textarea>
                    <span class="field-error" id="error-justificacion"></span>
                    <span class="field-help">Mínimo 20 caracteres - Art. 27 LOSEP requiere fundamentación de la solicitud</span>
                </td>
            </tr>
            
        </table>

        <div class="subheader">INFORMACIÓN DE AUTORIZACIÓN</div>
        <table>
            <colgroup>
                <col class="col-50">
                <col class="col-50">
            </colgroup>
            <tr>
                <td>
                    <div class="subheader" style="background-color: white; border: none; font-size: 9pt; text-transform: none; text-align: center; margin-bottom: 5px;">APROBADO POR JEFATURA INMEDIATA</div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <span class="signature-text">RECTOR</span>
                    </div>
                </td>
                <td>
                    <div class="subheader" style="background-color: white; border: none; font-size: 9pt; text-transform: none; text-align: center; margin-bottom: 5px;">APROBADO POR UNIDAD DE TALENTO HUMANO</div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <span class="signature-text">RHH</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="subheader" style="background-color: white; border: none; font-size: 9pt; text-transform: none; margin-bottom: 5px; padding-left: 0;">Observaciones (llenado por Talento Humano):</div>
                    <textarea name="observaciones_th" rows="3" readonly></textarea>
                    <span class="field-help">Este campo será completado por la Unidad de Talento Humano</span>
                </td>
            </tr>
        </table>

        <div class="instructions">
            <strong>IMPORTANTE - NORMATIVA LOSEP:</strong><br>
            • <strong>Art. 27 LOSEP:</strong> Todo permiso debe estar debidamente justificado y respaldado con documentación.<br>
            • <strong>Plazo de presentación:</strong> El formulario debe presentarse a Talento Humano máximo en los tres días posteriores a su emisión.<br>
            • <strong>Consecuencias:</strong> Formularios presentados fuera de plazo serán nulos y se descontará directamente de vacaciones.<br>
            • <strong>Anticipación:</strong> Se requiere solicitar con 24 horas de anticipación (excepto emergencias médicas y calamidades).<br>
            • <strong>Documentación requerida:</strong> Certificados médicos del IESS, actas de nacimiento, defunción, certificados de estudios, según corresponda.
        </div>

        <div class="button-container">
            <button type="submit" id="btnSubmit">Enviar Solicitud</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalContainer = document.getElementById('modal-container');
        const successModal = document.getElementById('success-modal');
        const errorModal = document.getElementById('error-modal');
        const form = document.getElementById('permisoForm');
        
        // Elementos del formulario
        const cedulaInput = document.getElementById('cedula');
        const cargoInput = document.getElementById('cargo');
        const direccionInput = document.getElementById('direccion_area');
        const regimenInput = document.getElementById('regimen_laboral');
        const tipoPermisoInput = document.getElementById('tipo_permiso');
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        const horaInicioInput = document.getElementById('hora_inicio');
        const horaFinInput = document.getElementById('hora_fin');
        const diasSolicitadosInput = document.getElementById('dias_solicitados');
        const horasSolicitadasInput = document.getElementById('horas_solicitadas');
        const justificacionInput = document.getElementById('justificacion');
        const pdfInput = document.getElementById('justificacion_pdf');
        const pdfRequired = document.getElementById('pdf-required');
        const helpTipoPermiso = document.getElementById('help-tipo-permiso');
        const helpPdf = document.getElementById('help-pdf');
        
        // Constantes de horario laboral
        const HORA_INICIO_LABORAL = '08:00';
        const HORA_FIN_LABORAL = '17:00';
        const HORA_ALMUERZO_INICIO = '13:00';
        const HORA_ALMUERZO_FIN = '14:00';
        
        // Configuración de permisos según LOSEP
        const permisosLOSEP = {
            'Permiso por Calamidad Doméstica': {
                maxDias: 3,
                requierePDF: true,
                ayuda: 'Art. 27 LOSEP: Máximo 3 días. Requiere documentación que acredite la calamidad (certificado de defunción, hospitalización, etc.)',
                anticipacion: false
            },
            'Permiso por Maternidad': {
                minDias: 84,
                maxDias: 84,
                requierePDF: true,
                ayuda: 'Art. 27 LOSEP: 12 semanas (84 días). Requiere certificado médico del IESS',
                anticipacion: false
            },
            'Permiso por Paternidad': {
                maxDias: 10,
                requierePDF: true,
                ayuda: 'Art. 27 LOSEP: 10 días desde el nacimiento. Requiere certificado de nacimiento',
                anticipacion: false
            },
            'Permiso Médico': {
                requierePDF: true,
                ayuda: 'Requiere certificado médico del IESS. No requiere anticipación para emergencias',
                anticipacion: false
            },
            'Permiso por Enfermedad Catastrófica': {
                requierePDF: true,
                ayuda: 'Art. 27 LOSEP: Requiere certificado médico del IESS que acredite la enfermedad catastrófica',
                anticipacion: false
            },
            'Permiso para Estudios': {
                maxDias: 365,
                requierePDF: true,
                ayuda: 'Art. 28 LOSEP: Máximo 1 año. Requiere certificado de matrícula o aceptación de la institución educativa',
                anticipacion: true
            },
            'Permiso Personal': {
                maxDias: 2,
                requierePDF: false,
                ayuda: 'Máximo 2 días por mes según normativa institucional. Requiere 24 horas de anticipación',
                anticipacion: true
            }
        };

        // Función para mostrar modal
        function showModal(type, message) {
            let modalToShow = type === 'success' ? successModal : errorModal;
            const messageElement = modalToShow.querySelector('p');
            if (messageElement) {
                messageElement.textContent = message;
            }
            modalToShow.style.display = 'block';
            modalContainer.classList.add('show');
            setTimeout(() => {
                modalContainer.classList.remove('show');
                setTimeout(() => {
                    modalToShow.style.display = 'none';
                }, 300);
            }, 3000);
        }

        // Función para mostrar error en campo
        function mostrarError(campo, mensaje) {
            const errorSpan = document.getElementById('error-' + campo);
            if (errorSpan) {
                errorSpan.textContent = mensaje;
                const input = document.getElementById(campo) || document.querySelector('[name="' + campo + '"]');
                if (input) input.classList.add('input-error');
            }
        }

        // Función para limpiar error
        function limpiarError(campo) {
            const errorSpan = document.getElementById('error-' + campo);
            if (errorSpan) {
                errorSpan.textContent = '';
                const input = document.getElementById(campo) || document.querySelector('[name="' + campo + '"]');
                if (input) input.classList.remove('input-error');
            }
        }

        // Validar cédula ecuatoriana
        function validarCedula(cedula) {
            if (cedula.length !== 10) return false;
            
            const provincia = parseInt(cedula.substr(0, 2));
            if (provincia < 1 || provincia > 24) return false;
            
            const tercerDigito = parseInt(cedula.substr(2, 1));
            if (tercerDigito > 6) return false;
            
            const coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
            let suma = 0;
            
            for (let i = 0; i < 9; i++) {
                let valor = parseInt(cedula.substr(i, 1)) * coeficientes[i];
                suma += (valor > 9) ? (valor - 9) : valor;
            }
            
            const residuo = suma % 10;
            const resultado = (residuo === 0) ? 0 : (10 - residuo);
            const digitoVerificador = parseInt(cedula.substr(9, 1));
            
            return resultado === digitoVerificador;
        }

        // Validar fecha no sea fin de semana
        function esFinDeSemana(dateString) {
            if (!dateString) return false;
            const date = new Date(dateString + 'T00:00:00');
            const day = date.getDay();
            return day === 0 || day === 6;
        }

        // Contar días laborables (INCLUSIVO - cuenta inicio y fin)
        function getBusinessDays(startDate, endDate) {
            let count = 0;
            const currentDate = new Date(startDate + 'T00:00:00');
            const end = new Date(endDate + 'T00:00:00');
            
            while (currentDate <= end) {
                const dayOfWeek = currentDate.getDay();
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    count++;
                }
                currentDate.setDate(currentDate.getDate() + 1);
            }
            return count;
        }

        // Configurar fecha mínima
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayFormatted = `${yyyy}-${mm}-${dd}`;
        
        fechaInicioInput.min = todayFormatted;
        fechaFinInput.min = todayFormatted;

        // Validación de cédula en tiempo real
        cedulaInput.addEventListener('input', function() {
            const cedula = this.value;
            limpiarError('cedula');
            
            if (cedula.length === 10) {
                if (!validarCedula(cedula)) {
                    mostrarError('cedula', 'Cédula ecuatoriana no válida');
                }
            }
        });

        // Validación de cargo
        cargoInput.addEventListener('blur', function() {
            limpiarError('cargo');
            if (this.value.length < 3) {
                mostrarError('cargo', 'El cargo debe tener al menos 3 caracteres');
            }
        });

        // Validación de dirección
        direccionInput.addEventListener('blur', function() {
            limpiarError('direccion');
            if (this.value.length < 3) {
                mostrarError('direccion', 'La dirección debe tener al menos 3 caracteres');
            }
        });

        // Validación de régimen laboral
        regimenInput.addEventListener('change', function() {
            limpiarError('regimen');
            if (this.value !== 'LOSEP') {
                mostrarError('regimen', 'Solo personal bajo régimen LOSEP puede usar este sistema');
                this.value = 'LOSEP';
            }
        });

        // Cambio de tipo de permiso
        tipoPermisoInput.addEventListener('change', function() {
            const tipoSeleccionado = this.value;
            limpiarError('tipo-permiso');
            
            if (permisosLOSEP[tipoSeleccionado]) {
                const config = permisosLOSEP[tipoSeleccionado];
                helpTipoPermiso.textContent = config.ayuda;
                
                if (config.requierePDF) {
                    pdfRequired.style.display = 'inline';
                    pdfInput.required = true;
                    helpPdf.textContent = 'OBLIGATORIO para este tipo de permiso - ' + config.ayuda;
                } else {
                    pdfRequired.style.display = 'none';
                    pdfInput.required = false;
                    helpPdf.textContent = 'Opcional para este tipo de permiso';
                }
            } else {
                helpTipoPermiso.textContent = '';
                pdfRequired.style.display = 'none';
                pdfInput.required = false;
            }
            
            toggleCampos();
            actualizarFormulario();
        });

        // Validación de justificación
        justificacionInput.addEventListener('input', function() {
            limpiarError('justificacion');
            const longitud = this.value.length;
            
            if (longitud > 0 && longitud < 20) {
                mostrarError('justificacion', `Faltan ${20 - longitud} caracteres (mínimo 20)`);
            }
        });

        

        // Validación de horas laborales
        function validarHoraLaboral(hora, campo) {
            if (!hora) return true; // Si está vacío, está bien
            
            limpiarError(campo);
            
            // Convertir a minutos para facilitar comparación
            const [h, m] = hora.split(':').map(Number);
            const minutosTotales = h * 60 + m;
            
            const [hInicio, mInicio] = HORA_INICIO_LABORAL.split(':').map(Number);
            const minutosInicio = hInicio * 60 + mInicio;
            
            const [hFin, mFin] = HORA_FIN_LABORAL.split(':').map(Number);
            const minutosFin = hFin * 60 + mFin;
            
            if (minutosTotales < minutosInicio || minutosTotales > minutosFin) {
                mostrarError(campo, `Debe estar entre ${HORA_INICIO_LABORAL} y ${HORA_FIN_LABORAL}`);
                return false;
            }
            
            return true;
        }

        // Validar horas en tiempo real
        horaInicioInput.addEventListener('change', function() {
            if (this.value) {
                validarHoraLaboral(this.value, 'hora-inicio');
                actualizarFormulario();
            }
        });

        horaFinInput.addEventListener('change', function() {
            if (this.value) {
                validarHoraLaboral(this.value, 'hora-fin');
                actualizarFormulario();
            }
        });

        // Función para habilitar/deshabilitar campos
        function toggleCampos() {
            const isPermisoMedico = tipoPermisoInput.value === 'Permiso Médico';
            fechaFinInput.disabled = isPermisoMedico;
            horaFinInput.disabled = isPermisoMedico;

            if (isPermisoMedico && fechaInicioInput.value) {
                fechaFinInput.value = fechaInicioInput.value;
            }
        }

        // Función principal de actualización
        function actualizarFormulario() {
            const fechaInicio = fechaInicioInput.value;
            const fechaFin = fechaFinInput.value;
            const horaInicio = horaInicioInput.value;
            const horaFin = horaFinInput.value;
            const tipoPermiso = tipoPermisoInput.value;

            diasSolicitadosInput.value = '';
            horasSolicitadasInput.value = '';
            limpiarError('fecha-inicio');
            limpiarError('fecha-fin');
            limpiarError('hora-inicio');
            limpiarError('hora-fin');
            limpiarError('dias');
            limpiarError('horas');

            // Validar fin de semana en fecha de inicio
            if (fechaInicio && esFinDeSemana(fechaInicio)) {
                mostrarError('fecha-inicio', 'No se puede iniciar en fin de semana');
                fechaInicioInput.value = '';
                return;
            }

            // Validar fin de semana en fecha fin
            if (tipoPermisoInput.value !== 'Permiso Médico' && fechaFin && esFinDeSemana(fechaFin)) {
                mostrarError('fecha-fin', 'No se puede terminar en fin de semana');
                fechaFinInput.value = '';
                return;
            }

            // Caso 1: Mismo día con horas especificadas (permiso por horas)
            if (fechaInicio && fechaFin && fechaInicio === fechaFin && horaInicio && horaFin) {
                // Validar que las horas estén en horario laboral
                if (!validarHoraLaboral(horaInicio, 'hora-inicio') || !validarHoraLaboral(horaFin, 'hora-fin')) {
                    return;
                }
                
                const start = new Date(`2000-01-01T${horaInicio}:00`);
                const end = new Date(`2000-01-01T${horaFin}:00`);
                
                if (end <= start) {
                    mostrarError('hora-fin', 'La hora de fin debe ser posterior a la de inicio');
                    horasSolicitadasInput.value = '0';
                    return;
                }
                
                const diffMs = end - start;
                let diffMinutos = diffMs / (1000 * 60); // Diferencia en minutos
                
                // Restar hora de almuerzo si el permiso cruza el horario de almuerzo
                const [hInicio, mInicio] = horaInicio.split(':').map(Number);
                const [hFin, mFin] = horaFin.split(':').map(Number);
                const minutosInicio = hInicio * 60 + mInicio;
                const minutosFin = hFin * 60 + mFin;
                
                const [hAlmuerzoIni, mAlmuerzoIni] = HORA_ALMUERZO_INICIO.split(':').map(Number);
                const [hAlmuerzoFin, mAlmuerzoFin] = HORA_ALMUERZO_FIN.split(':').map(Number);
                const minutosAlmuerzoIni = hAlmuerzoIni * 60 + mAlmuerzoIni;
                const minutosAlmuerzoFin = hAlmuerzoFin * 60 + mAlmuerzoFin;
                
                // Si el permiso cruza la hora de almuerzo, restar 60 minutos
                if (minutosInicio < minutosAlmuerzoFin && minutosFin > minutosAlmuerzoIni) {
                    diffMinutos -= 60;
                }
                
                // Convertir minutos a formato "X horas Y minutos"
                const horas = Math.floor(diffMinutos / 60);
                const minutos = diffMinutos % 60;
                
                // Validar que no exceda las 8 horas laborales
                if (horas > 8 || (horas === 8 && minutos > 0)) {
                    mostrarError('hora-fin', 'No puede solicitar más de 8 horas en un día (jornada laboral)');
                    horasSolicitadasInput.value = '0';
                    return;
                }
                
                // Mostrar en formato legible
                let textoHoras = '';
                if (horas > 0) {
                    textoHoras += horas + (horas === 1 ? ' hora' : ' horas');
                }
                if (minutos > 0) {
                    if (horas > 0) textoHoras += ' ';
                    textoHoras += minutos + (minutos === 1 ? ' minuto' : ' minutos');
                }
                
                diasSolicitadosInput.value = 0;
                horasSolicitadasInput.value = textoHoras || '0 horas';
                return; // Importante: salir aquí para no ejecutar el código de abajo
            }
            
            // Caso 2: Mismo día sin horas especificadas (se asume día completo)
            if (fechaInicio && fechaFin && fechaInicio === fechaFin && (!horaInicio || !horaFin)) {
                diasSolicitadosInput.value = 1;
                horasSolicitadasInput.value = '8 horas';
                return;
            }
            
            // Caso 3: Múltiples días (permiso por días)
            if (fechaInicio && fechaFin && fechaInicio !== fechaFin) {
                // Múltiples días - deshabilitar campos de hora
                horaInicioInput.disabled = true;
                horaFinInput.disabled = true;
                horaInicioInput.value = '';
                horaFinInput.value = '';

                const startDate = new Date(fechaInicio + 'T00:00:00');
                const endDate = new Date(fechaFin + 'T00:00:00');

                if (endDate < startDate) {
                    mostrarError('fecha-fin', 'La fecha de fin no puede ser anterior a la de inicio');
                    fechaFinInput.value = '';
                    return;
                }
                
                // Contar días laborables (de lunes a viernes, INCLUSIVO)
                const diasLaborables = getBusinessDays(fechaInicio, fechaFin);
                const horasLaborales = diasLaborables * 8;

                diasSolicitadosInput.value = diasLaborables;
                horasSolicitadasInput.value = horasLaborales + (horasLaborales === 1 ? ' hora' : ' horas');
                
                // Validar límites según LOSEP
                if (permisosLOSEP[tipoPermiso]) {
                    const config = permisosLOSEP[tipoPermiso];
                    
                    if (config.maxDias && diasLaborables > config.maxDias) {
                        mostrarError('dias', `Este permiso no puede exceder ${config.maxDias} días según LOSEP`);
                    }
                    
                    if (config.minDias && diasLaborables < config.minDias) {
                        mostrarError('dias', `Este permiso debe ser de al menos ${config.minDias} días según LOSEP`);
                    }
                }
                return;
            }
            
            // Caso 4: Solo fecha de inicio (habilitar campos de hora por si acaso)
            if (fechaInicio && !fechaFin) {
                horaInicioInput.disabled = false;
                horaFinInput.disabled = false;
            }
        }

        // Event listeners
        tipoPermisoInput.addEventListener('change', () => {
            toggleCampos();
            actualizarFormulario();
        });
        
        fechaInicioInput.addEventListener('change', () => {
            toggleCampos();
            actualizarFormulario();
        });
        
        fechaFinInput.addEventListener('change', () => {
            actualizarFormulario();
        });
        
        horaInicioInput.addEventListener('change', () => {
            if (horaInicioInput.value) {
                validarHoraLaboral(horaInicioInput.value, 'hora-inicio');
            }
            actualizarFormulario();
        });
        
        horaFinInput.addEventListener('change', () => {
            if (horaFinInput.value) {
                validarHoraLaboral(horaFinInput.value, 'hora-fin');
            }
            actualizarFormulario();
        });

        // Validación al enviar formulario
        form.addEventListener('submit', function(e) {
            let errores = [];
            
            // Validar cédula
            if (!validarCedula(cedulaInput.value)) {
                errores.push('Cédula no válida');
                mostrarError('cedula', 'Cédula ecuatoriana no válida');
            }
            
            // Validar régimen
            if (regimenInput.value !== 'LOSEP') {
                errores.push('Solo personal LOSEP puede usar este sistema');
                mostrarError('regimen', 'Debe ser régimen LOSEP');
            }
            
            // Validar justificación
            if (justificacionInput.value.length < 20) {
                errores.push('La justificación debe tener al menos 20 caracteres');
                mostrarError('justificacion', 'Mínimo 20 caracteres requeridos');
            }
            
            // Validar PDF si es requerido
            const tipoPermiso = tipoPermisoInput.value;
            if (permisosLOSEP[tipoPermiso] && permisosLOSEP[tipoPermiso].requierePDF) {
                if (!pdfInput.files.length) {
                    errores.push('Este tipo de permiso requiere documentación PDF');
                    mostrarError('pdf', 'Documento obligatorio para este tipo de permiso');
                }
            }
            
            // Validar días/horas
            const dias = parseFloat(diasSolicitadosInput.value) || 0;
            const horasTexto = horasSolicitadasInput.value;
            
            // Extraer horas del texto (puede ser "2 horas 30 minutos" o "40" para días)
            let horasValidas = false;
            
            if (dias > 0 || horasTexto.includes('hora') || horasTexto.includes('minuto')) {
                horasValidas = true;
            } else if (!isNaN(parseFloat(horasTexto)) && parseFloat(horasTexto) > 0) {
                horasValidas = true;
            }
            
            if (!horasValidas) {
                errores.push('Debe especificar la duración del permiso');
                mostrarError('dias', 'Especifique días y horas del permiso');
            }
            
            // Validar horas laborales
            if (horaInicioInput.value && !validarHoraLaboral(horaInicioInput.value, 'hora-inicio')) {
                errores.push('La hora de inicio debe estar en horario laboral (08:00 - 17:00)');
            }
            
            if (horaFinInput.value && !validarHoraLaboral(horaFinInput.value, 'hora-fin')) {
                errores.push('La hora de fin debe estar en horario laboral (08:00 - 17:00)');
            }
            
            // Validar anticipación
            if (permisosLOSEP[tipoPermiso] && permisosLOSEP[tipoPermiso].anticipacion) {
                const fechaActual = new Date();
                const fechaSolicitud = new Date(fechaInicioInput.value);
                const diferenciaDias = Math.floor((fechaSolicitud - fechaActual) / (1000 * 60 * 60 * 24));
                
                if (diferenciaDias < 1) {
                    errores.push('Este tipo de permiso requiere al menos 24 horas de anticipación');
                    mostrarError('fecha-inicio', 'Debe solicitar con 24 horas de anticipación');
                }
            }
            
            if (errores.length > 0) {
                e.preventDefault();
                showModal('error', 'Por favor corrija los errores antes de enviar');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
        });

        // Mostrar modal de éxito/error al cargar la página
        <?php if (isset($_SESSION['form_message'])): ?>
            const messageType = "<?= $_SESSION['form_message']['type'] ?>";
            const messageText = "<?= htmlspecialchars($_SESSION['form_message']['text']) ?>";
            if (messageType === 'success') {
                showModal('success', messageText);
            } else if (messageType === 'error') {
                showModal('error', messageText);
            }
            <?php unset($_SESSION['form_message']); ?>
        <?php endif; ?>
        
        // Limpiar errores al enfocar campos
        const allInputs = form.querySelectorAll('input, select, textarea');
        allInputs.forEach(input => {
            input.addEventListener('focus', function() {
                const fieldName = this.id || this.name;
                if (fieldName) {
                    limpiarError(fieldName);
                }
            });
        });
    });
</script>

</body>
</html>