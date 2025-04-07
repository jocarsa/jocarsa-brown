<?php
// -------------------------------------------------
// COMPROBAR LOGIN
// -------------------------------------------------
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// -------------------------------------------------
// CRUD DE ESTADOS (solo si está logueado)
// -------------------------------------------------
if ($logged_in) {
    // Crear estado
    if (isset($_POST['create_state'])) {
        $name = $_POST['name'] ?? '';
        if ($name) {
            $stmt = $db->prepare("INSERT INTO states (name) VALUES (:name)");
            $stmt->execute([':name' => $name]);
        }
        header("Location: index.php?action=states");
        exit;
    }

    // Actualizar estado
    if (isset($_POST['update_state'])) {
        $id   = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        if ($id && $name) {
            $stmt = $db->prepare("UPDATE states SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $name, ':id' => $id]);
        }
        header("Location: index.php?action=states");
        exit;
    }

    // Eliminar estado
    if (isset($_GET['delete_state'])) {
        $id = $_GET['delete_state'] ?? '';
        if ($id) {
            $stmt = $db->prepare("DELETE FROM states WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }
        header("Location: index.php?action=states");
        exit;
    }
}
?>
