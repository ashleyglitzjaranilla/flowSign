<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$source = sys_get_temp_dir().'/signflow-source.pdf';
$merged = sys_get_temp_dir().'/signflow-merged.pdf';

$original = new FPDF();
$original->AddPage();
$original->SetFont('Arial', '', 12);
$original->Cell(40, 10, 'Uploaded PDF');
$original->Output('F', $source);

$pdf = new setasign\Fpdi\Fpdi();
$pageCount = $pdf->setSourceFile($source);
$template = $pdf->importPage(1);
$size = $pdf->getTemplateSize($template);
$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
$pdf->useTemplate($template);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 10, 'Signature record');
$pdf->Output('F', $merged);

if ($pageCount !== 1 || !is_file($merged) || filesize($merged) < 500) {
    throw new RuntimeException('PDF merge smoke test failed.');
}

unlink($source);
unlink($merged);
echo "PDF merge smoke test passed.\n";
