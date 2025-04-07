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
if (function_exists('markdownToHtml')) {
    $htmlContent = markdownToHtml($pub['content']);
} else {
    $htmlContent = '<p>' . htmlspecialchars($pub['content']) . '</p>';
}

// Create a timestamp and document title
$timestamp = date("Y-m-d-H-i-s");
$documentTitle = $pub['title'] . ' ' . $timestamp;
// For header/footer, use the title without the date
$documentTitleSimple = $pub['title'];

// Document author from current session
$documentAuthor = $_SESSION['nombre'] ?? 'Unknown';

// Build an HTML layout for the PDF with CSS for section numbering and increased font size
$css = @file_get_contents(__DIR__ . '/estilodocumento.css');
$html = '<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($documentTitle) . '</title>
    <meta name="author" content="' . htmlspecialchars($documentAuthor) . '">
    <meta name="title" content="' . htmlspecialchars($documentTitle) . '">
    <style>
		'.$css.'
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

// --- TOC Options ---
// For now, TOC options are commented out because they may cause errors depending on your wkhtmltopdf version.
// Uncomment these lines only if you are sure your version supports TOC generation.
// $snappy->setOption('toc', '');
// $snappy->setOption('toc-header-text', 'Table of Contents');

// Set header (using the title without the date)
$snappy->setOption('header-center', $documentTitleSimple);
$snappy->setOption('header-line', true);
$snappy->setOption('header-spacing', '5'); // Adjust spacing if needed
$snappy->setOption('enable-local-file-access', true);

// Set footer with pagination
$snappy->setOption('footer-right', 'Page [page] of [topage]');
$snappy->setOption('footer-line', true);

// Set margins: 1.5 cm on all sides
$snappy->setOption('margin-top', '1.5cm');
$snappy->setOption('margin-bottom', '1.5cm');
$snappy->setOption('margin-left', '1.5cm');
$snappy->setOption('margin-right', '1.5cm');

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

