<?php
declare(strict_types=1);

const GOOGLE_CALLBACK = 'https://closing-notes.onrender.com/index.php?r=google_callback';

function google_enabled(): bool
{
    return (bool) getenv('GOOGLE_CLIENT_ID') && (bool) getenv('GOOGLE_CLIENT_SECRET');
}

function google_start(): never
{
    if (!google_enabled()) { http_response_code(404); exit; }
    $flow = [
        'state' => bin2hex(random_bytes(32)), 'nonce' => bin2hex(random_bytes(32)),
        'verifier' => bin2hex(random_bytes(32)), 'expires' => time() + 600,
        'skill' => (string) ($_POST['skill'] ?? ''),
        'popup' => is_string($_POST['popup'] ?? null) && preg_match('/^[a-f0-9-]{36}$/D', $_POST['popup']) ? $_POST['popup'] : '',
    ];
    unset($_SESSION['google_link']);
    $_SESSION['google_flow'] = $flow;
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => getenv('GOOGLE_CLIENT_ID'), 'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: GOOGLE_CALLBACK,
        'response_type' => 'code', 'scope' => 'openid email', 'state' => $flow['state'], 'nonce' => $flow['nonce'],
        'code_challenge_method' => 'S256',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='),
    ]));
}

function finish_google_flow(string $destination, array $flow): never
{
    if (empty($flow['popup'])) redirect($destination);
    header('Cache-Control: no-store');
    ?>
    <!doctype html><html lang="en"><meta charset="utf-8"><title>Continue to Uptime</title>
    <a href="<?= h($destination) ?>" data-google-result="<?= h($flow['popup']) ?>">Continue to Uptime</a>
    <script src="google-result.js"></script></html>
    <?php
    exit;
}

function google_callback(): never
{
    $flow = $_SESSION['google_flow'] ?? [];
    unset($_SESSION['google_flow'], $_SESSION['google_link']);
    $back = url(['r' => 'reader', 'skill' => $flow['skill'] ?? '']);
    if (!google_enabled() || ($flow['expires'] ?? 0) < time() || !is_string($_GET['state'] ?? null)
        || !hash_equals($flow['state'], $_GET['state']) || !is_string($_GET['code'] ?? null)) {
        flash('Google sign-in was canceled or expired. Try again.');
        finish_google_flow($back, $flow);
    }

    // Only accept an ID token received directly from Google's authenticated TLS token endpoint.
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 10, 'follow_location' => 0,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'client_id' => getenv('GOOGLE_CLIENT_ID'), 'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: GOOGLE_CALLBACK, 'grant_type' => 'authorization_code',
            'code' => $_GET['code'], 'code_verifier' => $flow['verifier'],
        ]),
    ]]);
    $response = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    $token = json_decode($response ?: '{}', true)['id_token'] ?? '';
    $segments = explode('.', $token);
    $claims = json_decode(base64_decode(strtr($segments[1] ?? '', '-_', '+/'), true) ?: '{}', true);
    if (count($segments) !== 3 || !in_array($claims['iss'] ?? '', ['accounts.google.com','https://accounts.google.com'], true)
        || ($claims['aud'] ?? '') !== getenv('GOOGLE_CLIENT_ID') || ($claims['nonce'] ?? '') !== $flow['nonce']
        || ($claims['exp'] ?? 0) <= time() || ($claims['email_verified'] ?? false) !== true
        || !is_string($claims['sub'] ?? null) || $claims['sub'] === '' || !filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        flash('Google sign-in could not be verified. Try again.');
        finish_google_flow($back, $flow);
    }
    $sub = $claims['sub'];
    $email = strtolower($claims['email']);
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $lookup = $pdo->prepare('SELECT reader_id FROM reader_google WHERE subject = ?');
        $lookup->execute([$sub]);
        $id = $lookup->fetchColumn();
        if ($id === false) {
            $existing = $pdo->prepare('SELECT id FROM readers WHERE email = ?');
            $existing->execute([$email]);
            $id = $existing->fetchColumn();
            if ($id !== false) {
                $pdo->exec('COMMIT');
                $_SESSION['google_link'] = ['id' => (int) $id, 'sub' => $sub, 'expires' => time() + 600];
                flash('Enter your existing reader password once to connect this Google account.');
                finish_google_flow($back, $flow);
            }
            $pdo->prepare('INSERT INTO readers(email,password_hash) VALUES (?,?)')->execute([$email, '!']);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO reader_google(subject,reader_id) VALUES (?,?)')->execute([$sub, $id]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
    start_reader_session((int) $id);
    finish_google_flow(reader_destination($flow['skill']), $flow);
}
