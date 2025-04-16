<?php
// Incluir la librería TCPDF
require_once(__DIR__.'/../../vendor/tecnickcom/tcpdf/examples/tcpdf_include.php');


# ModSecurity: Request body no files data length is larger than the configured limit (131072)
ini_set('memory_limit', '8192M');

/**
 * Clase personalizada que extiende TCPDF para manejar configuraciones específicas.
 */
class MYPDF extends TCPDF {
    // Espacio para añadir configuraciones específicas si es necesario
}

// Crear una nueva instancia de la clase MYPDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configuración del documento PDF
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Tu Nombre');
$pdf->SetTitle('Título del Documento');
$pdf->SetSubject('Asunto del Documento');
$pdf->SetKeywords('TCPDF, PDF, HTML, UTF-8');

// Establecer las configuraciones de la página
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Agregar una página en orientación horizontal (paisaje)
$pdf->AddPage('L', 'LETTER');

ob_start();

// Cargar el contenido HTML desde un archivo
#$html = file_get_contents('/mnt/data/webtrees_LOGGGGGGG.log.html');
$html = $html_report_content;

// Configurar estilo CSS específico para el PDF
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

// Aplicar el estilo CSS y renderizar el HTML al PDF
$pdf->writeHTML($css . $html, true, false, true, false, '');

            error_log("FamilyBookChartModuleGenPdf.php BBBBBBBBBBBBB\r\n");
$errores = ob_get_contents();
error_log("#########/r/n".$errores."/r/n#########");
ob_end_clean();


// Cerrar y generar el archivo PDF
$pdf->Output('output.pdf', 'D');

