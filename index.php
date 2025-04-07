<?php
/*****************************************************
 * GESTOR DE PUBLICACIONES (LIBROS) V2
 *  - PHP + SQLite
 *  - Login básico
 *  - CRUD de estados
 *  - CRUD de publicaciones (nuevo modelo)
 *  - 2 PANTALLAS DE EDICIÓN:
 *      1) Editar datos
 *      2) Editar contenido (split screen)
 *****************************************************/

session_start();

// Nombre del archivo de la base de datos (nueva)
$db_file = __DIR__ . '/database_v2.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Crear tablas si no existen
    // Tabla de usuarios
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");

    // Tabla de estados
    $db->exec("CREATE TABLE IF NOT EXISTS states (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )");

    // Tabla de publicaciones con un campo 'content' para el texto completo
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

// -------------------------------------------------
// PROCESAR LOGOUT
// -------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// -------------------------------------------------
// PROCESAR LOGIN
// -------------------------------------------------
if (isset($_POST['login_submit'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($username, $password, $db)) {
        header("Location: index.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}

// -------------------------------------------------
// COMPROBAR LOGIN
// -------------------------------------------------
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// -------------------------------------------------
// CRUD DE ESTADOS
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

// -------------------------------------------------
// CRUD DE PUBLICACIONES
// -------------------------------------------------
if ($logged_in) {
    // Crear publicación
    if (isset($_POST['create_publication'])) {
        $title     = $_POST['title']     ?? 'Sin título';
        $subtitle  = $_POST['subtitle']  ?? '';
        $comment   = $_POST['comment']   ?? '';
        $content   = $_POST['content']   ?? '';
        $state_id  = $_POST['state_id']  ?? '';
        $category1 = $_POST['category1'] ?? '';
        $category2 = $_POST['category2'] ?? '';
        $category3 = $_POST['category3'] ?? '';
        $pub_date  = $_POST['pub_date']  ?? '';
        $size_h    = $_POST['size_h']    ?? 0.0;
        $size_v    = $_POST['size_v']    ?? 0.0;

        $stmt = $db->prepare("INSERT INTO publications
            (title, subtitle, comment, content, state_id, category1, category2, category3, pub_date, size_h, size_v)
            VALUES
            (:title, :subtitle, :comment, :content, :state_id, :category1, :category2, :category3, :pub_date, :size_h, :size_v)");
        $stmt->execute([
            ':title'     => $title,
            ':subtitle'  => $subtitle,
            ':comment'   => $comment,
            ':content'   => $content,
            ':state_id'  => $state_id,
            ':category1' => $category1,
            ':category2' => $category2,
            ':category3' => $category3,
            ':pub_date'  => $pub_date,
            ':size_h'    => $size_h,
            ':size_v'    => $size_v
        ]);

        header("Location: index.php?action=publications");
        exit;
    }

    // Actualizar SOLO los datos (sin el content)
    if (isset($_POST['update_data'])) {
        $id        = $_POST['id']        ?? '';
        $title     = $_POST['title']     ?? 'Sin título';
        $subtitle  = $_POST['subtitle']  ?? '';
        $comment   = $_POST['comment']   ?? '';
        $state_id  = $_POST['state_id']  ?? '';
        $category1 = $_POST['category1'] ?? '';
        $category2 = $_POST['category2'] ?? '';
        $category3 = $_POST['category3'] ?? '';
        $pub_date  = $_POST['pub_date']  ?? '';
        $size_h    = $_POST['size_h']    ?? 0.0;
        $size_v    = $_POST['size_v']    ?? 0.0;

        $stmt = $db->prepare("UPDATE publications
            SET title = :title,
                subtitle = :subtitle,
                comment = :comment,
                state_id = :state_id,
                category1 = :category1,
                category2 = :category2,
                category3 = :category3,
                pub_date = :pub_date,
                size_h = :size_h,
                size_v = :size_v
            WHERE id = :id");
        $stmt->execute([
            ':title'     => $title,
            ':subtitle'  => $subtitle,
            ':comment'   => $comment,
            ':state_id'  => $state_id,
            ':category1' => $category1,
            ':category2' => $category2,
            ':category3' => $category3,
            ':pub_date'  => $pub_date,
            ':size_h'    => $size_h,
            ':size_v'    => $size_v,
            ':id'        => $id
        ]);

        header("Location: index.php?action=publications");
        exit;
    }

    // Actualizar SOLO el contenido (Markdown) en split screen
    if (isset($_POST['update_content'])) {
        $id      = $_POST['id']      ?? '';
        $content = $_POST['content'] ?? '';

        $stmt = $db->prepare("UPDATE publications
            SET content = :content
            WHERE id = :id");
        $stmt->execute([
            ':content' => $content,
            ':id'      => $id
        ]);

        header("Location: index.php?action=publications");
        exit;
    }

    // Eliminar publicación
    if (isset($_GET['delete_publication'])) {
        $id = $_GET['delete_publication'] ?? '';
        if ($id) {
            $stmt = $db->prepare("DELETE FROM publications WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }
        header("Location: index.php?action=publications");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestor de Publicaciones - 2 Pantallas de Edición</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php if (!$logged_in): ?>
    <!-- LOGIN -->
    <div class="login-container">
        <h1>Iniciar Sesión</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="username">Usuario</label>
            <input type="text" name="username" id="username" required>

            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" required>

            <button type="submit" name="login_submit">Entrar</button>
        </form>
    </div>
<?php else: ?>
    <!-- CABECERA -->
    <div class="header">
        <h1>Gestor de Publicaciones V2</h1>
        <p>Bienvenido/a, <?php echo htmlspecialchars($_SESSION['nombre']); ?>.</p>
        <nav>
            <a href="index.php?action=publications">Publicaciones</a>
            <a href="index.php?action=states">Estados</a>
            <a class="logout-link" href="?action=logout">Cerrar sesión</a>
        </nav>
    </div>

    <div class="main-content">
    <?php
    // Manejamos ?action=
    $action = $_GET['action'] ?? 'publications';

    // ----------------------------- ESTADOS -----------------------------
    if ($action === 'states') {
        // Listar estados
        $stmt = $db->query("SELECT * FROM states ORDER BY id ASC");
        $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="states-container">
            <h2>Gestión de Estados</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($states as $st): ?>
                    <tr>
                        <td><?php echo $st['id']; ?></td>
                        <td><?php echo htmlspecialchars($st['name']); ?></td>
                        <td>
                            <a href="index.php?action=edit_state&id=<?php echo $st['id']; ?>">Editar</a> |
                            <a href="index.php?delete_state=<?php echo $st['id']; ?>"
                               onclick="return confirm('¿Eliminar este estado?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Crear nuevo Estado</h3>
            <form method="post">
                <label for="name">Nombre del Estado</label>
                <input type="text" name="name" id="name" required>
                <button type="submit" name="create_state">Crear Estado</button>
            </form>
        </div>
        <?php
    }
    elseif ($action === 'edit_state') {
        $id = $_GET['id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM states WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            echo "<p>No se encontró el estado con ID = $id</p>";
        } else {
            ?>
            <div class="states-container">
                <h2>Editar Estado (ID: <?php echo $state['id']; ?>)</h2>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $state['id']; ?>">
                    <label for="name">Nombre del Estado</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($state['name']); ?>" required>
                    <button type="submit" name="update_state">Actualizar</button>
                </form>
            </div>
            <?php
        }
    }

    // ----------------------------- PUBLICACIONES -----------------------------
    elseif ($action === 'publications') {
        // Listar publicaciones
        $stmt = $db->query("SELECT p.*, s.name as state_name
                            FROM publications p
                            LEFT JOIN states s ON p.state_id = s.id
                            ORDER BY p.id DESC");
        $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="publications-container">
            <h2>Publicaciones</h2>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Subtítulo</th>
                        <th>Estado</th>
                        <th>Categorías</th>
                        <th>Fecha Pub.</th>
                        <th>Tamaño (inches)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($publications as $pub): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pub['title']); ?></td>
                        <td><?php echo htmlspecialchars($pub['subtitle']); ?></td>
                        <td><?php echo htmlspecialchars($pub['state_name'] ?: ''); ?></td>
                        <td>
                            <?php
                            $cats = array_filter([$pub['category1'], $pub['category2'], $pub['category3']]);
                            echo htmlspecialchars(implode(', ', $cats));
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($pub['pub_date']); ?></td>
                        <td><?php echo htmlspecialchars($pub['size_h'] . ' x ' . $pub['size_v']); ?></td>
                        <td>
                            <!-- 2 enlaces de edición separados -->
                            <a href="index.php?action=edit_data&id=<?php echo $pub['id']; ?>">Editar datos</a> |
                            <a href="index.php?action=edit_content&id=<?php echo $pub['id']; ?>">Editar contenido</a> |
                            <a href="index.php?delete_publication=<?php echo $pub['id']; ?>"
                               onclick="return confirm('¿Eliminar esta publicación?');">
                               Eliminar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Nueva Publicación</h3>
            <?php
            // Listado de estados para el select
            $stmtStates = $db->query("SELECT * FROM states ORDER BY name ASC");
            $allStates  = $stmtStates->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <form method="post">
                <label for="title">Título</label>
                <input type="text" name="title" id="title" required>

                <label for="subtitle">Subtítulo</label>
                <input type="text" name="subtitle" id="subtitle">

                <label for="comment">Comentario corto</label>
                <textarea name="comment" id="comment" rows="2"></textarea>

                <label for="content">Contenido (Markdown)</label>
                <textarea name="content" id="content" rows="5"></textarea>

                <label for="state_id">Estado</label>
                <select name="state_id" id="state_id">
                    <option value="">-- Seleccionar Estado --</option>
                    <?php foreach ($allStates as $st): ?>
                        <option value="<?php echo $st['id']; ?>">
                            <?php echo htmlspecialchars($st['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="category1">Categoría 1</label>
                <input type="text" name="category1" id="category1">

                <label for="category2">Categoría 2</label>
                <input type="text" name="category2" id="category2">

                <label for="category3">Categoría 3</label>
                <input type="text" name="category3" id="category3">

                <label for="pub_date">Fecha de Publicación</label>
                <input type="date" name="pub_date" id="pub_date">

                <label for="size_h">Tamaño Horizontal (in)</label>
                <input type="number" step="0.01" name="size_h" id="size_h">

                <label for="size_v">Tamaño Vertical (in)</label>
                <input type="number" step="0.01" name="size_v" id="size_v">

                <button type="submit" name="create_publication">Crear Publicación</button>
            </form>
        </div>
        <?php
    }
    elseif ($action === 'edit_data') {
        // Pantalla para editar solo DATOS (sin el content)
        $id = $_GET['id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pub) {
            echo "<p>No se encontró la publicación con ID = $id</p>";
        } else {
            // Cargar estados para el select
            $stmtStates = $db->query("SELECT * FROM states ORDER BY name ASC");
            $allStates  = $stmtStates->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="publications-container">
                <h2>Editar Datos (ID: <?php echo $pub['id']; ?>)</h2>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $pub['id']; ?>">

                    <label for="title">Título</label>
                    <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($pub['title']); ?>" required>

                    <label for="subtitle">Subtítulo</label>
                    <input type="text" name="subtitle" id="subtitle" value="<?php echo htmlspecialchars($pub['subtitle']); ?>">

                    <label for="comment">Comentario corto</label>
                    <textarea name="comment" id="comment" rows="3"><?php echo htmlspecialchars($pub['comment']); ?></textarea>

                    <label for="state_id">Estado</label>
                    <select name="state_id" id="state_id">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($allStates as $st): ?>
                            <option value="<?php echo $st['id']; ?>"
                                <?php if ($st['id'] == $pub['state_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($st['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="category1">Categoría 1</label>
                    <input type="text" name="category1" id="category1" value="<?php echo htmlspecialchars($pub['category1']); ?>">

                    <label for="category2">Categoría 2</label>
                    <input type="text" name="category2" id="category2" value="<?php echo htmlspecialchars($pub['category2']); ?>">

                    <label for="category3">Categoría 3</label>
                    <input type="text" name="category3" id="category3" value="<?php echo htmlspecialchars($pub['category3']); ?>">

                    <label for="pub_date">Fecha de Publicación</label>
                    <input type="date" name="pub_date" id="pub_date" value="<?php echo htmlspecialchars($pub['pub_date']); ?>">

                    <label for="size_h">Tamaño Horizontal (in)</label>
                    <input type="number" step="0.01" name="size_h" id="size_h" value="<?php echo htmlspecialchars($pub['size_h']); ?>">

                    <label for="size_v">Tamaño Vertical (in)</label>
                    <input type="number" step="0.01" name="size_v" id="size_v" value="<?php echo htmlspecialchars($pub['size_v']); ?>">

                    <button type="submit" name="update_data">Actualizar Datos</button>
                </form>
            </div>
            <?php
        }
    }
    elseif ($action === 'edit_content') {
        // Pantalla para editar SOLO el contenido en Markdown (split screen)
        $id = $_GET['id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pub) {
            echo "<p>No se encontró la publicación con ID = $id</p>";
        } else {
            ?>
            <div class="publications-container">
                <h2>Editar Contenido (ID: <?php echo $pub['id']; ?>)</h2>
                <form method="post" class="edit-form-markdown">
                    <input type="hidden" name="id" value="<?php echo $pub['id']; ?>">

                    <!-- Zona de split screen -->
                    <div class="markdown-container">
                        <!-- Columna izquierda: textarea con Markdown -->
                        <div class="markdown-editor">
                            <label for="content">Contenido (Markdown)</label>
                            <textarea name="content" id="content" rows="20"><?php
                                echo htmlspecialchars($pub['content']);
                            ?></textarea>
                        </div>

                        <!-- Columna derecha: Vista previa -->
                        <div class="markdown-preview" id="markdownPreview">
                            <!-- Aquí se renderiza en tiempo real con JS -->
                        </div>
                    </div>

                    <button type="submit" name="update_content">Actualizar Contenido</button>
                </form>
            </div>

            <!-- Script para renderizar Markdown en tiempo real (simplificado) -->
            <script>
            (function(){
                function simpleMarkdownParser(md) {
                    if (!md) return "";

                    // Escapar HTML básico
                    md = md
                      .replace(/&/g, "&amp;")
                      .replace(/</g, "&lt;")
                      .replace(/>/g, "&gt;");

                    // Encabezados (#, ##, ...)
                    md = md.replace(/^###### (.*$)/gim, '<h6>$1</h6>');
                    md = md.replace(/^##### (.*$)/gim, '<h5>$1</h5>');
                    md = md.replace(/^#### (.*$)/gim, '<h4>$1</h4>');
                    md = md.replace(/^### (.*$)/gim, '<h3>$1</h3>');
                    md = md.replace(/^## (.*$)/gim, '<h2>$1</h2>');
                    md = md.replace(/^# (.*$)/gim, '<h1>$1</h1>');

                    // Negrita
                    md = md.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
                    // Cursiva
                    md = md.replace(/\*(.*?)\*/gim, '<em>$1</em>');
                    // Enlaces [texto](url)
                    md = md.replace(/\[(.*?)\]\((.*?)\)/gim, "<a href='$2' target='_blank'>$1</a>");

                    // Saltos de línea
                    md = md.replace(/\n$/g, '<br>');
                    md = md.replace(/\n/g, '<br>');

                    return md.trim();
                }

                const textarea = document.getElementById('content');
                const preview  = document.getElementById('markdownPreview');

                function renderPreview() {
                    preview.innerHTML = simpleMarkdownParser(textarea.value);
                }

                // Actualizar la vista previa en cada cambio
                textarea.addEventListener('input', renderPreview);

                // Render inicial al cargar la página
                renderPreview();
            })();
            </script>
            <?php
        }
    }
    ?>
    </div> <!-- end .main-content -->
<?php endif; ?>

</body>
</html>

