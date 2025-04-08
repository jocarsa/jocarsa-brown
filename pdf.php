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

// Include the Markdown parser (adjust the path if needed)
require_once('inc/jocarsa | navy.php');

// Convert the publication’s content from Markdown to HTML
if (function_exists('markdownToHtml')) {
    $htmlContent = markdownToHtml($pub['content']);
} else {
    $htmlContent = '<p>' . htmlspecialchars($pub['content']) . '</p>';
}

// Inject server-side TOC & heading numbering
$htmlContent = addNumberingAndTOC($htmlContent);

// Create timestamp and document title
$timestamp = date("Y-m-d-H-i-s");
$documentTitle = $pub['title'] . ' ' . $timestamp;
$documentTitleSimple = $pub['title'];  // for header/footer
$documentAuthor = $_SESSION['nombre'] ?? 'Unknown';

// Build an HTML layout for the PDF with CSS
$css = @file_get_contents(__DIR__ . '/estilodocumento.css');
$html = '<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($documentTitle) . '</title>
    <meta name="author" content="' . htmlspecialchars($documentAuthor) . '">
    <meta name="title" content="' . htmlspecialchars($documentTitle) . '">
    <style>
        ' . $css . '
        h1 { page-break-before: always; }
    </style>
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
    <h1 style="text-align: center; font-size: 5em; margin-top: 40vh;">' . htmlspecialchars($documentTitleSimple) . '</h1>
    <h2 style="text-align: center; font-size: 3em; margin-top: 40vh;">' . htmlspecialchars($pub['subtitle'] ?? '') . '</h2>
    <p style="margin-top: 3em;text-align: center; font-size: 2em; margin-top: 40vh;">Autor: ' . htmlspecialchars($documentAuthor) . '</p>
</div>
<div style="page-break-after: always;"> </div>
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

// Use Composer's autoloader and instantiate KnpSnappy\Pdf
require_once __DIR__ . '/vendor/autoload.php';
use Knp\Snappy\Pdf;
$snappy = new Pdf('/usr/bin/wkhtmltopdf');

// Set header and footer options along with margins and other options
$snappy->setOption('header-center', $documentTitleSimple);
$snappy->setOption('header-line', true);
$snappy->setOption('header-spacing', '5');
$snappy->setOption('enable-local-file-access', true);
$snappy->setOption('footer-right', 'Page [page] of [topage]');
$snappy->setOption('footer-line', true);
$snappy->setOption('margin-top', '2.5cm');
$snappy->setOption('margin-bottom', '2.5cm');
$snappy->setOption('margin-left', '1.5cm');
$snappy->setOption('margin-right', '1.5cm');
$snappy->setOption('header-html', __DIR__ . '/header.html');

// Optionally, set page size if publication dimensions are provided
if (!empty($pub['size_h']) && !empty($pub['size_v'])) {
    $snappy->setOption('page-width', $pub['size_h'] . 'in');
    $snappy->setOption('page-height', $pub['size_v'] . 'in');
}
$snappy->setOption('title', $documentTitle);

// Generate the PDF output
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
// For this example, we assume that a new database field named `pdf_password` exists.
// If a password is provided for the publication, the PDF is encrypted using qpdf.
if (!empty($pub['pdf_password'])) {
    // Save the unencrypted PDF temporarily
    $tempPdfFile = $pdfDir . '/temp_publication_' . $id . '.pdf';
    file_put_contents($tempPdfFile, $pdfOutput);

    // Prepare the command to encrypt using qpdf.
    // We use the same password for user and owner, and a 256-bit key.
    $password = escapeshellarg($pub['pdf_password']);
    $tempPdfFileEscaped = escapeshellarg($tempPdfFile);
    $pdfFileEscaped = escapeshellarg($pdfFile);
    $cmd = "qpdf --encrypt $password $password 256 -- $tempPdfFileEscaped $pdfFileEscaped";
    exec($cmd, $output, $returnVar);
    if ($returnVar !== 0) {
         // If encryption fails, fall back to saving without encryption
         file_put_contents($pdfFile, $pdfOutput);
    }
    // Remove the temporary file
    unlink($tempPdfFile);
} else {
    // No password provided: save the PDF directly (overwriting any existing file)
    file_put_contents($pdfFile, $pdfOutput);
}

// -------------------------------------------------------
// 3. Redirect the browser to the saved PDF URL
// -------------------------------------------------------
// Assuming the “pdf” folder is web-accessible (e.g. via URL: http://yourdomain.com/jocarsa-brown/pdf/)
// This allows the generated PDF to be viewed in a new browser tab.
$relativeUrl = 'pdf/publication_' . $id . '.pdf';
header("Location: $relativeUrl");
exit;


/*****************************************************************
 * FUNCTION: addNumberingAndTOC
 * Adds server-side Table of Contents and numbering for <h1>.. <h6>.
 *****************************************************************/
function addNumberingAndTOC($html) {
    $pattern = '/<h([1-6])>(.*?)<\/h\1>/i';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    if (!$matches) {
        return $html;
    }
    $numbering = [0,0,0,0,0,0];
    $tocItems  = [];
    foreach ($matches as $m) {
        $level       = (int)$m[1];
        $headingText = strip_tags($m[2]);
        $numbering[$level - 1]++;
        for ($x = $level; $x < 6; $x++) {
            $numbering[$x] = 0;
        }
        $numLabel = implode('.', array_slice($numbering, 0, $level)) . '. ';
        $id = 'heading-' . md5($headingText . rand());
        $replacement = sprintf(
            '<h%d id="%s">%s%s</h%d>',
            $level,
            $id,
            $numLabel,
            $headingText,
            $level
        );
        $html = str_replace($m[0], $replacement, $html);
        $tocItems[] = [
            'level' => $level,
            'id'    => $id,
            'text'  => $numLabel . $headingText
        ];
    }
    $tocHtml = '<div class="table-of-contents"><h2>Table of Contents</h2><ul>';
    foreach ($tocItems as $item) {
        $indentPx = ($item['level'] - 1) * 20;
        $tocHtml .= sprintf(
            '<li style="margin-left:%dpx;"><a href="#%s">%s</a></li>',
            $indentPx,
            htmlspecialchars($item['id']),
            htmlspecialchars($item['text'])
        );
    }
    $tocHtml .= '</ul></div>';
    $html = $tocHtml . "\n\n" . $html;
    return $html;
}
?>

