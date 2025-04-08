<!-- LOGIN -->
    <div class="login-container">
    <img src="brown.png">
        <h1>jocarsa | brown</h1>
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
