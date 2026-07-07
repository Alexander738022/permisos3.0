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

// 2. CONSULTA DE DATOS
$sql = "SELECT p.*, tp.nombre_permiso, u.nombres, u.apellidos, u.cedula, u.cargo, u.direccion_area
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

// 3. CÁLCULO DE IMPACTO (Para Auditoría Interna)
$impacto_vacaciones = "0.00 días";
if ($p['estado'] === 'aprobado' || $p['estado'] === 'aprobado_rrhh') {
    if ($p['tipo_duracion'] === 'Días') {
        $f1 = new DateTime($p['fecha_desde']);
        $f2 = new DateTime($p['fecha_hasta']);
        $total_d = $f1->diff($f2)->days + 1;
        $impacto_vacaciones = $total_d . " día(s)";
    } else {
        $h1 = strtotime($p['hora_desde']);
        $h2 = strtotime($p['hora_hasta']);
        $total_h = ($h2 - $h1) / 3600;
        $impacto_vacaciones = number_format($total_h / 8, 2) . " día(s)";
    }
}

// 4. CLASE PDF MEJORADA
class PDF extends FPDF {
    function Header() {
        $this->SetFillColor(0, 32, 96); // Azul Institucional
        $this->Rect(10, 10, 190, 25, 'D'); 
        
        $this->SetXY(15, 12);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 32, 96);
        $this->Cell(180, 8, utf8_decode('SISTEMA DE GESTIÓN DE TALENTO HUMANO'), 0, 1, 'C');
        
        $this->SetX(15);
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(180, 6, utf8_decode('FORMULARIO DE CONTROL INTERNO DE LICENCIAS Y PERMISOS'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(10, 35);
        $this->Cell(190, 10, utf8_decode('DETALLE DE MOVIMIENTO DE PERSONAL - LOSEP'), 1, 1, 'C', true);
        $this->Ln(3);
    }

    function RowTable($label, $value, $w1 = 45) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(245, 245, 245);
        $this->Cell($w1, 8, utf8_decode($label), 1, 0, 'L', true);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 8, utf8_decode($value), 1, 1, 'L');
    }

    function SectionHeader($title) {
        $this->SetFillColor(0, 32, 96);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, utf8_decode($title), 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);

