<?php
// Crear publicación
if ($logged_in && isset($_POST['create_publication'])) {
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
?>
