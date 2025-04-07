<?php
// ------------------------------------------------------------------------
// AJAX PROCESS (NO RELOAD) FOR 'update_content_stay'
// ------------------------------------------------------------------------
if ($logged_in && isset($_GET['action'], $_GET['ajax']) 
    && $_GET['action'] === 'edit_content' 
    && $_GET['ajax'] === '1'
) {
    // We expect POST with 'id' and 'content'
    $id      = $_POST['id']      ?? '';
    $content = $_POST['content'] ?? '';

    $stmt = $db->prepare("UPDATE publications SET content = :content WHERE id = :id");
    $stmt->execute([
        ':content' => $content,
        ':id'      => $id
    ]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Contenido actualizado exitosamente (AJAX).']);
    exit;
}

// Actualizar SOLO el contenido (Markdown) in normal POST way (reload)
if (isset($_POST['update_content_back'])) {
    $id      = $_POST['id']      ?? '';
    $content = $_POST['content'] ?? '';

    $stmt = $db->prepare("UPDATE publications
        SET content = :content
        WHERE id = :id");
    $stmt->execute([
        ':content' => $content,
        ':id'      => $id
    ]);
    // After saving, go back to the dashboard
    header("Location: index.php?action=publications");
    exit;
}
?>
