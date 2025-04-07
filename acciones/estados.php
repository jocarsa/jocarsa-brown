<?php
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
?>
