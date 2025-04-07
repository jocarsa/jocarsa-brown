<?php
session_start();

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

/******************************************
 * 1) Inject server‐side TOC & heading numbering
 ******************************************/
$htmlContent = addNumberingAndTOC($htmlContent);

// Create a timestamp and document title
$timestamp = date("Y-m-d-H-i-s");
$documentTitle = $pub['title'] . ' ' . $timestamp;
// For header/footer, use the title without the date
$documentTitleSimple = $pub['title'];

// Document author from current session
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
    </style>
</head>
<body>
    ' . $htmlContent . '
</body>
</html>';

// Use KnpSnappy to generate the PDF; ensure Composer's autoloader is loaded.
require_once __DIR__ . '/vendor/autoload.php';
use Knp\Snappy\Pdf;

// Instantiate Snappy with the wkhtmltopdf binary path
$snappy = new Pdf('/usr/bin/wkhtmltopdf');

// --- TOC Options (optional) ---
// $snappy->setOption('toc', '');
// $snappy->setOption('toc-header-text', 'Table of Contents');

// Set header (using the title without the date)
$snappy->setOption('header-center', $documentTitleSimple);
$snappy->setOption('header-line', true);
$snappy->setOption('header-spacing', '5');
$snappy->setOption('enable-local-file-access', true);

// Set footer with pagination
$snappy->setOption('footer-right', 'Page [page] of [topage]');
$snappy->setOption('footer-line', true);

// Set margins: 1.5 cm on all sides (adjust if you need more space)
$snappy->setOption('margin-top', '1.5cm');
$snappy->setOption('margin-bottom', '1.5cm');
$snappy->setOption('margin-left', '1.5cm');
$snappy->setOption('margin-right', '1.5cm');

// Optionally disable smart shrinking
// $snappy->setOption('disable-smart-shrinking', true);
// $snappy->setOption('print-media-type', true);

// Set PDF page size based on the publication's dimensions (in inches)
if (!empty($pub['size_h']) && !empty($pub['size_v'])) {
    $snappy->setOption('page-width', $pub['size_h'] . 'in');
    $snappy->setOption('page-height', $pub['size_v'] . 'in');
}

// Set the document title as a meta option (if supported)
$snappy->setOption('title', $documentTitle);

// Send headers so that the PDF opens inline in the browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="publication_' . $id . '.pdf"');

// Generate and output the PDF
echo $snappy->getOutputFromHtml($html);

/**************************************************************
 * FUNCTION: addNumberingAndTOC
 * Adds a server‐side Table of Contents and numbering for <h1>.. <h6>.
 **************************************************************/
function addNumberingAndTOC($html) {
    // 1) Find all headings
    $pattern = '/<h([1-6])>(.*?)<\/h\1>/i';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    if (!$matches) {
        // No headings, so no TOC
        return $html;
    }

    // 2) Keep track of heading numbers for nested numbering
    $numbering = [0,0,0,0,0,0];
    $tocItems  = [];

    // 3) Replace each <hN> with a numbered version and build a TOC array
    foreach ($matches as $m) {
        // $m[0] = the full <hN>...</hN>
        // $m[1] = "1..6" (the heading level)
        // $m[2] = the heading text
        $level       = (int)$m[1];
        $headingText = strip_tags($m[2]);

        // Increase the relevant level count
        $numbering[$level - 1]++;

        // Reset deeper levels to 0
        for ($x = $level; $x < 6; $x++) {
            $numbering[$x] = 0;
        }

        // e.g. "1.2. "
        $numLabel = implode('.', array_slice($numbering, 0, $level)) . '. ';

        // Create an ID for anchor linking
        $id = 'heading-' . md5($headingText . rand());

        // Build the new heading with numbering + ID
        $replacement = sprintf(
            '<h%d id="%s">%s%s</h%d>',
            $level,
            $id,
            $numLabel,
            $headingText,
            $level
        );

        // Replace in the HTML
        $html = str_replace($m[0], $replacement, $html);

        // Add to our TOC array
        $tocItems[] = [
            'level' => $level,
            'id'    => $id,
            'text'  => $numLabel . $headingText
        ];
    }

    // 4) Build the actual TOC HTML
    $tocHtml = '<div class="table-of-contents"><h2>Table of Contents</h2><ul>';
    foreach ($tocItems as $item) {
        // For each heading, indent based on level
        $indentPx = ($item['level'] - 1) * 20;
        $tocHtml .= sprintf(
            '<li style="margin-left:%dpx;"><a href="#%s">%s</a></li>',
            $indentPx,
            htmlspecialchars($item['id']),
            htmlspecialchars($item['text'])
        );
    }
    $tocHtml .= '</ul></div>';

    // 5) Insert that TOC block at the very top
    $html = $tocHtml . "\n\n" . $html;

    return $html;
}

