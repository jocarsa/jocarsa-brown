<?php
// Eliminar publicación
if ($logged_in && isset($_GET['delete_publication'])) {
    $id = $_GET['delete_publication'] ?? '';
    if ($id) {
        $stmt = $db->prepare("DELETE FROM publications WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
    header("Location: index.php?action=publications");
    exit;
}
?>
