
<?php
session_start();

// 1. Verificar sesión y roles
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$cargos_usuario = $_SESSION['usuario']['cargo']; 
$cargos_autorizados = ['Rector', 'Jefe de Talento Humano'];

if (!in_array($cargos_usuario, $cargos_autorizados)) {
    echo "<script>
            alert('Acceso restringido: Solo el Rector o Talento Humano pueden ver las estadísticas.');
            window.location.href='dashboard.php';
          </script>";
    exit();
}

// 2. Conexión a la base de datos
$conexion = new mysqli("localhost", "root", "", "proyecto");

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->query("SET lc_time_names = 'es_ES'");

// --- EXTRACCIÓN DE DATOS CORREGIDA ---

// 1. Análisis Mensual (Suma de días solicitados)
$res_mes = $conexion->query("SELECT MONTHNAME(fecha_solicitud) as etiqueta, SUM(dias_solicitados) as total 
                             FROM permisos 
                             GROUP BY MONTH(fecha_solicitud) 
                             ORDER BY MONTH(fecha_solicitud)");
$labels_mes = []; $data_mes = [];
if($res_mes){
    while($r = $res_mes->fetch_assoc()){ 
        $labels_mes[] = ucfirst($r['etiqueta']); 
        $data_mes[] = $r['total'] ?? 0; 
    }
}

// 2. Por Empleado (Suma de días solicitados)
$res_emp = $conexion->query("SELECT u.nombres as etiqueta, SUM(p.dias_solicitados) as total 
                             FROM permisos p 
                             INNER JOIN usuarios u ON p.id_usuario = u.id_usuario 
                             GROUP BY u.nombres 
                             ORDER BY total DESC");
$labels_emp = []; $data_emp = [];
if($res_emp){
    while($r = $res_emp->fetch_assoc()){ 
        $labels_emp[] = $r['etiqueta']; 
        $data_emp[] = $r['total'] ?? 0; 
    }
}

// 3. Por Motivos (Suma de días solicitados)
$res_raz = $conexion->query("SELECT t.nombre_permiso as etiqueta, SUM(p.dias_solicitados) as total 
                             FROM permisos p 
                             INNER JOIN tipos_permiso t ON p.id_tipo_permiso = t.id_tipo_permiso 
                             GROUP BY t.nombre_permiso");
$labels_raz = []; $data_raz = [];
if($res_raz){
    while($r = $res_raz->fetch_assoc()){ 
        $labels_raz[] = $r['etiqueta']; 
        $data_raz[] = $r['total'] ?? 0; 
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Estadísticas - Yavira</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-deep: #002D5A; --primary-medium: #004080; --primary-light: #0056B3;
            --accent-orange: #f15a29; --accent-orange-hover: #ff7345;
            --text-dark: #2c3e50; --text-medium: #556677; --bg-main: #eef3f7;
            --white: #ffffff; --shadow-medium: rgba(0, 0, 0, 0.25);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-main) 0%, #dce4eb 100%);
            min-height: 100vh;
            position: relative;
        }

        header {
            position: sticky; top: 0;
            background: linear-gradient(to right, var(--primary-deep), var(--primary-light));
            height: 80px; display: flex; align-items: center; justify-content: space-between;
            padding: 0 50px; box-shadow: 0 8px 18px var(--shadow-medium);
            z-index: 1000; border-bottom: 4px solid var(--accent-orange);
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
      .material-icons {
        font-size: 1.2rem;
        margin-right: 5px; /* Espacio entre icono y texto */
    }

        /* DROPDOWN - EFECTO CRISTAL */
        .dropdown { position: relative; display: inline-block; }
        
        .dropdown-content {
            display: none; /* Oculto por defecto */
            position: absolute; 
            background: rgba(255, 255, 255, 0.7); /* Transparencia */
            backdrop-filter: blur(12px); /* Desenfoque cristal */
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            min-width: 200px; 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border-radius: 15px; 
            top: calc(100% + 15px); 
            padding: 8px 0;
            z-index: 1001;
        }

        .show { 
            display: block !important; 
            animation: slideInMenu 0.3s ease;
        }

        .dropdown-content a {
            color: var(--primary-deep); 
            padding: 12px 20px; 
            text-decoration: none;
            display: block; 
            font-weight: 600; 
            transition: 0.3s;
            background: none;
            border: none;
        }

        .dropdown-content a:hover { 
            background: rgba(241, 90, 41, 0.15); 
            color: var(--accent-orange); 
            padding-left: 25px; 
        }

        /* CONTENIDO PRINCIPAL */
        main {
            max-width: 1100px; width: 90%; margin: 50px auto; padding: 40px;
            background: rgba(255, 255, 255, 0.9); border-radius: 28px;
            box-shadow: 0 25px 60px var(--shadow-medium);
        }

        .profile-section {
            background: rgba(255, 255, 255, 0.3); backdrop-filter: blur(10px);
            border-radius: 24px; padding: 30px; border: 1px solid rgba(255, 255, 255, 0.5);
            margin-top: 30px; display: none;
        }
        .profile-section.active { display: block; animation: slideIn 0.5s ease; }

        .chart-wrapper { position: relative; height: 400px; width: 100%; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideInMenu { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        h1 { font-weight: 800; font-size: 2.5rem; background: linear-gradient(45deg, var(--accent-orange), #e04a1f); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-align: center; }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <nav>
            <a href="dashboard.php" class="home-link-left">🏠Inicio</a>
        </nav>
        <div class="dropdown">
            <button class="dropbtn" onclick="toggleMenu(event)">
                <span class="material-icons">bar_chart</span> Ver Esquemas
            </button>
            <div id="myDropdown" class="dropdown-content">
                <a href="#" onclick="showDiagram('mes')">Análisis Mensual</a>
                <a href="#" onclick="showDiagram('empleado')">Por Empleado</a>
                <a href="#" onclick="showDiagram('razon')">Por Razones</a>
            </div>
        </div>
    </div>
    <div class="header-right">
        <a href="logout.php">🔒 Cerrar Sesión</a>
    </div>
</header>

<main>
    <h1>Análisis Estadístico Yavira</h1>
    <p style="text-align:center; color: var(--text-medium); margin-bottom: 20px;">Haz clic en "Ver Esquemas" para alternar las gráficas.</p>

    <div id="mes" class="profile-section active">
        <h3 style="color: var(--primary-deep); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span class="material-icons">calendar_today</span> Histórico Mensual de Faltas
        </h3>
        <div class="chart-wrapper"><canvas id="cMes"></canvas></div>
    </div>

    <div id="empleado" class="profile-section">
        <h3 style="color: var(--primary-deep); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span class="material-icons">groups</span> Permisos por Servidor Público
        </h3>
        <div class="chart-wrapper"><canvas id="cEmp"></canvas></div>
    </div>

    <div id="razon" class="profile-section">
        <h3 style="color: var(--primary-deep); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span class="material-icons">fact_check</span> Motivos de Ausentismo
        </h3>
        <div class="chart-wrapper"><canvas id="cRaz"></canvas></div>
    </div>
</main>

<script>
    /* LÓGICA PARA EL CLIC DEL MENÚ */
    function toggleMenu(event) {
        event.stopPropagation(); 
        document.getElementById("myDropdown").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    function showDiagram(id) {
        document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        document.getElementById("myDropdown").classList.remove("show");
    }

    /* CONFIGURACIÓN DE GRÁFICOS */
    const options = { responsive: true, maintainAspectRatio: false };

    new Chart(document.getElementById('cMes'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels_mes); ?>,
            datasets: [{
                label: 'Cantidad', data: <?php echo json_encode($data_mes); ?>,
                borderColor: '#f15a29', backgroundColor: 'rgba(241, 90, 41, 0.1)', fill: true, tension: 0.4
            }]
        },
        options: options
    });

    new Chart(document.getElementById('cEmp'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels_emp); ?>,
            datasets: [{
                label: 'Total Permisos', data: <?php echo json_encode($data_emp); ?>,
                backgroundColor: '#002D5A', borderRadius: 10
            }]
        },
        options: options
    });

    new Chart(document.getElementById('cRaz'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($labels_raz); ?>,
            datasets: [{
                data: <?php echo json_encode($data_raz); ?>,
                backgroundColor: ['#f15a29', '#002D5A', '#0056B3', '#556677']
            }]
        },
        options: options
    });
</script>

</body>
</html>