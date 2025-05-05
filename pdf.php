<?php
session_start();

// Increase memory limit and suppress on‐screen errors
ini_set('memory_limit', '256M');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized access');
}

// Connect to the SQLite database
$db_file = __DIR__ . '/database_v2.sqlite';
try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get the publication id from the URL
$id = $_GET['id'] ?? '';
if (!$id) {
    die('No publication id specified.');
}

// Fetch the publication record
$stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
$stmt->execute([':id' => $id]);
$pub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pub) {
    die('Publication not found.');
}

// Include the Markdown parser
require_once('inc/jocarsa | navy.php');

// Convert the publication’s content from Markdown to HTML
action: {
    if (function_exists('markdownToHtml')) {
        $htmlContent = markdownToHtml($pub['content']);
    } else {
        $htmlContent = '<p>' . htmlspecialchars($pub['content']) . '</p>';
    }
}

// Inject heading numbering (we keep manual TOC injections if desired)
$htmlContent = addNumberingAndTOC($htmlContent);

// Create timestamp and document title
$timestamp = date("Y-m-d-H-i-s");
$documentTitle       = $pub['title'] . ' ' . $timestamp;
$documentTitleSimple = $pub['title'];
$documentAuthor      = $_SESSION['nombre'] ?? 'Unknown';

// Build an HTML layout for the PDF with CSS
$css = @file_get_contents(__DIR__ . '/estilodocumento.css');
// Optionally append an explicit <hr> style to ensure visibility
$css .= "\nhr { border: none; border-top: 1px solid #888; margin: 1.5em 0; height: 0; }";

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($documentTitle) . '</title>
    <meta name="author" content="' . htmlspecialchars($documentAuthor) . '">
    <meta name="title" content="' . htmlspecialchars($documentTitle) . '">
    <style>' . $css . '</style>
</head>
<body>

<!-- Página 1: solo título grande centrado -->
<div style="text-align: center; font-size: 5em; margin-top: 40vh;">
    <strong>' . htmlspecialchars($documentTitleSimple) . '</strong>
</div>
<div style="page-break-after: always;"></div>

<!-- Página 2: en blanco -->
<div style="page-break-after: always;">.</div>

<!-- Página 3: título, subtítulo, autor -->
<div style="text-align: center; margin-top: 30vh;">
    <h1 style="font-size: 5em; margin-top: 40vh;">' . htmlspecialchars($documentTitleSimple) . '</h1>
    <h2 style="font-size: 3em; margin-top: 40vh;">' . htmlspecialchars($pub['subtitle'] ?? '') . '</h2>
    <p style="margin-top: 3em; font-size: 2em;">Autor: ' . htmlspecialchars($documentAuthor) . '</p>
</div>
<div style="page-break-after: always;"></div>

<!-- Página 4: en blanco -->
<div style="page-break-after: always;">.</div>

<!-- Página 5: dedicatoria -->
<div style="text-align: center; margin-top: 40vh; font-style: italic;">
    <p>“Este libro está dedicado a todos los que aman aprender.”</p>
</div>
<div style="page-break-after: always;"></div>

<!-- Página 6: en blanco -->
<div style="page-break-after: always;">.</div>

<!-- Página 7: contenido con TOC y demás -->
' . $htmlContent . '

</body>
</html>';

// Generate PDF with KnpSnappy\Pdf
require_once __DIR__ . '/vendor/autoload.php';
use Knp\Snappy\Pdf;

$snappy = new Pdf('/usr/bin/wkhtmltopdf');
$snappy->setOption('enable-local-file-access', true);

// Margins
$snappy->setOption('margin-top',    '20mm');
$snappy->setOption('margin-bottom', '20mm');
$snappy->setOption('margin-left',   '15mm');
$snappy->setOption('margin-right',  '15mm');

// FULL-HTML HEADER (Option B)
$snappy->setOption('header-html',   __DIR__ . '/header.html');
$snappy->setOption('header-spacing', 5);

// Footer with page numbers
$snappy->setOption('footer-right',   'Page [page] of [topage]');
$snappy->setOption('footer-line',    true);

// Table of Contents with page numbers
$snappy->setOption('toc',             true);
$snappy->setOption('toc-header-text', 'Índice');

// Optional: dynamic page size
if (!empty($pub['size_h']) && !empty($pub['size_v'])) {
    $snappy->setOption('page-width',  $pub['size_h'] . 'in');
    $snappy->setOption('page-height', $pub['size_v'] . 'in');
}

$snappy->setOption('title', $documentTitle);

try {
    $pdfOutput = $snappy->getOutputFromHtml($html);
} catch (Exception $e) {
    die('PDF generation error: ' . $e->getMessage());
}

// -------------------------------------------------------
// 1. Save PDF into a "pdf" folder with publication id name
// -------------------------------------------------------
$pdfDir = __DIR__ . '/pdf';
if (!file_exists($pdfDir)) {
    mkdir($pdfDir, 0777, true);
}
$pdfFile = $pdfDir . '/publication_' . $id . '.pdf';

// -------------------------------------------------------
// 2. Optional PDF password protection (using a config field)
// -------------------------------------------------------
if (!empty($pub['pdf_password'])) {
    $tempPdf   = $pdfDir . '/temp_' . $id . '.pdf';
    file_put_contents($tempPdf, $pdfOutput);
    $pw           = escapeshellarg($pub['pdf_password']);
    $cmd          = "qpdf --encrypt $pw $pw 256 -- $tempPdf " . escapeshellarg($pdfFile);
    exec($cmd, $out, $ret);
    if ($ret !== 0) {
        file_put_contents($pdfFile, $pdfOutput);
    }
    unlink($tempPdf);
} else {
    file_put_contents($pdfFile, $pdfOutput);
}

// -------------------------------------------------------
// 3. Redirect to the saved PDF URL
// -------------------------------------------------------
header("Location: pdf/publication_" . $id . ".pdf");
exit;


/*********** FUNCTION: addNumberingAndTOC **********/
function addNumberingAndTOC($html) {
    // (kept unchanged) ...
    $pattern = '/<h([1-6])>(.*?)<\/h\1>/i';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    if (!$matches) return $html;
    $num = [0,0,0,0,0,0];
    $toc = [];
    foreach ($matches as $m) {
        $lvl = (int)$m[1];
        $txt = strip_tags($m[2]);
        $num[$lvl-1]++;
        for ($i=$lvl; $i<6; $i++) $num[$i]=0;
        $label = implode('.', array_slice($num,0,$lvl)) . '. ';
        $id    = 'heading-'.md5($txt . rand());
        $html  = str_replace($m[0], sprintf('<h%d id="%s">%s%s</h%d>',
                        $lvl, $id, $label, $txt, $lvl), $html);
        $toc[] = ['level'=>$lvl,'id'=>$id,'text'=>$label.$txt];
    }
    // build manual TOC if you still want it
    $tocHtml = '<div class="table-of-contents"><h2>Índice</h2><ul>';
    foreach ($toc as $item) {
        $tocHtml .= sprintf('<li style="margin-left:%dpx;">
            <a href="#%s">%s</a></li>',
            ($item['level']-1)*20,
            htmlspecialchars($item['id']),
            htmlspecialchars($item['text']));
    }
    $tocHtml .= '</ul></div>';
    return $tocHtml . "\n\n" . $html;
}
?>

