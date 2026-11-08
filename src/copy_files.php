<?php
declare(strict_types=1);

function copy_files(string $kind, int $id): array
{
    static $cache = [];
    $column = $kind === 'post' ? 'post_id' : 'skill_id';
    $scope = $column . '/' . $id;
    if (!isset($cache[$scope])) {
        $query = db()->prepare("SELECT id, block_key, filename FROM copy_files WHERE $column = ?");
        $query->execute([$id]);
        $cache[$scope] = array_column($query->fetchAll(), null, 'block_key');
    }
    return $cache[$scope];
}

function with_copy_file(string $html, ?array $file): string
{
    if ($file === null) return $html;
    $link = url(['r' => 'copy_file', 'id' => $file['id']]);
    return '<div data-copy-package>' . $html . '<small>Copy also downloads <a download href="' . h($link)
        . '">' . h($file['filename']) . '</a></small></div>';
}

function copy_file_choices(string $kind, array $owner): array
{
    $choices = $kind === 'skill' ? SKILL_COPY_STEPS : [];
    foreach (body_parts((string) ($kind === 'post' ? $owner['body'] : $owner['extra'])) as $block) {
        if (isset($block['file_key'])) $choices[$block['file_key']] = mb_substr($block['code'], 0, 100);
    }
    return $choices;
}

function copy_file_editor(string $kind, array $owner, string $csrf): void
{
    $id = (int) $owner['id'];
    $files = copy_files($kind, $id);
    $choices = copy_file_choices($kind, $owner);
    foreach ($files as $key => $file) $choices[$key] ??= 'Removed command';
    if (!$choices) return;
    ?>
    <section class="admin-block">
      <h2>Copy button download</h2>
      <form method="post" action="index.php" enctype="multipart/form-data" class="form">
        <input type="hidden" name="action" value="copy_file_update">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="kind" value="<?= h($kind) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <label>Copy button <select name="block_key">
          <?php foreach ($choices as $key => $label): ?>
            <option value="<?= h($key) ?>"><?= h($label . (isset($files[$key]) ? ' — ' . $files[$key]['filename'] : '')) ?></option>
          <?php endforeach; ?>
        </select></label>
        <label>File (up to 2 MiB) <input type="file" name="attachment" required></label>
        <button type="submit">Save file</button>
        <?php if ($files): ?><button type="submit" name="remove" value="1" formnovalidate>Remove selected attachment</button><?php endif; ?>
      </form>
    </section>
    <?php
}

function update_copy_file(): void
{
    require_admin();
    $kind = $_POST['kind'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $owner = match ($kind) {
        'skill' => get_skill_by_id($id),
        'post' => get_post_by_id($id),
        default => null,
    };
    if (!$owner) { http_response_code(404); exit; }
    $column = $kind === 'post' ? 'post_id' : 'skill_id';
    $key = (string) ($_POST['block_key'] ?? '');
    if (isset($_POST['remove'])) {
        db()->prepare("DELETE FROM copy_files WHERE $column = ? AND block_key = ?")->execute([$id, $key]);
        flash('Attachment removed.');
    } else {
        $file = $_FILES['attachment'] ?? [];
        if (!isset(copy_file_choices($kind, $owner)[$key])) {
            flash('Choose a current Copy button.');
        } elseif (($file['error'] ?? null) !== UPLOAD_ERR_OK || $file['size'] > 2097152 || !is_uploaded_file($file['tmp_name'])) {
            flash('Choose a file up to 2 MiB and try again.');
        } else {
            $name = basename(str_replace('\\', '/', $file['name']));
            $name = trim(preg_replace('/[[:cntrl:]]/', '', $name));
            $data = file_get_contents($file['tmp_name']);
            if ($data === false || $name === '' || !mb_check_encoding($name, 'UTF-8')) {
                flash('Could not read the file or its name.');
            } else {
                $save = db()->prepare("INSERT INTO copy_files ($column, block_key, filename, content) VALUES (?, ?, ?, ?)
                    ON CONFLICT ($column, block_key) DO UPDATE SET filename = excluded.filename, content = excluded.content");
                $save->bindValue(1, $id, PDO::PARAM_INT);
                $save->bindValue(2, $key);
                $save->bindValue(3, $name);
                $save->bindValue(4, $data, PDO::PARAM_LOB);
                $save->execute();
                flash('Attachment saved.');
            }
        }
    }
    redirect(url(['r' => 'admin', ($kind === 'post' ? 'edit' : 'edit_skill') => $id]));
}

function serve_copy_file(int $id): void
{
    $canReadSkills = reader_id() !== null || is_admin();
    $read = db()->prepare('SELECT filename, content FROM copy_files WHERE id = ? AND (post_id IS NOT NULL OR ? = 1)');
    $read->bindValue(1, $id, PDO::PARAM_INT);
    $read->bindValue(2, (int) $canReadSkills, PDO::PARAM_INT);
    $read->execute();
    $file = $read->fetch();
    if (!$file) { http_response_code(404); return; }
    header('Cache-Control: private, no-store');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file['filename']));
    echo $file['content'];
}
