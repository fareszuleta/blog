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
        db()->prepare('DELETE FROM reader_sessions WHERE digest = ?')
            ->execute([hash('sha256', (string) ($_COOKIE['reader_session'] ?? ''))]);
        reader_cookie('', 1);
        redirect(url(['r' => 'skills']));
    }
    $register = $action === 'reader_register';
    $back = url(['r' => 'reader', 'skill' => $skill, 'register' => $register ? '1' : '0', 'window' => (int) (($_POST['window'] ?? '') === '1'), 'entry' => '1']);
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
    }
    $token = bin2hex(random_bytes(32));
    $expires = time() + 30 * 86400;
    db()->prepare('DELETE FROM reader_sessions WHERE expires <= ?')->execute([time()]);
    db()->prepare('INSERT INTO reader_sessions (digest, reader_id, expires) VALUES (?, ?, ?)')
        ->execute([hash('sha256', $token), $id, $expires]);
    session_regenerate_id(true);
    reader_cookie($token, $expires);
    if (($_POST['remember_email'] ?? '') === '1') {
        $emails = array_slice(array_unique(array_merge([$email], remembered_reader_emails())), 0, 4);
        setcookie('uptime_emails', json_encode(array_values($emails)), [
            'expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        ]);
    }
    $destination = $skill === '' ? url(['r' => 'skills']) : url(['r' => 'skill', 'name' => $skill]);
    if (($_POST['window'] ?? '') === '1') {
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="en"><title>Signed in to Uptime</title><p>Signed in to Uptime.</p><a data-reader-done href="' . h($destination) . '">Continue</a><script src="reader-window.js"></script></html>';
        exit;
    }
    redirect($destination);
}

function remembered_reader_emails(): array
{
    $raw = $_COOKIE['uptime_emails'] ?? '';
    $emails = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($emails)) return [];
    return array_slice(array_values(array_filter($emails, static fn($email) =>
        is_string($email) && strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL))), 0, 4);
}

function page_reader(string $skill, bool $register): void
{
    $window = ($_GET['window'] ?? '') === '1';
    $base = ['r' => 'reader', 'skill' => $skill, 'window' => (int) $window];
    $emails = $window ? remembered_reader_emails() : [];
    $selected = filter_var($_GET['account'] ?? null, FILTER_VALIDATE_INT);
    $email = $selected !== false ? ($emails[$selected] ?? '') : '';
    $chooser = $window && !$register && $email === '' && ($_GET['entry'] ?? '') !== '1';
    $title = $chooser ? 'Choose an Uptime account' : ($register ? 'Create your Uptime account' : 'Sign in to Uptime');
    render_page($title, flash(), function () use ($title, $base, $window, $register, $chooser, $skill, $emails, $email) { ?>
      <section class="reader-card">
        <h1><?= h($title) ?></h1>
        <?php if ($skill !== ''): ?><p class="reader-destination">Continue to <?= h($skill) ?></p><?php endif; ?>
        <?php if ($chooser): ?>
          <nav class="account-list" aria-label="Uptime accounts">
            <?php foreach ($emails as $i => $address): ?>
              <a href="<?= h(url($base + ['account' => $i])) ?>"><span aria-hidden="true"><?= h(strtoupper(substr($address, 0, 1))) ?></span><?= h($address) ?></a>
            <?php endforeach; ?>
            <a href="<?= h(url($base + ['entry' => '1'])) ?>"><span aria-hidden="true">+</span><?= $emails ? 'Use another Uptime account' : 'Use an Uptime account' ?></a>
          </nav>
        <?php else: ?>
          <?php if (!$window): ?>
            <a class="reader-launch" data-reader-launch href="<?= h(url(array_replace($base, ['window' => 1]))) ?>">Continue with Uptime</a>
            <div class="reader-divider">or</div>
          <?php endif; ?>
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="<?= $register ? 'reader_register' : 'reader_login' ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="window" value="<?= (int) $window ?>">
            <input type="hidden" name="skill" value="<?= h($skill) ?>">
            <label>Email <input type="email" name="email" value="<?= h($email) ?>" autocomplete="username" maxlength="254" required></label>
            <label>Uptime password <input type="password" name="password" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>" <?= $register ? 'minlength="12"' : '' ?> required></label>
            <?php if ($register): ?><small>Use at least 12 characters.</small><?php endif; ?>
            <label class="remember-email"><input name="remember_email" type="checkbox" value="1"> Remember this email on this device</label>
            <button><?= $register ? 'Create account' : 'Sign in' ?></button>
          </form>
        <?php endif; ?>
        <a href="<?= h(url($base + ['register' => (int) !$register, 'entry' => 1])) ?>"><?= $register ? 'Sign in with an existing account' : 'Create a reader account' ?></a>
      </section>
      <script src="reader-window.js" defer></script>
    <?php }, readerWindow: $window);
}