// --- 1. IDENTIFICACIÓN ---
$pdf->SectionHeader('1. DATOS DEL SERVIDOR / TRABAJADOR');
$pdf->RowTable('NOMBRES Y APELLIDOS:', $p['apellidos'] . ' ' . $p['nombres']);
$pdf->SetFont('Arial', 'B', 9); $pdf->SetFillColor(245, 245, 245);
$pdf->Cell(45, 8, utf8_decode('CÉDULA:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(50, 8, $p['cedula'], 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 9); $pdf->SetFillColor(245, 245, 245);
$pdf->Cell(45, 8, 'CARGO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 8, $p['cargo'], 1, 1, 'L');
$pdf->RowTable('DIRECCIÓN / ÁREA:', $p['direccion_area'] ?: 'DEPARTAMENTO GENERAL');
$pdf->Ln(5);

// --- 2. DETALLE DEL PERMISO ---
$pdf->SectionHeader('2. DETALLE DE LA SOLICITUD Y TIEMPOS');
$pdf->RowTable('TIPO DE PERMISO:', $p['nombre_permiso']);

// Fechas y Horas
$pdf->SetFont('Arial', 'B', 8); $pdf->SetFillColor(245, 245, 245);
$pdf->Cell(47.5, 6, 'FECHA INICIO', 1, 0, 'C', true);
$pdf->Cell(47.5, 6, 'FECHA FIN', 1, 0, 'C', true);
$pdf->Cell(47.5, 6, 'HORA INICIO', 1, 0, 'C', true);
$pdf->Cell(47.5, 6, 'HORA FIN', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(47.5, 8, $p['fecha_desde'], 1, 0, 'C');
$pdf->Cell(47.5, 8, $p['fecha_hasta'], 1, 0, 'C');
$pdf->Cell(47.5, 8, ($p['hora_desde'] ?: '--:--'), 1, 0, 'C');
$pdf->Cell(47.5, 8, ($p['hora_hasta'] ?: '--:--'), 1, 1, 'C');

// Cuadro de impacto
$pdf->SetFont('Arial', 'B', 9); $pdf->SetFillColor(255, 235, 235);
$pdf->Cell(142.5, 8, utf8_decode('DÍAS A DESCONTAR DEL SALDO DE VACACIONES:'), 1, 0, 'R', true);
$pdf->SetTextColor(200, 0, 0);
$pdf->Cell(47.5, 8, $impacto_vacaciones, 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('Arial', 'B', 9); $pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, utf8_decode('MOTIVO / OBSERVACIONES:'), 'LR', 1, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 6, utf8_decode($p['observaciones']), 'LRB', 'L');
$pdf->Ln(5);

// --- 3. FIRMAS ---
$pdf->SectionHeader('3. AUTORIZACIONES Y FIRMAS ELECTRÓNICAS');
$y_firmas = $pdf->GetY();
$w_box = 63.3; 

// Dibujar recuadros de firma
$pdf->Rect(10, $y_firmas, $w_box, 45); 
$pdf->Rect(10 + $w_box, $y_firmas, $w_box, 45); 
$pdf->Rect(10 + ($w_box*2), $y_firmas, $w_box, 45); 

// Lógica de imágenes (Mismo orden que el anterior)
$firma_solicitante = realpath(__DIR__ . '/../firmas/usuario.png');
if (file_exists($firma_solicitante)) $pdf->Image($firma_solicitante, 22, $y_firmas + 5, 40);

if ($p['estado_rector'] === 'Aprobado') {
    $firma_r = realpath(__DIR__ . '/../firmas/rector.png');
    if (file_exists($firma_r)) $pdf->Image($firma_r, 85, $y_firmas + 5, 40);
}

if ($p['estado_rrhh'] === 'Aprobado') {
    $firma_h = realpath(__DIR__ . '/../firmas/rrhh.png');
    if (file_exists($firma_h)) $pdf->Image($firma_h, 148, $y_firmas + 5, 40);
}

// Textos de firmas
$pdf->SetY($y_firmas + 35);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell($w_box, 4, '__________________________', 0, 0, 'C');
$pdf->Cell($w_box, 4, '__________________________', 0, 0, 'C');
$pdf->Cell($w_box, 4, '__________________________', 0, 1, 'C');

$pdf->Cell($w_box, 4, utf8_decode($p['nombres'] . ' ' . $p['apellidos']), 0, 0, 'C');
$pdf->Cell($w_box, 4, 'JEFE INMEDIATO / RECTOR', 0, 0, 'C');
$pdf->Cell($w_box, 4, 'TALENTO HUMANO', 0, 1, 'C');

$pdf->SetFont('Arial', '', 6);
$pdf->Cell($w_box, 4, 'FIRMA DEL SERVIDOR', 0, 0, 'C');
$pdf->Cell($w_box, 4, 'AUTORIZADO', 0, 0, 'C');
$pdf->Cell($w_box, 4, 'VALIDADO', 0, 1, 'C');

// --- PIE DE PÁGINA ---
$pdf->Ln(10);
$pdf->SetFillColor(255, 255, 204); 
$pdf->SetFont('Arial', 'I', 7);
$pdf->MultiCell(0, 4, utf8_decode("Este documento es un comprobante de control interno generado por el Sistema de Gestión LOSEP. La validez de las firmas electrónicas está sujeta a la verificación en el servidor de archivos institucionales. Los días descontados serán reflejados en el reporte mensual de vacaciones."), 1, 'J', true);

// 5. SALIDA
ob_end_clean(); 
$pdf->Output('I', "Permiso_{$id_permiso}_Control_Interno.pdf");
exit;