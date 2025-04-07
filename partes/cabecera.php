<!-- CABECERA -->
    <div class="header">
        <h1>jocarsa | brown</h1>
        <p>Bienvenido/a, <?php echo htmlspecialchars($_SESSION['nombre']); ?>.</p>
        <nav>
            <a href="index.php?action=publications">Publicaciones</a>
            <a href="index.php?action=states">Estados</a>
            <a class="logout-link" href="?action=logout">Cerrar sesión</a>
        </nav>
    </div>
