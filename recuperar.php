<?php
// recovery.php
session_start();
include "db.php"; // tu conexión mysqli en $conn

// --- Helpers del servidor ---
function validar_cedula_ecuador($cedula) {
    // Acepta sólo 10 dígitos
    if (!preg_match('/^\d{10}$/', $cedula)) return false;
    $coef = [2,1,2,1,2,1,2,1,2];
    $sum = 0;
    for ($i=0;$i<9;$i++) {
        $producto = intval($cedula[$i]) * $coef[$i];
        if ($producto >= 10) $producto -= 9;
        $sum += $producto;
    }
    $resto = $sum % 10;
    $dig_ver = ($resto === 0) ? 0 : 10 - $resto;
    return $dig_ver === intval($cedula[9]);
}

function edad_desde_fecha($fecha_str) {
    $hoy = new DateTime('now');
    $nac = DateTime::createFromFormat('Y-m-d', $fecha_str);
    if (!$nac) return null;
    $diff = $hoy->diff($nac);
    return $diff->y;
}

function fecha_valida_yyyy_mm_dd($fecha_str) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha_str);
    return $d && $d->format('Y-m-d') === $fecha_str;
}

// --- CSRF token simple ---
if (!isset($_SESSION['csrf_recovery'])) {
    $_SESSION['csrf_recovery'] = bin2hex(random_bytes(24));
}

$mensaje_exito = "";
$mensaje_error = "";
$cedula_form = "";

