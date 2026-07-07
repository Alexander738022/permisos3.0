<?php
session_start();
include "db.php"; // Asegúrate de que db.php contiene la conexión $conn

// 1. Verificar si el usuario ha iniciado sesión
// CORRECCIÓN CLAVE AQUÍ: Aseguramos que $_SESSION['usuario'] sea un array y que contenga 'cedula'
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || !isset($_SESSION['usuario']['cedula'])) {
    session_destroy(); // Asegurar limpieza de sesión incompleta o incorrecta
    header("Location: login.php");
    exit;
}

// Los datos completos del usuario deben estar ahora en $_SESSION['usuario'] gracias a login.php
$usuario = $_SESSION['usuario']; 
$pendientes = 0;

// Contar solicitudes pendientes para cualquier administrador
if (isset($usuario['rol']) && $usuario['rol'] === 'admin') {
    $sqlPendientes = "SELECT COUNT(*) AS total FROM permisos WHERE estado='Pendiente'";
    $res = $conn->query($sqlPendientes);
    if ($res && $row = $res->fetch_assoc()) {
        $pendientes = $row['total'];
    }
}

// Cierre de la conexión a la base de datos
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inicio - Sistema de Permisos Yavirac</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />

  <style>
    :root {
        --primary-deep: #002D5A; /* Azul Oscuro Base */
        --primary-medium: #004080; /* Azul Intermedio */
        --primary-light: #0056B3; /* Azul Claro */
        --accent-orange: #f15a29; /* Naranja Principal */
        --accent-orange-hover: #ff7345; /* Naranja al Hover */
        --text-dark: #2c3e50; /* Texto Oscuro */
        --text-medium: #556677; /* Texto Secundario */
        --bg-main: #eef3f7; /* Fondo principal claro */
        --shadow-light: rgba(0, 0, 0, 0.1);
        --shadow-medium: rgba(0, 0, 0, 0.25);
        --shadow-heavy: rgba(0, 0, 0, 0.4);
    }

    * {
        box-sizing: border-box;
        margin: 0; padding: 0;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, var(--bg-main) 0%, #dce4eb 100%); /* Gradiente de fondo más dinámico */
        color: var(--text-dark);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
        position: relative;
    }

    /* Fondo logo grande y sutil con más presencia y color */
    body::before {
        content: "";
        position: fixed;
        top: 55%;
        left: 50%;
        width: 600px; /* Tamaño un poco más grande */
        height: 600px;
        background: url('img/logo_yavira.png') no-repeat center center;
        background-size: contain;
        opacity: 0.12; /* Más visible */
        filter: grayscale(10%) brightness(110%); /* Menos gris, un poco más brillante */
        transform: translate(-50%, -50%) rotate(-7deg); /* Rotación sutil */
        pointer-events: none;
        user-select: none;
        z-index: -1;
    }

    header {
        position: sticky;
        top: 0;
        background: linear-gradient(to right, var(--primary-deep), var(--primary-light)); /* Gradiente azul más vivo */
        height: 80px; /* Un poco más alto */
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 50px;
        box-shadow: 0 8px 18px var(--shadow-medium); /* Sombra más profunda */
        z-index: 1000;
        border-bottom: 4px solid var(--accent-orange); /* Borde inferior naranja más grueso */
    }

    .header-left, .header-right {
        display: flex;
        align-items: center;
        gap: 25px; /* Más espacio entre elementos */
    }

    /* Estilo general para enlaces y botones del header */
    nav a, .dropbtn, .header-right a {
        color: var(--white);
        font-weight: 600;
        font-size: 1rem;
        text-decoration: none;
        padding: 12px 22px; /* Más padding */
        border-radius: 30px; /* Botones más redondeados (estilo "pill") */
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        display: flex;
        align-items: center;
        gap: 10px; /* Más espacio entre texto e icono */
        background: rgba(255, 255, 255, 0.15); /* Fondo semitransparente con efecto cristal */
        border: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(5px); /* Desenfoque sutil */
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    /* Hover y focus con efectos más dinámicos */
    nav a:hover, .dropbtn:hover, .header-right a:hover {
        background-color: var(--accent-orange);
        color: var(--white);
        transform: translateY(-4px) scale(1.02); /* Efecto de elevación y ligero crecimiento */
        box-shadow: 0 10px 20px rgba(241, 90, 41, 0.6); /* Sombra naranja intensa */
        border-color: var(--accent-orange-hover);
    }

    /* Iconos dentro de los botones */
    .material-icons {
        font-size: 1.2rem;
        margin-right: 5px; /* Espacio entre icono y texto */
    }

    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--white); /* Fondo blanco para los ítems */
        min-width: 200px;
        box-shadow: 0 15px 30px var(--shadow-medium);
        z-index: 1001; /* Asegura que esté por encima de todo */
        border-radius: 12px;
        overflow: hidden;
        top: calc(100% + 15px); /* Más espacio entre botón y dropdown */
        left: 0;
        padding: 8px 0;
        border: 1px solid #e0e0e0;
        animation: slideInFromTop 0.3s ease-out forwards;
        transform-origin: top;
    }

    @keyframes slideInFromTop {
        from { opacity: 0; transform: translateY(-10px) scaleY(0.8); }
        to { opacity: 1; transform: translateY(0) scaleY(1); }
    }

    .show {
        display: block !important;
    }

    .dropdown-content a {
        color: var(--text-dark);
        padding: 14px 20px;
        text-decoration: none;
        display: flex; /* Para alinear iconos si los hubiera */
        align-items: center;
        font-weight: 500;
        font-size: 0.95rem;
        transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        border-radius: 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .dropdown-content a:last-child {
        border-bottom: none;
    }

    .dropdown-content a:hover {
        background-color: var(--bg-main); /* Fondo más claro al hover */
        color: var(--accent-orange);
        transform: translateX(5px); /* Deslizamiento al hover */
        box-shadow: none;
    }

    main {
        flex: 1;
        max-width: 1050px; /* Un poco más ancho */
        width: 90%;
        margin: 70px auto; /* Ajuste de margen superior */
        padding: 50px; /* Más padding interno */
        background: var(--white);
        box-shadow: 0 25px 60px var(--shadow-medium); /* Sombra más pronunciada */
        border-radius: 28px; /* Más redondeado */
        position: relative;
        overflow: hidden; /* Para contener elementos con animación */
        border: 1px solid #e0e0e0;
    }

    /* Animación del fondo del main */
    main::before {
        content: "";
        position: absolute;
        top: -100px;
        right: -100px;
        width: 300px;
        height: 300px;
        background: var(--accent-orange);
        border-radius: 50%;
        opacity: 0.05;
        filter: blur(50px);
        z-index: 0;
    }
    main::after {
        content: "";
        position: absolute;
        bottom: -100px;
        left: -100px;
        width: 250px;
        height: 250px;
        background: var(--primary-light);
        border-radius: 50%;
        opacity: 0.05;
        filter: blur(50px);
        z-index: 0;
    }

    main h1 {
    font-weight: 800;
    font-size: 3rem;
    background: linear-gradient(45deg, var(--accent-orange), #e04a1f);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    
    /* PROPIEDADES DE CENTRADO */
    text-align: center; 
    margin-left: auto;
    margin-right: auto;
    display: block; /* Asegura que ocupe todo el ancho para centrar el texto */

    margin-bottom: 15px;
    text-shadow: 2px 2px 5px rgba(241, 90, 41, 0.2);
    letter-spacing: -1.5px;
}

    main p.subtitle {
        font-size: 1.15rem;
        color: var(--text-medium);
        font-weight: 500;
        line-height: 1.6;
        margin-bottom: 45px;
    }

    /* Sección de Perfil como tarjetas dinámicas */
    .profile-section {
    /* Fondo semi-transparente para ver el logo de YAVIRAC */
    background: rgba(255, 255, 255, 0.2); 
    backdrop-filter: blur(10px); /* Efecto de desenfoque de vidrio */
    -webkit-backdrop-filter: blur(10px);
    
    border-radius: 24px;
    padding: 35px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px 0 rgba(0, 51, 102, 0.1);
}

.profile-item {
    /* Tarjetas internas más transparentes */
    background: rgba(255, 255, 255, 0.5) !important;
    backdrop-filter: blur(5px);
    border-radius: 18px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1.2);
    position: relative;
    overflow: hidden;
}

.profile-item:hover {
    /* Se vuelve un poco más sólido al pasar el mouse para resaltar la info */
    background: rgba(255, 255, 255, 0.8) !important;
    transform: translateY(-8px) scale(1.03);
    border-color: var(--accent-orange) !important;
    box-shadow: 0 15px 30px rgba(241, 90, 41, 0.2);
}

.profile-item strong {
    display: block;
    font-size: 0.85rem;
    color: #003366; /* Azul YAVIRAC */
    letter-spacing: 0.5px;
    margin-bottom: 10px;
    font-weight: 700;
    text-transform: uppercase;
    position: relative;
    padding-left: 20px;
}

.profile-item strong::before {
    content: "•";
    position: absolute;
    left: 0;
    color: #f15a29; /* Naranja YAVIRAC */
    font-size: 1.5rem;
    line-height: 0.8;
}

.profile-item span {
    font-size: 1.2rem;
    color: #1a202c;
    font-weight: 600;
    display: block;
    line-height: 1.4;
}
    /* Media Queries */
    @media (max-width: 992px) {
        header { padding: 0 30px; height: 70px; }
        .header-left, .header-right { gap: 15px; }
        nav a, .dropbtn, .header-right a { padding: 10px 15px; font-size: 0.9rem; border-radius: 20px; }
        main { max-width: 90%; margin: 50px auto; padding: 35px; border-radius: 20px; }
        main h1 { font-size: 2.5rem; }
        main p.subtitle { font-size: 1rem; }
        .profile-section { grid-template-columns: 1fr; gap: 20px; padding: 25px; }
        .profile-item { padding: 20px; border-radius: 15px; }
        body::before { width: 450px; height: 450px; }
    }

    @media (max-width: 768px) {
        header { flex-direction: column; height: auto; padding: 15px 20px; align-items: stretch; }
        .header-left { margin-bottom: 10px; justify-content: center; width: 100%; }
        .header-right { flex-wrap: wrap; justify-content: center; width: 100%; gap: 10px; padding-bottom: 10px; }
        .dropdown { width: 100%; }
        .dropbtn { width: 100%; justify-content: center; }
        .dropdown-content { position: static; width: 100%; box-shadow: none; border-radius: 0; padding: 0; border: none; }
        main { padding: 20px; margin-top: 30px; border-radius: 15px; }
        main h1 { font-size: 2rem; }
        main p.subtitle { font-size: 0.9rem; margin-bottom: 30px; }
        .profile-item strong { font-size: 0.8rem; }
        .profile-item span { font-size: 1.1rem; }
        body::before { width: 350px; height: 350px; top: 60%; }
    }

    @media (max-width: 480px) {
        header { padding: 10px 15px; }
        .header-left { gap: 10px; }
        nav a, .dropbtn, .header-right a { padding: 8px 12px; font-size: 0.85rem; gap: 6px; }
        .material-icons { font-size: 1rem; margin-right: 3px; }
        main h1 { font-size: 1.8rem; }
        main p.subtitle { font-size: 0.85rem; }
        .profile-item strong { font-size: 0.75rem; }
        .profile-item span { font-size: 1rem; }
    }
</style>
</head>
<body>

<header>
  
  <!-- IZQUIERDA -->
 <div class="header-left">
    <div class="dropdown">
        <button class="dropbtn" onclick="toggleDropdown()">Menú</button>

        <div id="dropdown-content" class="dropdown-content">
            <a href="solicitar_permiso.php">Nueva Solicitud</a> 
            <a href="mis_solicitudes.php">Mis Solicitudes</a>

            <?php 
            // Usamos 'cargo' que es el nombre real en tu base de datos
            if (isset($_SESSION['usuario']['cargo'])) {
                $cargo = trim($_SESSION['usuario']['cargo']);
                
                // Validación exacta con los nombres de tu imagen
                if ($cargo === 'Rector' || $cargo === 'Jefe de Talento Humano') {
                    echo '<a href="revisar_esquemas.php">Revisar Esquemas</a>';
                }
            }
            ?>

            <?php if (isset($usuario['rol']) && $usuario['rol'] === 'admin'): ?>
                <a href="revisar_solicitudes.php">Revisar Solicitudes</a>
            <?php endif; ?>
        </div>
    </div>
</div>
  <!-- DERECHA -->
  <div class="header-right">
    <a href="logout.php" title="Cerrar Sesión" class="logout-desktop">🔒 Cerrar Sesión</a>
  </div>

</header>

<script>
function toggleDropdown() {
    document.getElementById("dropdown-content").classList.toggle("show");
}

// Cerrar menú al hacer clic fuera
window.onclick = function(e) {
  if (!e.target.matches('.dropbtn')) {
    document.querySelectorAll(".dropdown-content").forEach(dc => {
      dc.classList.remove("show");
    });
  }
}
</script>

<main>
  <h1>Bienvenido, <?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></h1>
  <p class="subtitle">A continuación, encontrarás los detalles de tu perfil en nuestro sistema. Selecciona una opción en el menú superior para comenzar tus gestiones.</p>

  <div class="profile-section">
      <h2>Datos del Perfil</h2>
      <?php if ($usuario): // Asegúrate de que los datos del usuario existan ?>
          <div class="profile-item">
              <strong>Nombre Completo:</strong>
              <span><?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></span>
          </div>
          <div class="profile-item">
              <strong>Cédula:</strong>
              <span><?= htmlspecialchars($usuario['cedula']) ?></span>
          </div>
          
          <?php if (!empty($usuario['correo'])): ?>
          <div class="profile-item">
              <strong>Correo Electrónico:</strong>
              <span><?= htmlspecialchars($usuario['correo']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['telefono'])): ?>
          <div class="profile-item">
              <strong>Teléfono:</strong>
              <span><?= htmlspecialchars($usuario['telefono']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['cargo'])): ?>
          <div class="profile-item">
              <strong>Cargo:</strong>
              <span><?= htmlspecialchars($usuario['cargo']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['direccion_area'])): ?>
          <div class="profile-item">
              <strong>Dirección/Área:</strong>
              <span><?= htmlspecialchars($usuario['direccion_area']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['regimen_laboral'])): ?>
          <div class="profile-item">
              <strong>Régimen Laboral:</strong>
              <span><?= htmlspecialchars($usuario['regimen_laboral']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['fecha_nacimiento'])): ?>
          <div class="profile-item">
              <strong>Fecha de Nacimiento:</strong>
              <span><?= htmlspecialchars($usuario['fecha_nacimiento']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['rol'])): ?>
          <div class="profile-item">
              <strong>Rol en el Sistema:</strong>
              <span><?= htmlspecialchars(ucfirst($usuario['rol'])) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($usuario['estado'])): ?>
          <div class="profile-item">
              <strong>Estado de Cuenta:</strong>
              <span><?= htmlspecialchars(ucfirst($usuario['estado'])) ?></span>
          </div>
          <?php endif; ?>

      <?php else: ?>
          <p>No se pudieron cargar los datos de tu perfil.</p>
      <?php endif; ?>
  </div>
</main>

</body>
</html>