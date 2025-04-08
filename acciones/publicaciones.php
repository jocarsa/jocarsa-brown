<?php
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
                <th>Tamaño (pulgadas)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($publications as $pub_item): ?>
            <tr>
                <td><?php echo htmlspecialchars($pub_item['title']); ?></td>
                <td><?php echo htmlspecialchars($pub_item['subtitle']); ?></td>
                <td><?php echo htmlspecialchars($pub_item['state_name'] ?: ''); ?></td>
                <td>
                    <?php
                    $cats = array_filter([
                        $pub_item['category1'], 
                        $pub_item['category2'], 
                        $pub_item['category3']
                    ]);
                    echo htmlspecialchars(implode(', ', $cats));
                    ?>
                </td>
                <td><?php echo htmlspecialchars($pub_item['pub_date']); ?></td>
                <td><?php echo htmlspecialchars($pub_item['size_h'] . ' x ' . $pub_item['size_v']); ?></td>
                <td>
                    <a href="index.php?action=edit_data&id=<?php echo $pub_item['id']; ?>">
                      Editar datos
                    </a> |
                    <a href="index.php?action=edit_content&id=<?php echo $pub_item['id']; ?>">
                      Editar contenido
                    </a> |
                    <a href="index.php?delete_publication=<?php echo $pub_item['id']; ?>"
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
        
        <!-- New field for PDF password -->
        <label for="pdf_password">Contraseña para PDF (opcional)</label>
        <input type="password" name="pdf_password" id="pdf_password">

        <button type="submit" name="create_publication">Crear Publicación</button>
    </form>
</div>
<?php
?>