// Manejo POST - Paso 1: Verificar identidad (sin nueva_clave)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['nueva_clave'])) {

    // CSRF
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_recovery']) {
        $mensaje_error = "Petición inválida (CSRF).";
    } else {

        $cedula_form = trim($_POST['cedula'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $fecha = trim($_POST['fecha'] ?? '');

        // Validaciones servidor
        if (!validar_cedula_ecuador($cedula_form)) {
            $mensaje_error = "Cédula inválida.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje_error = "Correo inválido.";
        } else {
            // dominio institucional obligatorio
            $dom = strtolower(substr(strrchr($correo, "@"), 1) ?: '');
            if ($dom !== 'yavirac.edu.ec') {
                $mensaje_error = "El correo debe ser del dominio institucional @yavirac.edu.ec.";
            } elseif (!fecha_valida_yyyy_mm_dd($fecha)) {
                $mensaje_error = "Fecha inválida.";
            } else {
                // rango fecha: no antes de 1900-01-01
                $min_fecha = '1900-01-01';
                $hoy = date('Y-m-d');
                $edad = edad_desde_fecha($fecha);
                if ($fecha < $min_fecha || $fecha > $hoy) {
                    $mensaje_error = "Fecha de nacimiento ingresada no coinciden con ningún usuario.";
                } elseif ($edad === null || $edad < 18) {
                    $mensaje_error = "Fecha de nacimiento incorrecta";
                } else {
                    // Buscar usuario
                    $sql = "SELECT id_usuario FROM usuarios WHERE cedula = ? AND correo = ? AND DATE(fecha_nacimiento) = ?";
                    $stmt = $conn->prepare($sql);

                    if (!$stmt) {
                        $mensaje_error = "Error interno. Inténtalo más tarde.";
                    } else {
                        $stmt->bind_param("sss", $cedula_form, $correo, $fecha);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result && $result->num_rows === 1) {
                            $row = $result->fetch_assoc();
                            session_regenerate_id(true);
                            $_SESSION['id_usuario_recuperar'] = $row['id_usuario'];
                            $_SESSION['recovery_token'] = bin2hex(random_bytes(16));
                            $mensaje_exito = "Datos verificados. Ingresa tu nueva contraseña.";
                        } else {
                            $mensaje_error = "Los datos ingresados no coinciden con ningún usuario.";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Manejo POST - Paso 2: Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_clave'])) {

    // CSRF
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_recovery']) {
        $mensaje_error = "Petición inválida (CSRF).";
    } else {

        if (isset($_SESSION['id_usuario_recuperar'])) {

            $id_usuario = intval($_SESSION['id_usuario_recuperar']);
            $nueva = $_POST['nueva_clave'] ?? '';
            $confirmar = $_POST['confirmar_clave'] ?? '';

            $errors = [];
            if (strlen($nueva) < 5) $errors[] = "La contraseña debe tener al menos 5 caracteres.";
            if (!preg_match('/[A-Z]/', $nueva)) $errors[] = "Incluye al menos una letra mayúscula.";
            if (!preg_match('/[a-z]/', $nueva)) $errors[] = "Incluye al menos una letra minúscula.";
            if (!preg_match('/\d/', $nueva)) $errors[] = "Incluye al menos un número.";
            if (!preg_match('/[\W_]/', $nueva)) $errors[] = "Incluye al menos un carácter especial.";
            if ($nueva !== $confirmar) $errors[] = "Las contraseñas no coinciden.";

            if (!empty($errors)) {
                $mensaje_error = implode(' ', $errors);
            } else {

                $nueva_hash = password_hash($nueva, PASSWORD_DEFAULT);

                $sql = "UPDATE usuarios SET clave = ? WHERE id_usuario = ?";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("si", $nueva_hash, $id_usuario);

                    if ($stmt->execute()) {
                        $mensaje_exito = "Contraseña actualizada con éxito. Redirigiendo al login...";
                        unset($_SESSION['id_usuario_recuperar']);
                        unset($_SESSION['recovery_token']);
                        $_SESSION['csrf_recovery'] = bin2hex(random_bytes(24));

                        // Redirección automática después de 2 segundos
                        echo "<script>
                            setTimeout(function(){ window.location.href='login.php'; }, 0,1000);
                        </script>";

                    } else {
                        $mensaje_error = "Error al guardar la nueva contraseña.";
                    }
                    $stmt->close();
                } else {
                    $mensaje_error = "Error interno al actualizar la contraseña.";
                }
            }

        } else {
            $mensaje_error = "Sesión inválida, vuelve a intentarlo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Recuperar Contraseña - Sistema de Permisos (Yavirac)</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />

    <style>
    :root {
        --primary: #003366; 
        --accent: #f15a29; 
        --muted: #6b7280; 
        /* Fondo azul oscuro profundo */
        --bg-dark: #001a33; 
    }

    * { box-sizing: border-box; }

    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        /* Fondo Azul Oscuro Institucional */
        background: radial-gradient(circle at center, #002D62 0%, #001529 100%);
        padding: 24px;
        position: relative;
        overflow-x: hidden;
    }

    /* Marca de agua sutil de YAVIRAC */
    body::before {
        content: "YAVIRAC";
        position: fixed;
        font-size: 15vw;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.03);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        z-index: -1;
        pointer-events: none;
    }

    .box {
        /* Efecto Glassmorphism: blanco semi-transparente */
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        width: 100%;
        max-width: 520px;
        border-radius: 20px;
        padding: 40px;
        /* Sombra suave para dar profundidad sin bordes fuertes */
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .logo { width: 120px; display: block; margin: 0 auto 20px; }

    h2 { 
        text-align: center; 
        color: var(--primary); 
        margin-bottom: 25px; 
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: -0.5px;
    }

    form { display: grid; gap: 15px; }

    /* Etiquetas con el color principal */
    label { 
        font-size: 0.9rem; 
        color: var(--primary); 
        font-weight: 700; 
        margin-left: 5px;
    }

    input {
        width: 100%;
        padding: 14px; 
        border-radius: 12px; 
        border: 1.5px solid #e6e9ef; 
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    input:focus {
        outline: none; 
        border-color: var(--accent); 
        box-shadow: 0 0 0 4px rgba(241, 90, 41, 0.15);
        transform: translateY(-2px);
    }

    .btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(to right, #003366, #00509d);
        color: #fff;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 700;
        font-size: 1.1rem;
        text-transform: uppercase;
        transition: all 0.3s ease;
        margin-top: 10px;
        box-shadow: 0 8px 15px rgba(0, 51, 102, 0.2);
    }

    .btn:hover {
        background: var(--accent);
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(241, 90, 41, 0.3);
    }

    .links a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 700;
        transition: color 0.3s;
    }

    .links a:hover { color: var(--accent); }

    @media (max-width:520px) { .row { flex-direction: column; } }
</style>
</head>
<body>
<div class="box">
    <img src="img/logo_yavira.png" alt="Yavirac" class="logo" />

    <?php if ($mensaje_exito): ?>
        <div class="message success"><?= htmlspecialchars($mensaje_exito) ?></div>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
        <div class="message error"><?= htmlspecialchars($mensaje_error) ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['id_usuario_recuperar'])): ?>
        <h2>Recuperar Contraseña</h2>
        <form id="verifyForm" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_recovery']) ?>">

            <div>
                <label for="cedula">Cédula</label>
                <input id="cedula" name="cedula" type="text" maxlength="10" inputmode="numeric"
                    placeholder="Ej: 0102030405"
                    value="<?= htmlspecialchars($cedula_form) ?>" required>
                <div id="cedulaMsg" class="field-msg"></div>
            </div>

            <div>
                <label for="correo">Correo institucional</label>
                <input id="correo" name="correo" type="email" placeholder="usuario@yavirac.edu.ec" required>
                <div id="correoMsg" class="field-msg"></div>
            </div>

            <div>
                <label for="fecha">Fecha de nacimiento</label>
                <input id="fecha" name="fecha" type="date" required min="1900-01-01" max="<?= date('Y-m-d') ?>">
                <div id="fechaMsg" class="field-msg"></div>
            </div>

            <div>
                <button id="verifyBtn" class="btn" type="submit">Verificar</button>
            </div>
        </form>

    <?php else: ?>

        <h2>Cambiar Contraseña</h2>
        <form id="changeForm" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_recovery']) ?>">

            <div>
                <label for="nueva_clave">Nueva contraseña</label>
                <input id="nueva_clave" name="nueva_clave" type="password"
                    placeholder="Ingrese la nueva contraseña" required>

                <div class="meta">
                    <span id="pwdFeedback" class="note">Estado: </span>
                </div>

                <div class="strength" id="pwdBar"><i></i></div>
                <div id="nuevaMsg" class="field-msg"></div>
            </div>

            <div>
                <label for="confirmar_clave">Confirmar contraseña</label>
                <input id="confirmar_clave" name="confirmar_clave" type="password"
                       placeholder="Reingresa la contraseña" required>
                <div id="confirmMsg" class="field-msg"></div>
            </div>

            <div>
                <button id="changeBtn" class="btn" type="submit">Guardar Contraseña</button>
            </div>
        </form>

    <?php endif; ?>

    <div class="links"><a href="login.php">Volver al inicio de sesión</a></div>
</div>

<script>
function $(id){return document.getElementById(id)}
function setMsg(el, text, ok) {
    el.textContent = text || '';
    el.className = 'field-msg ' + (ok === true ? 'ok' : (ok === false ? 'error' : ''));
}

// ---------- Validar cédula ECU ----------
function validarCedulaEcuador(ced) {
    if (!/^\d{10}$/.test(ced)) return false;
    const coef = [2,1,2,1,2,1,2,1,2];
    let sum = 0;
    for (let i=0;i<9;i++){
        let prod = parseInt(ced[i],10) * coef[i];
        if (prod >= 10) prod -= 9;
        sum += prod;
    }
    const resto = sum % 10;
    const dig = resto === 0 ? 0 : 10 - resto;
    return dig === parseInt(ced[9],10);
}

function calcularEdad(fechaStr) {
    if (!fechaStr) return null;
    const parts = fechaStr.split('-');
    if (parts.length !== 3) return null;
    const [y,m,d] = parts.map(Number);
    const nac = new Date(y,m-1,d);
    if (isNaN(nac)) return null;
    const hoy = new Date();
    let edad = hoy.getFullYear() - nac.getFullYear();
    const mm = hoy.getMonth() - nac.getMonth();
    const dd = hoy.getDate() - nac.getDate();
    if (mm < 0 || (mm === 0 && dd < 0)) edad--;
    return edad;
}

// ---------- Fuerza contraseña ----------
function evaluarContrasena(pw) {
    const res = {score:0, issues: []};
    if (pw.length >= 5) res.score++;
    else res.issues.push('mínimo 5 caracteres');

    if (/[A-Z]/.test(pw)) res.score++; else res.issues.push('una mayúscula');
    if (/[a-z]/.test(pw)) res.score++; else res.issues.push('una minúscula');
    if (/\d/.test(pw)) res.score++; else res.issues.push('un número');
    if (/[\W_]/.test(pw)) res.score++; else res.issues.push('un símbolo');

    let label = 'Muy débil';
    if (res.score <= 1) label = 'Muy débil';
    else if (res.score <= 2) label = 'Débil';
    else if (res.score === 3) label = 'Medio';
    else if (res.score >= 4) label = 'Fuerte';
    res.label = label;
    return res;
}

// ---------- Validación first form ----------
const verifyForm = $('verifyForm');
if (verifyForm) {
    const cedInput = $('cedula');
    const cedMsg = $('cedulaMsg');
    const correoInput = $('correo');
    const correoMsg = $('correoMsg');
    const fechaInput = $('fecha');
    const fechaMsg = $('fechaMsg');

    cedInput.addEventListener('input', () => {
        const val = cedInput.value.replace(/\D/g,'').slice(0,10);
        cedInput.value = val;
        if (val.length === 0) { setMsg(cedMsg, '', null); return; }
        if (!/^\d{10}$/.test(val)) {
            setMsg(cedMsg, 'Cédula inválida', false);
        } else setMsg(cedMsg,'OK', true);
    });
}

// ---------- Validación change password ----------
const changeForm = $('changeForm');
if (changeForm){
    const pwdInput = $('nueva_clave');
    const confInput = $('confirmar_clave');
    const pwdMsg = $('nuevaMsg');
    const confMsg = $('confirmMsg');
    const pwdBar = $('pwdBar');

    function actualizarFuerza() {
        const pw = pwdInput.value;
        const r = evaluarContrasena(pw);
        const i = pwdBar.querySelector('i');
        i.style.width = (r.score*20)+'%';
        switch(r.score){
            case 0: pwdBar.className='strength very-weak'; break;
            case 1: pwdBar.className='strength very-weak'; break;
            case 2: pwdBar.className='strength weak'; break;
            case 3: pwdBar.className='strength medium'; break;
            case 4: case 5: pwdBar.className='strength strong'; break;
        }
        $('pwdFeedback').textContent = 'Estado: '+r.label;
        if (pw.length>0 && r.issues.length>0){
            setMsg(pwdMsg,'Recomendaciones: '+r.issues.join(', '),false);
        } else setMsg(pwdMsg,'',null);
    }
    pwdInput.addEventListener('input', actualizarFuerza);

    confInput.addEventListener('input',()=>{
        if (confInput.value === pwdInput.value) setMsg(confMsg,'Coincide',true);
        else setMsg(confMsg,'No coincide',false);
    });
}
</script>
</body>
</html>
