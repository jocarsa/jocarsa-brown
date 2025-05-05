<?php
session_start();

// Increase memory limit and log errors
ini_set('memory_limit', '256M');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized access');
}

// Connect to SQLite database
$db_file = __DIR__ . '/database_v2.sqlite';
try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Get publication ID
$id = $_GET['id'] ?? '';
if (!$id) {
    die('No publication id specified.');
}

// Fetch publication record
$stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
$stmt->execute([':id' => $id]);
$pub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pub) {
    die('Publication not found.');
}

// Include Markdown parser
require_once __DIR__ . '/inc/jocarsa | navy.php';

// Convert Markdown to HTML and inject numbering & manual TOC
$htmlContent = function_exists('markdownToHtml')
    ? markdownToHtml($pub['content'])
    : '<p>' . htmlspecialchars($pub['content']) . '</p>';
$htmlContent = addNumberingAndTOC($htmlContent);

// Generate titles and author info
date_default_timezone_set('Europe/Madrid');
$timestamp = date('Y-m-d-H-i-s');
$docTitleSimple = $pub['title'];
$docTitle       = $docTitleSimple . ' ' . $timestamp;
$docAuthor      = $_SESSION['nombre'] ?? 'Unknown';

// Load CSS and append custom rules
$css = @file_get_contents(__DIR__ . '/estilodocumento.css');
$css .= <<<CSS

/* Page setup */
@page {
  margin-top: 20mm;
  margin-bottom: 15mm;
  margin-left: 15mm;
  margin-right: 15mm;
}

/* Ensure <hr> is visible */
hr {
  border: none;
  border-top: 1px solid #888;
  margin: 1.5em 0;
  height: 0;
}

/* Fixed header */
.pdf-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 15mm;
  border-bottom: 1px solid #ccc;
  background: white;
  text-align: center;
  line-height: 15mm;
  font-size: 12px;
}

/* Fixed footer with page numbers */
.pdf-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 15mm;
  border-top: 1px solid #ccc;
  background: white;
  padding-right: 10mm;
  text-align: right;
  line-height: 15mm;
  font-size: 12px;
}
.pdf-footer:after {
  content: 'Page ' counter(page) ' of ' counter(pages);
}

/* Body content below header/footer */
body {
  margin: 0;
  padding: 0;
}
CSS;

// Build HTML
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>\${docTitle}</title>
  <meta name="author" content="\${docAuthor}">
  <meta name="title" content="\${docTitle}">
  <style>
    \$css
  </style>
</head>
<body>

  <!-- Header & Footer containers -->
  <div class="pdf-header">My Header: <?php echo htmlspecialchars(\$docTitleSimple); ?></div>
  <div class="pdf-footer"></div>

  <!-- Content -->
  <main>
    <!-- Title page -->
    <section style="text-align:center; font-size:5em; margin-top:40vh;">
      <strong><?php echo htmlspecialchars(\$docTitleSimple); ?></strong>
    </section>
    <div style="page-break-after:always;"></div>

    <!-- Blank page -->
    <div style="page-break-after:always;">.</div>

    <!-- Title + subtitle + author -->
    <section style="text-align:center; margin-top:30vh;">
      <h1 style="font-size:5em;"><?php echo htmlspecialchars(\$docTitleSimple); ?></h1>
      <h2 style="font-size:3em; margin-top:2em;">
        <?php echo htmlspecialchars(\$pub['subtitle'] ?? ''); ?>
      </h2>
      <p style="font-size:2em; margin-top:2em;">Autor: <?php echo htmlspecialchars(\$docAuthor); ?></p>
    </section>
    <div style="page-break-after:always;"></div>

    <!-- Blank page -->
    <div style="page-break-after:always;">.</div>

    <!-- Dedication -->
    <section style="text-align:center; margin-top:40vh; font-style:italic;">
      <p>“Este libro está dedicado a todos los que aman aprender.”</p>
    </section>
    <div style="page-break-after:always;"></div>

    <!-- Blank page -->
    <div style="page-break-after:always;">.</div>

    <!-- Main content with manual TOC and headings -->
    \$htmlContent
  </main>
</body>
</html>
HTML;

// Generate PDF
equire_once __DIR__ . '/vendor/autoload.php';
use Knp\Snappy\Pdf;

\$snappy = new Pdf('/usr/bin/wkhtmltopdf');
\$snappy->setOption('enable-local-file-access', true);

// Dynamic page size if provided
if (!empty(\$pub['size_h']) && !empty(\$pub['size_v'])) {
    \$snappy->setOption('page-width',  \$pub['size_h'] . 'in');
    \$snappy->setOption('page-height', \$pub['size_v'] . 'in');
}

// Generate from single HTML string
try {
    \$pdfOutput = \$snappy->getOutputFromHtml(\$html);
} catch (Exception \$e) {
    die('PDF generation error: ' . \$e->getMessage());
}

// Save PDF file
\$pdfDir  = __DIR__ . '/pdf';
if (!is_dir(\$pdfDir)) mkdir(\$pdfDir, 0777, true);
\$pdfFile = \$pdfDir . '/publication_' . \$id . '.pdf';

// Optional encryption with qpdf
if (!empty(\$pub['pdf_password'])) {
    \$temp = \$pdfDir . '/temp_' . \$id . '.pdf';
    file_put_contents(\$temp, \$pdfOutput);
    \$pw = escapeshellarg(\$pub['pdf_password']);
    exec("qpdf --encrypt \$pw \$pw 256 -- \$temp " . escapeshellarg(\$pdfFile), \$out, \$ret);
    if (\$ret !== 0) {
        file_put_contents(\$pdfFile, \$pdfOutput);
    }
    @unlink(\$temp);
} else {
    file_put_contents(\$pdfFile, \$pdfOutput);
}

// Redirect to PDF
header('Location: pdf/publication_' . \$id . '.pdf');
exit;

/**
 * Manual TOC & Heading numbering function (unchanged)
 */
function addNumberingAndTOC(\$html) {
    \$pattern = '/<h([1-6])>(.*?)<\/h\1>/i';
    preg_match_all(\$pattern, \$html, \$matches, PREG_SET_ORDER);
    if (!\$matches) return \$html;
    \$numbering = [0,0,0,0,0,0];
    \$tocItems   = [];
    foreach (\$matches as \$m) {
        \$lvl  = (int)\$m[1];
        \$text = strip_tags(\$m[2]);
        \$numbering[\$lvl-1]++;
        for (\$i = \$lvl; \$i < 6; \$i++) { \$numbering[\$i] = 0; }
        \$label = implode('.', array_slice(\$numbering, 0, \$lvl)) . '. ';
        \$id    = 'heading-' . md5(\$text . rand());
        \$html  = str_replace(\$m[0], sprintf('<h%d id="%s">%s%s</h%d>',
                        \$lvl, \$id, \$label, \$text, \$lvl), \$html);
        \$tocItems[] = ['level'=>\$lvl,'id'=>\$id,'text'=>\$label.\$text];
    }
    \$tocHtml = '<div class="table-of-contents"><h2>Índice</h2><ul>';
    foreach (\$tocItems as \$item) {
        \$indent = (\$item['level']-1) * 20;
        \$tocHtml .= sprintf('<li style="margin-left:%dpx;"><a href="#%s">%s</a></li>',
            \$indent, htmlspecialchars(\$item['id']), htmlspecialchars(\$item['text']));
    }
    \$tocHtml .= '</ul></div>';
    return \$tocHtml . "\n\n" . \$html;
}

