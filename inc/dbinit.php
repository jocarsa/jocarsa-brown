<?php
// Nombre del archivo de la base de datos (nueva)
$db_file = __DIR__ . '/../database_v2.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Crear tablas si no existen
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS states (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS publications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        subtitle TEXT,
        comment TEXT,
        content TEXT,
        state_id INTEGER,
        category1 TEXT,
        category2 TEXT,
        category3 TEXT,
        pub_date TEXT,
        size_h REAL,
        size_v REAL,
        pdf_password TEXT,
        FOREIGN KEY (state_id) REFERENCES states(id)
    )");

    // 2. Crear usuario por defecto si no existe
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM users WHERE username = :username");
    $stmt->execute([':username' => 'jocarsa']);
    $userExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userExists['count'] == 0) {
        $insertUser = $db->prepare("INSERT INTO users (nombre, email, username, password)
            VALUES (:nombre, :email, :username, :password)");
        $insertUser->execute([
            ':nombre'   => 'Jose Vicente Carratala',
            ':email'    => 'info@josevicentecarratala.com',
            ':username' => 'jocarsa',
            // ¡En producción usar password_hash!
            ':password' => 'jocarsa'
        ]);
    }

    // 3. Crear algunos estados por defecto si no existen
    $stmt = $db->query("SELECT COUNT(*) as c FROM states");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['c'] == 0) {
        $db->exec("INSERT INTO states (name) VALUES ('En Construcción'), ('Descartadas'), ('Publicadas')");
    }

} catch (PDOException $e) {
    die("Error con la base de datos: " . $e->getMessage());
}
?>

