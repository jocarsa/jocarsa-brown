<?php
// -------------------------------------------------
// FUNCIONES DE LOGIN
// -------------------------------------------------
function login($username, $password, $db) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND password = :password LIMIT 1");
    $stmt->execute([
        ':username' => $username,
        ':password' => $password  // En producción usar password_verify
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['nombre']    = $user['nombre'];
        return true;
    }
    return false;
}
?>
