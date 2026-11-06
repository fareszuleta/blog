<?php
declare(strict_types=1);

// Session cookie hardening: page scripts cannot read it, and it is not sent cross-site.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
session_start();

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/views.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ---------- Actions (POST): always end with a redirect ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    // The home page is static HTML (public/index.html) and has no session, so it
    // cannot carry a CSRF token. The lead form there relies on the honeypot field
    // instead. Every other action still requires a valid CSRF token.
    if ($action !== 'lead' && !csrf_valid()) {
        http_response_code(403);
        exit('Invalid or expired form. Reload the page and try again.');
    }
    handle_action($action);
    redirect(url());
}

// ---------- Views (GET) ----------
$route = (string) ($_GET['r'] ?? 'home');

switch ($route) {
    case 'post':
        $post = get_post_by_slug((string) ($_GET['slug'] ?? ''));
        if ($post === null) {
            http_response_code(404);
            page_not_found();
            break;
        }
        page_post($post, get_comments((int) $post['id']), csrf_token(), flash());
        break;

    case 'admin':
        if (!is_admin()) {
            page_login(flash(), csrf_token());
        } else {
            page_admin(get_posts(), get_recent_comments(), get_leads(), csrf_token(), flash());
        }
        break;

    case 'home':
        // The home page is public/index.html now; index.php never renders it.
        redirect('/');

    default:
        http_response_code(404);
        page_not_found();
}

// ---------- Action logic ----------
function handle_action(string $action): void
{
    switch ($action) {
        case 'comment':
            $slug = (string) ($_POST['slug'] ?? '');
            $post = get_post_by_slug($slug);
            if ($post === null) {
                redirect(url());
            }
            // If the honeypot field has content, it's a bot: discard silently.
            if (trim((string) ($_POST['hp_field'] ?? '')) !== '') {
                redirect(url(['r' => 'post', 'slug' => $slug]));
            }

            $author = trim((string) ($_POST['author'] ?? ''));
            $body   = trim((string) ($_POST['body'] ?? ''));

            if ($author === '' || mb_strlen($author) > 80 || $body === '' || mb_strlen($body) > 2000) {
                flash('Check your name (max 80 characters) and comment (max 2000 characters).');
            } else {
                add_comment((int) $post['id'], $author, $body);
                flash('Thanks! Your comment was posted.');
            }
            redirect(url(['r' => 'post', 'slug' => $slug]));

        case 'lead':
            // Email capture (home and posts). Same honeypot as comments.
            if (trim((string) ($_POST['hp_field'] ?? '')) !== '') {
                redirect(url());
            }
            $email  = strtolower(trim((string) ($_POST['email'] ?? '')));
            $source = ($_POST['source'] ?? '') === 'post' ? 'post' : 'home';
            if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                flash('Enter a valid email, for example you@company.com.');
            } else {
                add_lead($email, $source);
                flash('Done. Your email is registered.');
            }
            $slug = (string) ($_POST['slug'] ?? '');
            redirect($source === 'post' && $slug !== '' ? url(['r' => 'post', 'slug' => $slug]) : url());

        case 'login':
            if (check_password((string) ($_POST['password'] ?? ''))) {
                session_regenerate_id(true);
                $_SESSION['admin'] = true;
                flash('Logged in.');
                redirect(url(['r' => 'admin']));
            }
            sleep(1); // slows down brute-force attempts
            flash(getenv('ADMIN_PASSWORD')
                ? 'Wrong password.'
                : 'Login is disabled: set the ADMIN_PASSWORD variable.');
            redirect(url(['r' => 'admin']));

        case 'logout':
            $_SESSION = [];
            session_destroy();
            redirect(url());

        case 'post_create':
            require_admin();
            $title   = trim((string) ($_POST['title'] ?? ''));
            $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
            $body    = trim((string) ($_POST['body'] ?? ''));

            if ($title === '' || $excerpt === '' || $body === ''
                || mb_strlen($title) > 150 || mb_strlen($excerpt) > 300 || mb_strlen($body) > 20000) {
                flash('Fill in every field within the length limits.');
            } else {
                create_post($title, $excerpt, $body);
                flash('Post published.');
            }
            redirect(url(['r' => 'admin']));

        case 'post_delete':
            require_admin();
            delete_post((int) ($_POST['id'] ?? 0));
            flash('Post deleted.');
            redirect(url(['r' => 'admin']));

        case 'comment_delete':
            require_admin();
            delete_comment((int) ($_POST['id'] ?? 0));
            flash('Comment deleted.');
            redirect(url(['r' => 'admin']));

        default:
            return;
    }
}
