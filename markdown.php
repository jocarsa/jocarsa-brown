<?php
session_start();

// Ensure the user is logged in; this step mimics the PDF generation security
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

// Retrieve the publication ID from the query string
$id = $_GET['id'] ?? '';
if (!$id) {
    die('No publication id specified.');
}

// Fetch the publication record from the database
$stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
$stmt->execute([':id' => $id]);
$pub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pub) {
    die('Publication not found.');
}

// Generate a timestamp and build document title fields
$timestamp = date("Y-m-d-H-i-s");
$documentTitleSimple = $pub['title'];
$documentAuthor = $_SESSION['nombre'] ?? 'Unknown';

// Build the Markdown content
// Here we include a header with title, optional subtitle, author, and generation date.
// You can adjust the header formatting as needed.
$mdContent  = "# " . $documentTitleSimple . "\n\n";
if (!empty($pub['subtitle'])) {
    $mdContent .= "## " . $pub['subtitle'] . "\n\n";
}
$mdContent .= "Author: " . $documentAuthor . "\n\n";
$mdContent .= "Date: " . $timestamp . "\n\n";
$mdContent .= "---\n\n";
$mdContent .= $pub['content'];  // The raw Markdown content stored in the database

// Ensure the "markdown" folder exists; if not, create it
$markdownDir = __DIR__ . '/markdown';
if (!file_exists($markdownDir)) {
    mkdir($markdownDir, 0777, true);
}

// Build the complete file path and save the Markdown file
$mdFile = $markdownDir . '/publication_' . $id . '.md';
file_put_contents($mdFile, $mdContent);

// Prepare the relative URL for the generated Markdown file
// (Make sure that the "markdown" folder is accessible via your web server)
$relativeUrl = 'markdown/publication_' . $id . '.md';

// Redirect the browser to the Markdown file URL so it can be viewed or downloaded
header("Location: $relativeUrl");
exit;

