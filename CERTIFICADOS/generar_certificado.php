<?php
// 1. SILENCIAR ERRORES Y SALTAR NGROK
header("ngrok-skip-browser-warning: true");
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); 

require('../fpdf186/fpdf.php');
include "../db.php";
session_start();

$id_permiso = isset($_GET['id_permiso']) ? intval($_GET['id_permiso']) : null;
if (!$id_permiso) { die("ID no válido"); }

// 2. CONSULTA DE DATOS COMPLETA
$sql = "SELECT p.*, tp.nombre_permiso, u.nombres, u.apellidos, u.cedula, u.cargo 
        FROM permisos p
        JOIN tipos_permiso tp ON p.id_tipo_permiso = tp.id_tipo_permiso
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.id_permiso = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_permiso);
$stmt->execute();
$res = $stmt->get_result();
$p = $res->fetch_assoc();

if (!$p) { die("Permiso no encontrado"); }

// 3. CÁLCULO DE IMPACTO EN VACACIONES (Lógica LOSEP)
$descuento_texto = "0 días";
if ($p['estado'] === 'aprobado' || $p['estado_rrhh'] === 'Aprobado') {
    if ($p['tipo_duracion'] === 'Días') {
        $f1 = new DateTime($p['fecha_desde']);
        $f2 = new DateTime($p['fecha_hasta']);
        $dias = $f1->diff($f2)->days + 1;
        $descuento_texto = $dias . " día(s)";
    } else {
        $h1 = strtotime($p['hora_desde']);
        $h2 = strtotime($p['hora_hasta']);
        $horas = ($h2 - $h1) / 3600;
        if (strpos(strtolower($p['nombre_permiso']), 'personal') !== false) {
             $descuento_texto = number_format($horas / 8, 2) . " día(s) (proporcional)";
        } else {
             $descuento_texto = "0 días (No imputable)";
        }
    }
}

// 4. RUTAS DE FIRMAS
$firma_usuario = realpath(__DIR__ . '/../firmas/usuario.png'); 
$firma_rrhh    = realpath(__DIR__ . '/../firmas/rrhh.png');
$firma_rector  = realpath(__DIR__ . '/../firmas/rector.png');

// 5. CLASE PDF PERSONALIZADA
class PDF extends FPDF {
    function Header() {
        $this->SetFillColor(0, 32, 96); 
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 15, utf8_decode('SISTEMA DE GESTIÓN DE TALENTO HUMANO - CERTIFICADO LOSEP'), 0, 1, 'C', true);
        $this->Ln(5);
    }

    function SectionTitle($title) {
        $this->SetFillColor(235, 235, 235); 
        $this->SetTextColor(0, 51, 102);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, utf8_decode('  ' . $title), 0, 1, 'L', true);
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb} - Documento generado bajo normativa LOSEP', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// --- SECCIÓN 1: IDENTIFICACIÓN ---
$pdf->SectionTitle('1. IDENTIFICACIÓN DEL SERVIDOR PÚBLICO');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(95, 7, utf8_decode('Apellidos y Nombres:'), 0, 0);
$pdf->Cell(85, 7, utf8_decode('Cédula de Identidad:'), 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 8, utf8_decode($p['apellidos'] . ' ' . $p['nombres']), 'B', 0);
$pdf->Cell(85, 8, $p['cedula'], 'B', 1);
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 7, utf8_decode('Cargo Institucional:'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, utf8_decode($p['cargo']), 'B', 1);
$pdf->Ln(10);

// --- SECCIÓN 2: DETALLES ---
$pdf->SectionTitle('2. DETALLES DEL PERMISO / LICENCIA');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(95, 7, utf8_decode('Tipo de Permiso:'), 0, 0);
$pdf->Cell(85, 7, utf8_decode('Fecha de Solicitud:'), 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 8, utf8_decode($p['nombre_permiso']), 'B', 0);
$pdf->Cell(85, 8, $p['fecha_solicitud'], 'B', 1);
$pdf->Ln(4);

// Tiempos e Impacto
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(45, 7, utf8_decode('Desde:'), 0, 0);
$pdf->Cell(45, 7, utf8_decode('Hasta:'), 0, 0);
$pdf->Cell(90, 7, utf8_decode('Impacto en Vacaciones:'), 0, 1);

$pdf->SetFont('Arial', '', 10);
$valDesde = ($p['tipo_duracion'] == 'Días') ? $p['fecha_desde'] : $p['hora_desde'];
$valHasta = ($p['tipo_duracion'] == 'Días') ? $p['fecha_hasta'] : $p['hora_hasta'];

$pdf->Cell(45, 8, $valDesde, 'B', 0);
$pdf->Cell(45, 8, $valHasta, 'B', 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(200, 0, 0); 
$pdf->Cell(90, 8, utf8_decode($descuento_texto), 'B', 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 7, utf8_decode('Justificación:'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, utf8_decode($p['observaciones']), 1, 'L');
$pdf->Ln(10);

// --- SECCIÓN 3: FIRMAS (Orden: Solicitante, Rector, RRHH) ---
$pdf->SectionTitle('3. LEGALIZACIÓN Y FIRMAS ELECTRÓNICAS');
$y_firmas = $pdf->GetY();
$w_box = 60;

$pdf->SetDrawColor(200, 200, 200);
$pdf->Rect(15, $y_firmas, $w_box, 45); 
$pdf->Rect(75, $y_firmas, $w_box, 45); 
$pdf->Rect(135, $y_firmas, $w_box, 45); 

// Firma Solicitante
if (file_exists($firma_usuario)) $pdf->Image($firma_usuario, 25, $y_firmas + 5, 40);

// Firma Rector (Centro)
if ($p['estado_rector'] === 'Aprobado' && file_exists($firma_rector)) {
    $pdf->Image($firma_rector, 85, $y_firmas + 5, 40);
}

// Firma RRHH (Derecha)
if ($p['estado_rrhh'] === 'Aprobado' && file_exists($firma_rrhh)) {
    $pdf->Image($firma_rrhh, 145, $y_firmas + 5, 40);
}

$pdf->SetY($y_firmas + 35);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell($w_box, 4, utf8_decode($p['nombres'] . ' ' . $p['apellidos']), 0, 0, 'C');
$pdf->Cell($w_box, 4, 'JEFE INMEDIATO / RECTOR', 0, 0, 'C');
$pdf->Cell($w_box, 4, 'UNIDAD DE T. HUMANO', 0, 1, 'C');

$pdf->SetFont('Arial', '', 6);
$pdf->Cell($w_box, 3, 'FIRMA DEL SERVIDOR', 0, 0, 'C');
$pdf->Cell($w_box, 3, 'AUTORIZADO', 0, 0, 'C');
$pdf->Cell($w_box, 3, 'VALIDADO', 0, 1, 'C');

$pdf->Ln(10);

// --- NOTA LEGAL ---
$pdf->SetFillColor(255, 255, 204);
$pdf->SetFont('Arial', 'I', 7);
$pdf->MultiCell(0, 4, utf8_decode("Este certificado tiene validez jurídica conforme a la Ley de Comercio Electrónico. El descuento de vacaciones se procesará en el cierre mensual según el Art. 33 de la LOSEP."), 1, 'C', true);

ob_end_clean();
$pdf->Output('I', "Certificado_Permiso_{$p['cedula']}.pdf");
exit;