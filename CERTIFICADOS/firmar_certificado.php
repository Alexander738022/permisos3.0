<?php
use setasign\Fpdi\Fpdi;
require '../vendor/autoload.php';
include "../db.php";

$id_permiso = $_GET['id_permiso'] ?? null;
if (!$id_permiso) die("ID inválido");

session_start();
$cargo = $_SESSION['usuario']['cargo'] ?? '';

$pdf_base = __DIR__ . "/../uploads/permiso_$id_permiso.pdf";
if (!file_exists($pdf_base)) {
    die("No existe el PDF base");
}

/* === CONFIGURAR FIRMA === */
if ($cargo === 'Jefe de Talento Humano') {
    $firma = __DIR__ . '/../firmas/rrhh.png';
    $x = 20;  // izquierda
    $y = 210;
} elseif ($cargo === 'Rector') {
    $firma = __DIR__ . '/../firmas/rector.png';
    $x = 120; // derecha
    $y = 210;
} else {
    die("No autorizado");
}

/* === IMPORTAR PDF BASE === */
$pdf = new Fpdi();
$pageCount = $pdf->setSourceFile($pdf_base);

for ($i = 1; $i <= $pageCount; $i++) {
    $tpl = $pdf->importPage($i);
    $pdf->AddPage();
    $pdf->useTemplate($tpl);
}

/* === INSERTAR FIRMA === */
$pdf->Image($firma, $x, $y, 50);

/* === SOBRESCRIBIR EL MISMO PDF === */
$pdf->Output('F', $pdf_base);

/* === ACTUALIZAR BD === */
$stmt = $conn->prepare("
    UPDATE permisos 
    SET archivo_justificativo = ? 
    WHERE id_permiso = ?
");
$ruta_publica = "uploads/permiso_$id_permiso.pdf";
$stmt->bind_param("si", $ruta_publica, $id_permiso);
$stmt->execute();

echo "Documento firmado correctamente";
