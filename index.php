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

include "inc/dbinit.php";

include "funciones/login.php";
include "funciones/logout.php";

include "inc/procesarlogin.php";
include "inc/comprobarlogin.php";


include "inc/crudpublicaciones.php";

include "inc/actualizarcontenido.php";

include "inc/crearpublicacion.php";
include "inc/eliminarpublicacion.php";
?>
<?php 
	include "partes/cabeza.php";
?>

<?php if (!$logged_in): ?>
    <?php 
		include "partes/login.php";
	?>
<?php else: ?>
    
    <?php 
		include "partes/cabecera.php";
	?>
    <div class="main-content">
    <?php
    // Manejamos ?action=
    $action = $action ?? ($_GET['action'] ?? 'publications');

    // ----------------------------- ESTADOS -----------------------------
    if ($action === 'states') {
        include "acciones/estados.php";
    }
    elseif ($action === 'edit_state') {
        include "acciones/editarestado.php";
    }

    // ----------------------------- PUBLICACIONES -----------------------------
    elseif ($action === 'publications') {
        include "acciones/publicaciones.php";
    }
    elseif ($action === 'edit_data') {
        include "acciones/editardatos.php";
    }
    elseif ($action === 'edit_content') {
        include "acciones/editarcontenido.php";
    }
    ?>
    </div> <!-- end .main-content -->
<?php endif; ?>

</body>
</html>

