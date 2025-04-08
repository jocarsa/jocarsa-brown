<?php
// Pantalla para editar solo DATOS (sin el content)
$id = $_GET['id'] ?? '';
$stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
$stmt->execute([':id' => $id]);
$pub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pub) {
    echo "<p>No se encontró la publicación con ID = $id</p>";
} else {
    ?>
    <div class="publications-container">
        <h2>Editar Datos (ID: <?php echo $pub['id']; ?>)</h2>
        <?php if(isset($update_message)) {
            echo "<p class='update-message'>".htmlspecialchars($update_message)."</p>";
        } ?>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $pub['id']; ?>">
            <label for="title">Título</label>
            <input type="text" name="title" id="title" 
                   value="<?php echo htmlspecialchars($pub['title']); ?>" required>

            <label for="subtitle">Subtítulo</label>
            <input type="text" name="subtitle" id="subtitle" 
                   value="<?php echo htmlspecialchars($pub['subtitle']); ?>">

            <label for="comment">Comentario corto</label>
            <textarea name="comment" id="comment" rows="3"><?php echo htmlspecialchars($pub['comment']); ?></textarea>

            <label for="state_id">Estado</label>
            <select name="state_id" id="state_id">
                <option value="">-- Seleccionar --</option>
                <?php
                $stmtStates = $db->query("SELECT * FROM states ORDER BY name ASC");
                $allStates  = $stmtStates->fetchAll(PDO::FETCH_ASSOC);
                foreach ($allStates as $st): ?>
                    <option value="<?php echo $st['id']; ?>"
                        <?php if ($st['id'] == $pub['state_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($st['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="category1">Categoría 1</label>
            <input type="text" name="category1" id="category1"
                   value="<?php echo htmlspecialchars($pub['category1']); ?>">

            <label for="category2">Categoría 2</label>
            <input type="text" name="category2" id="category2"
                   value="<?php echo htmlspecialchars($pub['category2']); ?>">

            <label for="category3">Categoría 3</label>
            <input type="text" name="category3" id="category3"
                   value="<?php echo htmlspecialchars($pub['category3']); ?>">

            <label for="pub_date">Fecha de Publicación</label>
            <input type="date" name="pub_date" id="pub_date"
                   value="<?php echo htmlspecialchars($pub['pub_date']); ?>">

            <label for="size_h">Tamaño Horizontal (in)</label>
            <input type="number" step="0.01" name="size_h" id="size_h"
                   value="<?php echo htmlspecialchars($pub['size_h']); ?>">

            <label for="size_v">Tamaño Vertical (in)</label>
            <input type="number" step="0.01" name="size_v" id="size_v"
                   value="<?php echo htmlspecialchars($pub['size_v']); ?>">

            <!-- New field for PDF password -->
            <label for="pdf_password">Contraseña para PDF (opcional)</label>
            <input type="password" name="pdf_password" id="pdf_password" value="<?php echo htmlspecialchars($pub['pdf_password'] ?? ''); ?>">

            <button type="submit" name="update_data_back">
                Guardar y volver al Dashboard
            </button>
            <button type="submit" name="update_data_stay">
                Guardar y permanecer
            </button>
        </form>
    </div>
    <?php
}
?>

