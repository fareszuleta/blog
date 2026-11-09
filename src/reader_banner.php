<?php
declare(strict_types=1);

function has_reader_banner(): bool
{
    return (bool) db()->query('SELECT 1 FROM reader_banner WHERE id = 1')->fetchColumn();
}

function serve_reader_banner(): void
{
    header('Cache-Control: no-store');
    $row = db()->query('SELECT mime, content FROM reader_banner WHERE id = 1')->fetch();
    if (!$row) {
        http_response_code(404);
        return;
    }
    header('Content-Type: ' . $row['mime']);
    echo $row['content'];
}

function update_reader_banner(): never
{
    require_admin();
    $dashboard = url(['r' => 'admin']);
    if (isset($_POST['remove'])) {
        db()->exec('DELETE FROM reader_banner WHERE id = 1');
        flash('Sign-in banner removed.');
        redirect($dashboard);
    }
    $upload = $_FILES['banner'] ?? [];
    $data = false;
    if (($upload['error'] ?? null) === UPLOAD_ERR_OK && $upload['size'] <= 2097152 && is_uploaded_file($upload['tmp_name'])) {
        $data = file_get_contents($upload['tmp_name']);
    }
    $info = $data ? @getimagesizefromstring($data) : false;
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) || $info[0] * $info[1] > 20000000) {
        flash('Choose a PNG, JPEG or WebP image up to 2 MiB and 20 megapixels.');
        redirect($dashboard);
    }
    $write = db()->prepare('INSERT INTO reader_banner (id, mime, content) VALUES (1, ?, ?)
        ON CONFLICT(id) DO UPDATE SET mime = excluded.mime, content = excluded.content');
    $write->bindValue(1, $info['mime']);
    $write->bindValue(2, $data, PDO::PARAM_LOB);
    $write->execute();
    flash('Sign-in banner saved.');
    redirect($dashboard);
}
