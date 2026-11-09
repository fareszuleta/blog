<?php
declare(strict_types=1);

function reader_id(): ?int
{
    static $id = false;
    if ($id === false) {
        $token = $_COOKIE['reader_session'] ?? '';
        $id = null;
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $query = db()->prepare('SELECT reader_id FROM reader_sessions WHERE digest = ? AND expires > ?');
            $query->execute([hash('sha256', $token), time()]);
            $found = $query->fetchColumn();
            if ($found !== false) $id = (int) $found;
        }
    }
    return $id;
}

function reader_cookie(string $token, int $expires): void
{
    setcookie('reader_session', $token, [
        'expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    ]);
}

function reader_action(string $action): void
{
    $skill = (string) ($_POST['skill'] ?? '');
    if ($action === 'reader_logout') {
        unset($_SESSION['google_link'], $_SESSION['google_flow']);
        db()->prepare('DELETE FROM reader_sessions WHERE digest = ?')
            ->execute([hash('sha256', (string) ($_COOKIE['reader_session'] ?? ''))]);
        reader_cookie('', 1);
        redirect(url(['r' => 'skills']));
    }
    $register = $action === 'reader_register';
    $back = url(['r' => 'reader', 'skill' => $skill, 'register' => $register ? '1' : '0']);
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || strlen($password) > 72
        || str_contains($password, "\0") || ($register && mb_strlen($password) < 12)) {
        flash('Enter a valid email and a password of 12 or more characters (up to 72 bytes).');
        redirect($back);
    }
    if ($register) {
        $insert = db()->prepare('INSERT INTO readers (email, password_hash) VALUES (?, ?) ON CONFLICT(email) DO NOTHING');
        $insert->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
        if (!$insert->rowCount()) {
            flash('Unable to create this account. Try signing in.');
            redirect($back);
        }
        $id = (int) db()->lastInsertId();
    } else {
        $query = db()->prepare('SELECT id, password_hash FROM readers WHERE email = ?');
        $query->execute([$email]);
        $reader = $query->fetch();
        if (!$reader || !password_verify($password, $reader['password_hash'])) {
            sleep(1);
            flash('Incorrect email or password.');
            redirect($back);
        }
        $id = (int) $reader['id'];
        $link = $_SESSION['google_link'] ?? [];
        if (($link['expires'] ?? 0) > time() && $link['id'] === $id) {
            $insert = db()->prepare('INSERT INTO reader_google(subject, reader_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
            $insert->execute([$link['sub'], $id]);
            if (!$insert->rowCount()) flash('Signed in with your password. A Google account is already connected.');
        }
    }
    unset($_SESSION['google_link'], $_SESSION['google_flow']);
    start_reader_session($id);
    redirect(reader_destination($skill));
}

function reader_destination(string $skill): string
{
    return $skill === '' ? url(['r' => 'skills']) : url(['r' => 'skill', 'name' => $skill]);
}

function start_reader_session(int $id): void
{
    $token = bin2hex(random_bytes(32));
    $expires = time() + 30 * 86400;
    db()->prepare('DELETE FROM reader_sessions WHERE expires <= ?')->execute([time()]);
    db()->prepare('INSERT INTO reader_sessions (digest, reader_id, expires) VALUES (?, ?, ?)')
        ->execute([hash('sha256', $token), $id, $expires]);
    session_regenerate_id(true);
    reader_cookie($token, $expires);
}

function page_reader(string $skill, bool $register): void
{
    $title = $register ? 'Create your account' : 'Sign in';
    render_page($title, flash(), function () use ($title, $skill, $register) { ?>
        <section class="reader-card">
          <h1><?= h($title) ?></h1>
          <p class="reader-destination">Continue to <?= h(SITE_NAME) ?><?= $skill !== '' ? ' · ' . h($skill) : '' ?></p>
          <?php if (google_enabled()): ?>
            <form method="post" action="index.php" class="form" data-google-form>
              <input type="hidden" name="popup" value="">
              <input type="hidden" name="action" value="google_start">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="skill" value="<?= h($skill) ?>">
              <button class="secondary" type="submit">Continue with Google</button>
            </form>
            <div class="reader-divider">or</div>
          <?php endif; ?>
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="<?= $register ? 'reader_register' : 'reader_login' ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="skill" value="<?= h($skill) ?>">
            <label>Email <input name="email" type="email" autocomplete="username" maxlength="254" required></label>
            <label>Password <input name="password" type="password" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>" <?= $register ? 'minlength="12"' : '' ?> required>
              <?php if ($register): ?><small>Use at least 12 characters.</small><?php endif; ?>
            </label>
            <button><?= $register ? 'Create account' : 'Sign in' ?></button>
          </form>
          <a href="<?= h(url(['r' => 'reader', 'skill' => $skill, 'register' => $register ? '0' : '1'])) ?>"><?= $register ? 'Sign in with an existing account' : 'Create a reader account' ?></a>
        </section>
        <script src="reader-login.js?v=5" defer></script>
    <?php }, readerWindow: true);
}
