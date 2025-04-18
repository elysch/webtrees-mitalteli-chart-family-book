<?php
// Incluir la librería TCPDF
#require_once('tcpdf_include.php');
#require_once('/home/genealogy/domains/genealogy.mitalteli.com/public_html/webtrees/vendor/tecnickcom/tcpdf/examples/tcpdf_include.php');
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



####################################################################

#//============================================================+
#// File name   : example_061.php
#// Begin       : 2010-05-24
#// Last Update : 2014-01-25
#//
#// Description : Example 061 for TCPDF class
#//               XHTML + CSS
#//
#// Author: Nicola Asuni
#//
#// (c) Copyright:
#//               Nicola Asuni
#//               Tecnick.com LTD
#//               www.tecnick.com
#//               info@tecnick.com
#//============================================================+
#
#/**
# * Creates an example PDF TEST document using TCPDF
# * @package com.tecnick.tcpdf
# * @abstract TCPDF - Example: XHTML + CSS
# * @author Nicola Asuni
# * @since 2010-05-25
# * @group html
# * @group css
# * @group pdf
# */
#
#//Configure::write('debug', 0);
#error_reporting(2147483647);
#ini_set('memory_limit', '8192M');
#ini_set('max_execution_time', 1200); //600 seconds = 10 minutes
#
#// Include the main TCPDF library (search for installation path).
##require_once('../../vendor/tecnickcom/tcpdf/examples/tcpdf_include.php');
#require_once('/home/genealogy/domains/genealogy.mitalteli.com/public_html/webtrees/vendor/tecnickcom/tcpdf/examples/tcpdf_include.php');
#
#// create new PDF document
#$pdf = new TCPDF($paper_orientation, PDF_UNIT, $paper_size, true, 'UTF-8', false);
#
#// set document information
#$pdf->setCreator(PDF_CREATOR);
#$pdf->setAuthor('Nicola Asuni');
#$pdf->setTitle('TCPDF Example 061');
#$pdf->setSubject('TCPDF Tutorial');
#$pdf->setKeywords('TCPDF, PDF, example, test, guide');
#
#// set default header data
#$pdf->setHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 061', PDF_HEADER_STRING);
#
#// set header and footer fonts
#$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
#$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
#
#// set default monospaced font
#$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);
#
#// set margins
#$pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
#$pdf->setHeaderMargin(PDF_MARGIN_HEADER);
#$pdf->setFooterMargin(PDF_MARGIN_FOOTER);
#
#// set auto page breaks
#$pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
#
#// set image scale factor
#$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
#
#// set some language-dependent strings (optional)
#if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
#	require_once(dirname(__FILE__).'/lang/eng.php');
#	$pdf->setLanguageArray($l);
#}
#
#// ---------------------------------------------------------
#
#// set font
##$pdf->setFont('helvetica', '', 10);
#$pdf->setFont('dejavusans', '', 10);
#
#// add a page
#$pdf->AddPage();
#
#/* NOTE:
# * *********************************************************
# * You can load external XHTML using :
# *
# * $html = file_get_contents('/path/to/your/file.html');
# *
# * External CSS files will be automatically loaded.
# * Sometimes you need to fix the path of the external CSS.
# * *********************************************************
# */
#
#// define some HTML content with style
#$html = <<<EOF
#<!-- EXAMPLE OF CSS STYLE -->
#<style>
#	h1 {
#		color: navy;
#		font-family: times;
#		font-size: 24pt;
#		text-decoration: underline;
#	}
#	p.first {
#		color: #003300;
#		font-family: helvetica;
#		font-size: 12pt;
#	}
#	p.first span {
#		color: #006600;
#		font-style: italic;
#	}
#	p#second {
#		color: rgb(00,63,127);
#		font-family: times;
#		font-size: 12pt;
#		text-align: justify;
#	}
#	p#second > span {
#		background-color: #FFFFAA;
#	}
#	table.first {
#		color: #003300;
#		font-family: helvetica;
#		font-size: 8pt;
#		border-left: 3px solid red;
#		border-right: 3px solid #FF00FF;
#		border-top: 3px solid green;
#		border-bottom: 3px solid blue;
#		background-color: #ccffcc;
#	}
#	td {
#		border: 2px solid blue;
#		background-color: #ffffee;
#	}
#	td.second {
#		border: 2px dashed green;
#	}
#	div.test {
#		color: #CC0000;
#		background-color: #FFFF66;
#		font-family: helvetica;
#		font-size: 10pt;
#		border-style: solid solid solid solid;
#		border-width: 2px 2px 2px 2px;
#		border-color: green #FF00FF blue red;
#		text-align: center;
#	}
#	.lowercase {
#		text-transform: lowercase;
#	}
#	.uppercase {
#		text-transform: uppercase;
#	}
#	.capitalize {
#		text-transform: capitalize;
#	}
#</style>
#
#<h1 class="title">Example of <i style="color:#990000">XHTML + CSS</i></h1>
#
#<p class="first">Example of paragraph with class selector. <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit. In sed imperdiet lectus. Phasellus quis velit velit, non condimentum quam. Sed neque urna, ultrices ac volutpat vel, laoreet vitae augue. Sed vel velit erat. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras eget velit nulla, eu sagittis elit. Nunc ac arcu est, in lobortis tellus. Praesent condimentum rhoncus sodales. In hac habitasse platea dictumst. Proin porta eros pharetra enim tincidunt dignissim nec vel dolor. Cras sapien elit, ornare ac dignissim eu, ultricies ac eros. Maecenas augue magna, ultrices a congue in, mollis eu nulla. Nunc venenatis massa at est eleifend faucibus. Vivamus sed risus lectus, nec interdum nunc.</span></p>
#
#<p id="second">Example of paragraph with ID selector. <span>Fusce et felis vitae diam lobortis sollicitudin. Aenean tincidunt accumsan nisi, id vehicula quam laoreet elementum. Phasellus egestas interdum erat, et viverra ipsum ultricies ac. Praesent sagittis augue at augue volutpat eleifend. Cras nec orci neque. Mauris bibendum posuere blandit. Donec feugiat mollis dui sit amet pellentesque. Sed a enim justo. Donec tincidunt, nisl eget elementum aliquam, odio ipsum ultrices quam, eu porttitor ligula urna at lorem. Donec varius, eros et convallis laoreet, ligula tellus consequat felis, ut ornare metus tellus sodales velit. Duis sed diam ante. Ut rutrum malesuada massa, vitae consectetur ipsum rhoncus sed. Suspendisse potenti. Pellentesque a congue massa.</span></p>
#
#<div class="test">example of DIV with border and fill.
#<br />Lorem ipsum dolor sit amet, consectetur adipiscing elit.
#<br /><span class="lowercase">text-transform <b>LOWERCASE</b> Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
#<br /><span class="uppercase">text-transform <b>uppercase</b> Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
#<br /><span class="capitalize">text-transform <b>cAPITALIZE</b> Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
#</div>
#
#<br />
#
#<table class="first" cellpadding="4" cellspacing="6">
# <tr>
#  <td width="30" align="center"><b>No.</b></td>
#  <td width="140" align="center" bgcolor="#FFFF00"><b>XXXX</b></td>
#  <td width="140" align="center"><b>XXXX</b></td>
#  <td width="80" align="center"> <b>XXXX</b></td>
#  <td width="80" align="center"><b>XXXX</b></td>
#  <td width="45" align="center"><b>XXXX</b></td>
# </tr>
# <tr>
#  <td width="30" align="center">1.</td>
#  <td width="140" rowspan="6" class="second">XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX</td>
#  <td width="140">XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td width="80">XXXX</td>
#  <td align="center" width="45">XXXX<br />XXXX</td>
# </tr>
# <tr>
#  <td width="30" align="center" rowspan="3">2.</td>
#  <td width="140" rowspan="3">XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td align="center" width="45">XXXX<br />XXXX</td>
# </tr>
# <tr>
#  <td width="80">XXXX<br />XXXX<br />XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td align="center" width="45">XXXX<br />XXXX</td>
# </tr>
# <tr>
#  <td width="80" rowspan="2" >XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td align="center" width="45">XXXX<br />XXXX</td>
# </tr>
# <tr>
#  <td width="30" align="center">3.</td>
#  <td width="140">XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td align="center" width="45">XXXX<br />XXXX</td>
# </tr>
# <tr bgcolor="#FFFF80">
#  <td width="30" align="center">4.</td>
#  <td width="140" bgcolor="#00CC00" color="#FFFF00">XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td width="80">XXXX<br />XXXX</td>
#  <td align="center" width="45">XXXX<br />XXXX</td>
# </tr>
#</table>
#EOF;
#
##$html_report_content = file_get_contents("Family_book_test.html", true);
##$html_report_content = mb_convert_encoding($html_report_content, 'HTML-ENTITIES', "UTF-8");
###$html_report_content = $html;
#
#            error_log("FamilyBookChartModuleGenPdf.php rep_cont: " . substr($html_report_content,0,50) . "\r\n");
#
#            error_log("FamilyBookChartModuleGenPdf.php AAAAAAAAAAAAA\r\n");
#
#ob_start();
#// output the HTML content
##$pdf->writeHTML($html, true, false, true, false, '');
#$pdf->writeHTML($html_report_content, true, false, true, false, '');
#            error_log("FamilyBookChartModuleGenPdf.php BBBBBBBBBBBBB2\r\n");
#
#// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
#
##// add a page
##$pdf->AddPage();
##
##$html = '
##<h1>HTML TIPS & TRICKS</h1>
##
##<h3>REMOVE CELL PADDING</h3>
##<pre>$pdf->setCellPadding(0);</pre>
##This is used to remove any additional vertical space inside a single cell of text.
##
##<h3>REMOVE TAG TOP AND BOTTOM MARGINS</h3>
##<pre>$tagvs = array(\'p\' => array(0 => array(\'h\' => 0, \'n\' => 0), 1 => array(\'h\' => 0, \'n\' => 0)));
##$pdf->setHtmlVSpace($tagvs);</pre>
##Since the CSS margin command is not yet implemented on TCPDF, you need to set the spacing of block tags using the following method.
##
##<h3>SET LINE HEIGHT</h3>
##<pre>$pdf->setCellHeightRatio(1.25);</pre>
##You can use the following method to fine tune the line height (the number is a percentage relative to font height).
##
##<h3>CHANGE THE PIXEL CONVERSION RATIO</h3>
##<pre>$pdf->setImageScale(0.47);</pre>
##This is used to adjust the conversion ratio between pixels and document units. Increase the value to get smaller objects.<br />
##Since you are using pixel unit, this method is important to set theright zoom factor.<br /><br />
##Suppose that you want to print a web page larger 1024 pixels to fill all the available page width.<br />
##An A4 page is larger 210mm equivalent to 8.268 inches, if you subtract 13mm (0.512") of margins for each side, the remaining space is 184mm (7.244 inches).<br />
##The default resolution for a PDF document is 300 DPI (dots per inch), so you have 7.244 * 300 = 2173.2 dots (this is the maximum number of points you can print at 300 DPI for the given width).<br />
##The conversion ratio is approximatively 1024 / 2173.2 = 0.47 px/dots<br />
##If the web page is larger 1280 pixels, on the same A4 page the conversion ratio to use is 1280 / 2173.2 = 0.59 pixels/dots';
##
##// output the HTML content
##$pdf->writeHTML($html, true, false, true, false, '');
#
#// reset pointer to the last page
#$pdf->lastPage();
#
#// ---------------------------------------------------------
#
#            error_log("FamilyBookChartModuleGenPdf.php BBBBBBBBBBBBB\r\n");
#$errores = ob_get_contents();
#error_log("#########/r/n".$errores."/r/n#########");
#ob_end_clean();
#//Close and output PDF document
#$pdf->Output('example_061.pdf', 'I');
#
#//============================================================+
#// END OF FILE
#//============================================================+
