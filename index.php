<?php
session_start();
include "db.php"; // Conexión a BD

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = $_POST['cedula'];
    $clave = $_POST['clave'];

    $sql = "SELECT id_usuario, nombres, apellidos, cedula, clave, cargo, direccion_area, regimen_laboral, firma_digital_url, correo, telefono, fecha_nacimiento, rol, estado
            FROM usuarios
            WHERE cedula = ? AND estado = 'activo'";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $error = "Error interno.";
        error_log("Error SQL: " . $conn->error);
    } else {
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $usuario = $result->fetch_assoc();

            if (password_verify($clave, $usuario['clave'])) {
                $_SESSION['usuario'] = $usuario;
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Cédula o contraseña incorrecta.";
            }
        } else {
            $error = "Cédula o contraseña incorrecta.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Permisos</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
        --primary-color: #002D62;
        --accent-color: #f15a29;
        --text-color: #333;
        /* Azul institucional profundo para el fondo */
        --bg-dark: #001529; 
    }

    * { box-sizing: border-box; }

    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        /* FONDO: Azul profundo radial (mismo que Registro) */
        background: radial-gradient(circle at center, #002D62 0%, var(--bg-dark) 100%);
        background-attachment: fixed;
        overflow: hidden;
        position: relative;
    }

    /* Marca de agua institucional YAVIRAC */
    body::before {
        content: "YAVIRAC";
        position: absolute;
        font-size: 18vw;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.03);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        z-index: 0;
        pointer-events: none;
    }

    .login-box {
        position: relative;
        z-index: 1;
        /* Efecto Glassmorphism suave */
        background: rgba(255, 255, 255, 0.98); 
        padding: 45px;
        border-radius: 24px;
        width: 100%;
        max-width: 420px;
        text-align: center;
        /* Profundidad con sombra suave, sin bordes de colores extraños */
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .logo {
        width: 150px;
        margin-bottom: 20px;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
    }

    h2 {
        color: var(--primary-color);
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 30px;
        text-transform: uppercase;
        letter-spacing: -1px;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Estilo de Inputs limpio */
    input[type="text"],
    input[type="password"] {
        padding: 16px;
        width: 100%;
        border-radius: 12px;
        border: 2px solid #edf2f7;
        background-color: #f8fafc;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    input:focus {
        border-color: var(--accent-color);
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(241, 90, 41, 0.15);
        outline: none;
        transform: translateY(-2px);
    }

    /* Botón con degradado institucional */
    button {
        padding: 16px;
        background: linear-gradient(to right, #002D62, #004b93);
        color: #fff;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 1.1rem;
        font-weight: 700;
        text-transform: uppercase;
        transition: all 0.3s ease;
        margin-top: 10px;
        box-shadow: 0 8px 15px rgba(0, 45, 98, 0.2);
    }

    button:hover {
        background: var(--accent-color); /* Cambia a naranja al pasar el mouse */
        transform: translateY(-3px);
        box-shadow: 0 12px 20px rgba(241, 90, 41, 0.3);
    }

    .login-links {
        margin-top: 30px;
        display: flex;
        justify-content: space-between;
        font-size: 0.95rem;
    }

    .login-links a {
        color: var(--primary-color);
        font-weight: 700;
        text-decoration: none;
        transition: 0.3s;
    }

    .login-links a:hover {
        color: var(--accent-color);
    }
</style>
</head>

<body>

<div class="login-box">
    <img src="img/logo_yavira.png" class="logo">

    <h2>Iniciar Sesión</h2>

    <form method="post">
        <input type="text" name="cedula" placeholder="Cédula" required>

        <input type="password" name="clave" id="clave" placeholder="Contraseña" required>

        <div class="password-msg" id="password-msg"></div>

        <button type="submit">Ingresar</button>

        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    </form>

    <div class="login-links">
        <a href="registro.php">Registrarse</a>
        <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
    </div>
</div>

</body>
</html>
