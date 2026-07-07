<?php
// registro.php
session_start();
include "db.php"; // DEBE definir $conn (mysqli). Mantén tu configuración habitual.

$message = "";
$error = "";

// Lista blanca de opciones para cargo, area y regimen (basadas en LOSEP)
$allowed_cargos = [
    "Servidor Público de Carrera",
    "Servidor Público de Confianza",
    "Servidor Público de Apoyo",
    "Servidor Público de Servicios",
    "Contratista",
    "Docente",
    "Administrativo"
];

$allowed_areas = [
    "Dirección General",
    "Academia / Docencia",
    "Investigación",
    "Recursos Humanos",
    "Finanzas",
    "Tecnologías de la Información",
    "Bienestar Estudiantil",
    "Administración",
    "Servicios Generales"
];

$allowed_regimen = [
    "Nombramiento",
    "Contrato a Plazo Fijo",
    "Contrato de Servicios Ocasionales",
    "Contrato de Obra",
    "Comisión de Servicio",
    "Régimen Especial"
];

// Funciones de validación servidor (duplicadas con JS)
function validar_cedula_ecuador($cedula) {
    if (!preg_match('/^\d{10}$/', $cedula)) return false;
    $digits = str_split($cedula);
    $prov = intval($digits[0].$digits[1]);
    if ($prov < 1 || $prov > 24) { /* provincias válidas 01-24 */ return false; }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $num = intval($digits[$i]);
        if (($i % 2) == 0) {
            $aux = $num * 2;
            if ($aux > 9) $aux -= 9;
        } else {
            $aux = $num;
        }
        $sum += $aux;
    }
    $ver = intval($digits[9]);
    $res = 10 - ($sum % 10);
    if ($res == 10) $res = 0;
    return $res === $ver;
}

