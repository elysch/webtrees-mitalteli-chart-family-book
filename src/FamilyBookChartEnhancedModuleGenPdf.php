<?php
// Include TCPDF library
require_once(__DIR__.'/../../vendor/tecnickcom/tcpdf/examples/tcpdf_include.php');

# ModSecurity: Request body no files data length is larger than the configured limit (131072)
ini_set('memory_limit', '8192M');

/**
 * Personalized class to extend TCPDF in order to add specific configurations
 */
class MYPDF extends TCPDF {
    // Place where specific configurations should be.
}

// Create a new MYPDF instance
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// PDF document configuration
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your Name');
$pdf->SetTitle('Document Title');
$pdf->SetSubject('Document subject');
$pdf->SetKeywords('TCPDF, PDF, HTML, UTF-8');

// Page configuration
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Add a landscape page
$pdf->AddPage('L', 'LETTER');

ob_start();

// Load HTML contets from file
$html = $html_report_content;

// Configure specific CSS styles for the PDF
$css = "
<style>
    div {
        font-family: 'dejavusans';
        font-size: 12px;
        line-height: 1.5;
    }
    .my-class {
        /* Ajustes de estilo específicos */
    }
    /* Otros estilos personalizados */
</style>
";

// Apply CSS stykles and render the HTML to the PDF
$pdf->writeHTML($css . $html, true, false, true, false, '');

$errores = ob_get_contents();
error_log("#########/r/n".$errores."/r/n#########");
ob_end_clean();


// Generate PDF document and close it
$pdf->Output('output.pdf', 'D');

