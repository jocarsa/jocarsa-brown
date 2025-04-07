<?php
// -------------------------------------------------
// PROCESAR LOGOUT
// -------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
