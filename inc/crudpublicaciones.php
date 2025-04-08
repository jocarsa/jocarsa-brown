<?php
// -------------------------------------------------
// CRUD DE PUBLICACIONES
// -------------------------------------------------

// Actualizar SOLO los datos (sin el content)
if (isset($_POST['update_data_back']) || isset($_POST['update_data_stay'])) {
    $id           = $_POST['id']           ?? '';
    $title        = $_POST['title']        ?? 'Sin título';
    $subtitle     = $_POST['subtitle']     ?? '';
    $comment      = $_POST['comment']      ?? '';
    $state_id     = $_POST['state_id']     ?? '';
    $category1    = $_POST['category1']    ?? '';
    $category2    = $_POST['category2']    ?? '';
    $category3    = $_POST['category3']    ?? '';
    $pub_date     = $_POST['pub_date']     ?? '';
    $size_h       = $_POST['size_h']       ?? 0.0;
    $size_v       = $_POST['size_v']       ?? 0.0;
    $pdf_password = $_POST['pdf_password'] ?? '';

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
            size_v = :size_v,
            pdf_password = :pdf_password
        WHERE id = :id");
    $stmt->execute([
        ':title'        => $title,
        ':subtitle'     => $subtitle,
        ':comment'      => $comment,
        ':state_id'     => $state_id,
        ':category1'    => $category1,
        ':category2'    => $category2,
        ':category3'    => $category3,
        ':pub_date'     => $pub_date,
        ':size_h'       => $size_h,
        ':size_v'       => $size_v,
        ':pdf_password' => $pdf_password,
        ':id'           => $id
    ]);
    if (isset($_POST['update_data_back'])) {
        header("Location: index.php?action=publications");
        exit;
    } else {
        // "Guardar y permanecer" but for data screen
        $update_message = "Datos actualizados exitosamente.";
        $action = 'edit_data';
        $stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pub = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

