<?php
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
                    <input type="text" name="name" id="name" 
                           value="<?php echo htmlspecialchars($state['name']); ?>" required>
                    <button type="submit" name="update_state">Actualizar</button>
                </form>
            </div>
            <?php
        }
?>