function edad_desde_fecha($fecha) {
    $hoy = new DateTime();
    $nac = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$nac) return -1;
    $diff = $hoy->diff($nac);
    return intval($diff->y);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $clave = isset($_POST['clave']) ? $_POST['clave'] : '';
    $nombre = isset($_POST['nombres']) ? trim($_POST['nombres']) : '';
    $apellidos = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
    $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $telefono = isset($_POST['telefono']) ? preg_replace('/\D+/', '', $_POST['telefono']) : ''; // solo dígitos
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : '';
    $cargo = isset($_POST['cargo']) ? $_POST['cargo'] : '';
    $direccion = isset($_POST['direccion_area']) ? trim($_POST['direccion_area']) : '';
    $regimen = isset($_POST['regimen_laboral']) ? $_POST['regimen_laboral'] : '';
    $rol = 'usuario';
    $estado = 'activo';

    // Validaciones servidor (separar mensajes)
    $errors = [];

    // 1) Cédula: formato y algoritmo ecuatoriano
    if (!validar_cedula_ecuador($cedula)) {
        $errors[] = "Cédula inválida (debe ser una cédula ecuatoriana válida de 10 dígitos).";
    }

    // 2) Correo: dominio institucional exacto
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Correo con formato inválido.";
    } elseif (strtolower(substr($correo, -15)) !== "@yavirac.edu.ec") {
        $errors[] = "Correo debe pertenecer al dominio institucional @yavirac.edu.ec.";
    }

    // 3) Teléfono: exactamente 10 dígitos
    if (!preg_match('/^\d{10}$/', $telefono)) {
        $errors[] = "Teléfono inválido: debe contener exactamente 10 dígitos.";
    }

    // 4) Contraseña: mínimo 5, una mayúscula, número y símbolo (servidor valida requisitos mínimos)
    if (strlen($clave) < 5) {
        $errors[] = "Contraseña demasiado corta (mínimo 5 caracteres).";
    }
    if (!preg_match('/[A-Z]/', $clave)) {
        $errors[] = "Contraseña debe contener al menos una letra mayúscula.";
    }
    if (!preg_match('/\d/', $clave)) {
        $errors[] = "Contraseña debe contener al menos un número.";
    }
    if (!preg_match('/[\W_]/', $clave)) {
        $errors[] = "Contraseña debe contener al menos un símbolo (ej: !@#$%...).";
    }

    // 5) Fecha nacimiento: formato, no futura, no antes de 1900, edad >= 18
    $nac = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
    if (!$nac) {
        $errors[] = "Fecha de nacimiento inválida.";
    } else {
        $minDate = new DateTime('1900-01-01');
        $hoy = new DateTime('now');
        if ($nac < $minDate) $errors[] = "Fecha de nacimiento demasiado antigua (antes de 1900 no permitida).";
        if ($nac > $hoy) $errors[] = "Fecha de nacimiento en el futuro no permitida.";
        $edad = edad_desde_fecha($fecha_nacimiento);
        if ($edad < 18) $errors[] = "Debe ser mayor de 18 años para registrarse (edad actual: $edad).";
        if ($edad === -1) $errors[] = "Fecha de nacimiento no válida.";
    }

    // 6) Cargo, área, régimen: listas permitidas (evitar inyección / valores no esperados)
    if (!in_array($cargo, $allowed_cargos)) $errors[] = "Cargo no válido o no permitido por LOSEP.";
    if (!in_array($direccion, $allowed_areas)) $errors[] = "Área/Dirección no válida.";
    if (!in_array($regimen, $allowed_regimen)) $errors[] = "Régimen laboral no válido.";

    // 7) Nombres y apellidos mínimos
    if (strlen($nombre) < 2) $errors[] = "Nombres demasiado cortos.";
    if (strlen($apellidos) < 2) $errors[] = "Apellidos demasiado cortos.";

    // 8) Verificar cédula única en BD
    if (empty($errors)) {
        $sql_check = "SELECT cedula FROM usuarios WHERE cedula = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("s", $cedula);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check && $result_check->num_rows > 0) {
                $errors[] = "La cédula ingresada ya está registrada.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Error interno (preparación consulta).";
        }
    }

    // Si no hay errores procedemos a insertar
    if (empty($errors)) {
        $clave_hashed = password_hash($clave, PASSWORD_DEFAULT);
        $sql_insert = "INSERT INTO usuarios (cedula, clave, nombres, apellidos, correo, telefono, fecha_nacimiento, cargo, direccion_area, regimen_laboral, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert) {
            $stmt_insert->bind_param("ssssssssssss", $cedula, $clave_hashed, $nombre, $apellidos, $correo, $telefono, $fecha_nacimiento, $cargo, $direccion, $regimen, $rol, $estado);
            if ($stmt_insert->execute()) {
                $message = "¡Registro exitoso! Ya puedes iniciar sesión.";
                // redirigir a login
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "Error al registrar el usuario: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $errors[] = "Error interno (preparación inserción).";
        }
    }

    // Mostrar errores si los hubiese
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registro de Usuario - Sistema de Permisos</title>

    <!-- Tipografía -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />

  <style>
    :root {
        --primary-color: #003366;
        --accent-color: #f15a29;
        --muted: #64748b;
        --bg-dark: #001a33;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        /* Fondo Azul Oscuro Radial unificado */
        background: radial-gradient(circle at center, #002D62 0%, #001529 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        position: relative;
        overflow-x: hidden;
    }

    /* Marca de agua YAVIRAC sutil */
    body::before {
        content: "YAVIRAC";
        position: fixed;
        font-size: 15vw;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.03);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        z-index: 0;
        pointer-events: none;
    }

    .registration-box {
        position: relative;
        z-index: 1;
        background: #ffffff;
        width: 100%;
        max-width: 800px;
        border-radius: 24px;
        /* Sombra profunda para evitar bordes toscos */
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        padding: 40px;
        overflow: auto;
        max-height: 92vh;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .top-row {
        display: flex;
        gap: 20px;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 2px solid #f8fafc;
        padding-bottom: 15px;
    }

    .logo { width: 100px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05)); }

    h2 { 
        color: var(--primary-color); 
        margin: 0; 
        font-size: 1.8rem; 
        font-weight: 800;
        text-transform: uppercase;
    }

    p.lead { color: var(--muted); margin: 5px 0 25px; font-size: 0.95rem; }

    form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    .form-group { display: flex; flex-direction: column; }

    label { 
        font-weight: 700; 
        color: var(--primary-color); 
        margin-bottom: 8px; 
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    input, select {
        padding: 12px 15px;
        border: 2px solid #edf2f7;
        border-radius: 10px;
        font-size: 0.95rem;
        background-color: #f8fafc;
        transition: all 0.3s ease;
    }

    input:focus, select:focus {
        outline: none;
        border-color: var(--accent-color);
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(241, 90, 41, 0.1);
        transform: translateY(-1px);
    }

    .full-width { grid-column: 1 / -1; }

    /* Botón con degradado y efecto hover */
    button {
        grid-column: 1 / -1;
        background: linear-gradient(to right, #003366, #00509d);
        color: #fff;
        border: none;
        padding: 16px;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        margin-top: 15px;
        box-shadow: 0 8px 15px rgba(0, 51, 102, 0.2);
    }

    button:hover {
        background: var(--accent-color);
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(241, 90, 41, 0.3);
    }

    /* Mensajes de error/éxito limpios */
    .error-box, .success-box {
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .error-box { background: #fff1f0; color: #cf1322; border-color: #ffa39e; }
    .success-box { background: #f6ffed; color: #389e0d; border-color: #b7eb8f; }

    @media(max-width:720px){
        form { grid-template-columns: 1fr; }
        .registration-box { padding: 25px; }
    }
</style>
</head>
<body>
    <div class="registration-box">
        <div class="top-row">
            <img src="img/logo_yavira.png" alt="Logo Yavirac" class="logo" />
            <div>
                <h2>Registro de Usuario - Yavirac</h2>
                <p class="lead">Formulario para gestión de permisos docente-administrativo. Validaciones en tiempo real y en servidor.</p>
            </div>
        </div>

        <?php if (!empty($message)) echo "<div class='success-box'>{$message}</div>"; ?>
        <?php if (!empty($error)) echo "<div class='error-box'>{$error}</div>"; ?>

        <form id="registroForm" method="post" action="">
            <!-- Cédula -->
            <div class="form-group">
                <label for="cedula">Cédula</label>
                <input type="text" id="cedula" name="cedula" maxlength="10" autocomplete="off" required />
                <div id="cedulaMsg" class="field-msg"></div>
                <div class="hint small-inline">Ingrese la cédula ecuatoriana (10 dígitos).</div>
            </div>

            <!-- Contraseña -->
            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" required />
                <div id="pwStrength" class="strength-meter"><div id="pwBar"></div></div>
                <div id="claveMsg" class="field-msg"></div>

                <div class="password-requirements">
                    <div class="pw-criteria"><span class="dot" id="pwLen"></span> Mínimo 5 caracteres</div>
                    <div class="pw-criteria"><span class="dot" id="pwUpper"></span> Al menos una mayúscula</div>
                    <div class="pw-criteria"><span class="dot" id="pwNum"></span> Al menos un número</div>
                    <div class="pw-criteria"><span class="dot" id="pwSym"></span> Al menos un símbolo</div>
                </div>
            </div>

            <!-- Nombres -->
            <div class="form-group">
                <label for="nombres">Nombres</label>
                <input type="text" id="nombres" name="nombres" required />
                <div id="nombresMsg" class="field-msg"></div>
            </div>

            <!-- Apellidos -->
            <div class="form-group">
                <label for="apellidos">Apellidos</label>
                <input type="text" id="apellidos" name="apellidos" required />
                <div id="apellidosMsg" class="field-msg"></div>
            </div>

            <!-- Correo -->
            <div class="form-group">
                <label for="correo">Correo institucional</label>
                <input type="email" id="correo" name="correo" required />
                <div id="correoMsg" class="field-msg"></div>
                <div class="hint small-inline">Solo se permite el dominio <strong>@yavirac.edu.ec</strong>.</div>
            </div>

            <!-- Teléfono -->
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="15" required />
                <div id="telefonoMsg" class="field-msg"></div>
                <div class="hint small-inline">Ingrese exactamente 10 dígitos (sin espacios ni guiones).</div>
            </div>

            <!-- Fecha de nacimiento -->
            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required />
                <div id="fechaMsg" class="field-msg"></div>
                <div class="hint small-inline">No se permiten fechas futuras. Edad mínima: 18 años. Fecha mínima aceptada: 1900-01-01.</div>
            </div>

            <!-- Cargo -->
            <div class="form-group">
                <label for="cargo">Cargo</label>
                <select id="cargo" name="cargo" required>
                    <option value="">Seleccione...</option>
                    <?php foreach($allowed_cargos as $c) echo "<option value=\"".htmlspecialchars($c)."\">".htmlspecialchars($c)."</option>"; ?>
                </select>
                <div id="cargoMsg" class="field-msg"></div>
                <div class="hint small-inline">Opciones alineadas a categorías de servicio público (LOSEP). Requerido.</div>
            </div>

            <!-- Dirección / Área -->
            <div class="form-group">
                <label for="direccion_area">Dirección / Área</label>
                <select id="direccion_area" name="direccion_area" required>
                    <option value="">Seleccione...</option>
                    <?php foreach($allowed_areas as $a) echo "<option value=\"".htmlspecialchars($a)."\">".htmlspecialchars($a)."</option>"; ?>
                </select>
                <div id="direccionMsg" class="field-msg"></div>
            </div>

            <!-- Régimen laboral -->
            <div class="form-group">
                <label for="regimen_laboral">Régimen laboral</label>
                <select id="regimen_laboral" name="regimen_laboral" required>
                    <option value="">Seleccione...</option>
                    <?php foreach($allowed_regimen as $r) echo "<option value=\"".htmlspecialchars($r)."\">".htmlspecialchars($r)."</option>"; ?>
                </select>
                <div id="regimenMsg" class="field-msg"></div>
                <div class="hint small-inline">Opciones basadas en tipos de contratación y régimenes del servicio público (LOSEP).</div>
            </div>

            <button type="submit" id="submitBtn">Registrarse</button>
        </form>

        <div style="margin-top:12px; text-align:center;">
            <a href="login.php" style="color:var(--primary-color); text-decoration:none; font-weight:700;">¿Ya tienes una cuenta? Inicia sesión</a>
        </div>
    </div>

    <script>
        // ---------- Utilidades ----------
        const $ = id => document.getElementById(id);

        // Cedula validator (mismo algoritmo que en servidor)
        function validarCedula(ced) {
            if (!/^\d{10}$/.test(ced)) return false;
            const prov = parseInt(ced.substring(0,2), 10);
            if (prov < 1 || prov > 24) return false;
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                let num = parseInt(ced.charAt(i), 10);
                if ((i % 2) === 0) {
                    let aux = num * 2;
                    if (aux > 9) aux -= 9;
                    sum += aux;
                } else {
                    sum += num;
                }
            }
            let ver = parseInt(ced.charAt(9), 10);
            let res = 10 - (sum % 10);
            if (res === 10) res = 0;
            return res === ver;
        }

        // Password strength checker
        function passwordStrength(pw) {
            let score = 0;
            if (pw.length >= 5) score += 1;
            if (/[A-Z]/.test(pw)) score += 1;
            if (/\d/.test(pw)) score += 1;
            if (/[\W_]/.test(pw)) score += 1;
            return score; // 0..4
        }

        // Edad desde fecha (YYYY-MM-DD)
        function calcularEdad(fecha) {
            if (!fecha) return -1;
            let nac = new Date(fecha + "T00:00:00");
            if (isNaN(nac)) return -1;
            let hoy = new Date();
            let edad = hoy.getFullYear() - nac.getFullYear();
            let m = hoy.getMonth() - nac.getMonth();
            if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
            return edad;
        }

        // ---------- Elementos ----------
        const cedulaEl = $('cedula'), cedulaMsg = $('cedulaMsg');
        const claveEl = $('clave'), claveMsg = $('claveMsg'), pwBar = $('pwBar');
        const pwLen = $('pwLen'), pwUpper = $('pwUpper'), pwNum = $('pwNum'), pwSym = $('pwSym');
        const nombresEl = $('nombres'), nombresMsg = $('nombresMsg');
        const apellidosEl = $('apellidos'), apellidosMsg = $('apellidosMsg');
        const correoEl = $('correo'), correoMsg = $('correoMsg');
        const telefonoEl = $('telefono'), telefonoMsg = $('telefonoMsg');
        const fechaEl = $('fecha_nacimiento'), fechaMsg = $('fechaMsg');
        const cargoEl = $('cargo'), cargoMsg = $('cargoMsg');
        const direccionEl = $('direccion_area'), direccionMsg = $('direccionMsg');
        const regimenEl = $('regimen_laboral'), regimenMsg = $('regimenMsg');
        const submitBtn = $('submitBtn');

        // ---------- Validaciones en tiempo real ----------
        cedulaEl.addEventListener('input', () => {
            const v = cedulaEl.value.replace(/\D/g,'');
            cedulaEl.value = v;
            if (v.length === 0) { cedulaMsg.textContent = ''; cedulaMsg.className='field-msg'; return; }
            if (!/^\d{10}$/.test(v)) {
                cedulaMsg.textContent = 'La cédula debe tener 10 dígitos.';
                cedulaMsg.className = 'field-msg error';
            } else if (!validarCedula(v)) {
                cedulaMsg.textContent = 'Cédula inválida según algoritmo ecuatoriano.';
                cedulaMsg.className = 'field-msg error';
            } else {
                cedulaMsg.textContent = 'Cédula válida.';
                cedulaMsg.className = 'field-msg ok';
            }
        });

        claveEl.addEventListener('input', () => {
            const v = claveEl.value;
            const score = passwordStrength(v);
            const percent = (score / 4) * 100;
            pwBar.style.width = percent + '%';
            if (percent < 34) pwBar.style.background = '#d9534f';
            else if (percent < 67) pwBar.style.background = '#f0ad4e';
            else pwBar.style.background = '#28a745';

            // criterios
            pwLen.style.background = (v.length>=5) ? '#28a745' : '#e6e9ee';
            pwUpper.style.background = (/[A-Z]/.test(v)) ? '#28a745' : '#e6e9ee';
            pwNum.style.background = (/\d/.test(v)) ? '#28a745' : '#e6e9ee';
            pwSym.style.background = (/[\W_]/.test(v)) ? '#28a745' : '#e6e9ee';

            // mensaje
            if (v.length === 0) { claveMsg.textContent = ''; claveMsg.className = 'field-msg'; return; }
            if (score <= 1) {
                claveMsg.textContent = 'Muy débil';
                claveMsg.className = 'field-msg error';
            } else if (score === 2) {
                claveMsg.textContent = 'Débil';
                claveMsg.className = 'field-msg error';
            } else if (score === 3) {
                claveMsg.textContent = 'Media';
                claveMsg.className = 'field-msg';
            } else {
                claveMsg.textContent = 'Segura';
                claveMsg.className = 'field-msg ok';
            }
        });

        nombresEl.addEventListener('input', () => {
            const v = nombresEl.value.trim();
            if (v.length === 0) { nombresMsg.textContent = ''; nombresMsg.className = 'field-msg'; return; }
            nombresMsg.textContent = (v.length>=2) ? 'OK' : 'Ingrese al menos 2 caracteres';
            nombresMsg.className = (v.length>=2) ? 'field-msg ok' : 'field-msg error';
        });

        apellidosEl.addEventListener('input', () => {
            const v = apellidosEl.value.trim();
            if (v.length === 0) { apellidosMsg.textContent = ''; apellidosMsg.className = 'field-msg'; return; }
            apellidosMsg.textContent = (v.length>=2) ? 'OK' : 'Ingrese al menos 2 caracteres';
            apellidosMsg.className = (v.length>=2) ? 'field-msg ok' : 'field-msg error';
        });

        correoEl.addEventListener('input', () => {
            const v = correoEl.value.trim();
            if (v.length === 0) { correoMsg.textContent = ''; correoMsg.className='field-msg'; return; }
            // formato básico
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!pattern.test(v)) {
                correoMsg.textContent = 'Formato de correo inválido.';
                correoMsg.className = 'field-msg error';
                return;
            }
            if (v.toLowerCase().endsWith('@yavirac.edu.ec')) {
                correoMsg.textContent = 'Correo institucional válido.';
                correoMsg.className = 'field-msg ok';
            } else {
                correoMsg.textContent = 'Debe usar el dominio institucional @yavirac.edu.ec.';
                correoMsg.className = 'field-msg error';
            }
        });

        telefonoEl.addEventListener('input', () => {
            let v = telefonoEl.value.replace(/\D/g,'');
            telefonoEl.value = v;
            if (v.length === 0) { telefonoMsg.textContent = ''; telefonoMsg.className='field-msg'; return; }
            if (v.length < 10) {
                telefonoMsg.textContent = 'Ingrese 10 dígitos.';
                telefonoMsg.className = 'field-msg error';
            } else if (v.length > 10) {
                telefonoMsg.textContent = 'Máximo 10 dígitos.';
                telefonoMsg.className = 'field-msg error';
            } else {
                telefonoMsg.textContent = 'Teléfono OK.';
                telefonoMsg.className = 'field-msg ok';
            }
        });

        fechaEl.addEventListener('change', () => {
            const v = fechaEl.value;
            if (!v) { fechaMsg.textContent=''; fechaMsg.className='field-msg'; return; }
            const edad = calcularEdad(v);
            if (edad === -1) {
                fechaMsg.textContent = 'Fecha inválida.';
                fechaMsg.className = 'field-msg error';
                return;
            }
            if (edad < 18) {
                fechaMsg.textContent = 'Debe ser mayor de 18 años. Edad actual: ' + edad;
                fechaMsg.className = 'field-msg error';
                return;
            }
            // fecha minima 1900-01-01
            const min = new Date('1900-01-01T00:00:00');
            const nac = new Date(v + 'T00:00:00');
            if (nac < min) {
                fechaMsg.textContent = 'Fecha demasiado antigua (antes de 1900).';
                fechaMsg.className = 'field-msg error';
                return;
            }
            // no futura
            const hoy = new Date();
            if (nac > hoy) {
                fechaMsg.textContent = 'Fecha en el futuro no permitida.';
                fechaMsg.className = 'field-msg error';
                return;
            }
            fechaMsg.textContent = 'Fecha válida (edad: ' + edad + ').';
            fechaMsg.className = 'field-msg ok';
        });

        cargoEl.addEventListener('change', () => {
            cargoMsg.textContent = cargoEl.value ? 'OK' : 'Seleccione un cargo.';
            cargoMsg.className = cargoEl.value ? 'field-msg ok' : 'field-msg error';
        });
        direccionEl.addEventListener('change', () => {
            direccionMsg.textContent = direccionEl.value ? 'OK' : 'Seleccione un área.';
            direccionMsg.className = direccionEl.value ? 'field-msg ok' : 'field-msg error';
        });
        regimenEl.addEventListener('change', () => {
            regimenMsg.textContent = regimenEl.value ? 'OK' : 'Seleccione un régimen.';
            regimenMsg.className = regimenEl.value ? 'field-msg ok' : 'field-msg error';
        });

        // Prevención básica envío si hay errores críticos (cliente)
        document.getElementById('registroForm').addEventListener('submit', function(e) {
            // Forzamos validaciones claves
            let clientErrors = [];

            if (!validarCedula(cedulaEl.value)) clientErrors.push('Cédula inválida.');
            if (!correoEl.value.toLowerCase().endsWith('@yavirac.edu.ec')) clientErrors.push('Correo debe ser @yavirac.edu.ec');
            if (!/^\d{10}$/.test(telefonoEl.value)) clientErrors.push('Teléfono debe tener 10 dígitos');
            if (passwordStrength(claveEl.value) < 3) clientErrors.push('Contraseña débil (revisa requisitos).');
            if (calcularEdad(fechaEl.value) < 18) clientErrors.push('Debe ser mayor de 18 años.');
            if (!cargoEl.value) clientErrors.push('Seleccione un cargo.');
            if (!direccionEl.value) clientErrors.push('Seleccione un área.');
            if (!regimenEl.value) clientErrors.push('Seleccione un régimen.');

            if (clientErrors.length > 0) {
                e.preventDefault();
                alert('Errores en el formulario:\\n- ' + clientErrors.join('\\n- '));
                // Alternativa: desplazar al primer error y mostrar inline (ya mostrados)
                return false;
            }
            // Si todo OK, formulario se envía y servidor volverá a validar
        });
    </script>
</body>
</html>
