<?php
declare(strict_types=1);

// Session cookie hardening: page scripts cannot read it, and it is not sent cross-site.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
session_start();

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/copy_files.php';
require __DIR__ . '/../src/views.php';
require __DIR__ . '/../src/readers.php';
require __DIR__ . '/../src/google_login.php';
require __DIR__ . '/../src/reader_banner.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ---------- Actions (POST): always end with a redirect ----------
$route = (string) ($_GET['r'] ?? 'home');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $route !== 'clipboard') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_valid()) {
        http_response_code(403);
        exit('Invalid or expired form. Reload the page and try again.');
    }
    handle_action($action);
    redirect(url());
}

// ---------- Views (GET) ----------
switch ($route) {
    case 'google_callback':
        google_callback();
    case 'reader_banner':
        serve_reader_banner();
        break;
    case 'copy_file':
        serve_copy_file((int) ($_GET['id'] ?? 0));
        break;
    case 'clipboard':
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('null');
        }
        $ref = $_POST['ref'] ?? '';
        $ticket = $_POST['ticket'] ?? '';
        if (!is_string($ref) || !is_string($ticket) || !valid_clipboard_ticket($ref, $ticket)) {
            http_response_code(403);
            exit('null');
        }
        $text = null;
        if (preg_match('~^(post|extra|skill)/([1-9][0-9]*)/([a-z_0-9]+)$~D', $ref, $parts)) {
            [, $kind, $id, $key] = $parts;
            if ($kind !== 'post' && reader_id() === null && !is_admin()) {
                http_response_code(401);
                exit('null');
            }
            if ($kind === 'skill') {
                $skill = get_skill_by_id((int) $id);
                $settings = json_decode($skill['copy_settings'] ?? '{}', true);
                $text = $settings[$key]['text'] ?? null;
            } elseif (ctype_digit($key)) {
                $body = $kind === 'post' ? (get_post_by_id((int) $id)['body'] ?? '') : (get_skill_by_id((int) $id)['extra'] ?? '');
                $block = body_parts($body)[(int) $key] ?? [];
                $text = ($_POST['part'] ?? 'custom') === 'command' ? ($block['code'] ?? null) : ($block['custom'] ?? null);
            }
        }
        if ($text === null) http_response_code(404);
        echo json_encode($text, JSON_THROW_ON_ERROR);
        break;
    case 'reader':
        page_reader((string) ($_GET['skill'] ?? ''), ($_GET['register'] ?? '') === '1');
        break;
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
            break;
        }
        $editId = (int) ($_GET['edit'] ?? 0);
        $editPost = $editId > 0 ? get_post_by_id($editId) : null;
        $editSkillId = (int) ($_GET['edit_skill'] ?? 0);
        $editSkill = $editSkillId > 0 ? get_skill_by_id($editSkillId) : null;
        if (($editId > 0 && $editPost === null) || ($editSkillId > 0 && $editSkill === null)) {
            http_response_code(404);
            page_not_found();
        } elseif ($editPost !== null) {
            page_admin_edit($editPost, csrf_token(), flash());
        } elseif ($editSkill !== null) {
            page_admin_edit_skill($editSkill, csrf_token(), flash());
        } else {
            page_admin(get_posts(), get_recent_comments(), get_leads(), get_skills(), csrf_token(), flash());
        }
        break;

    case 'skill':
        if (reader_id() === null && !is_admin()) {
            redirect(url(['r' => 'reader', 'skill' => (string) ($_GET['name'] ?? '')]));
        }
        $skill = get_skill_by_name((string) ($_GET['name'] ?? ''));
        if ($skill === null) {
            http_response_code(404);
            page_not_found();
            break;
        }
        $skillPost = !empty($skill['post_slug']) ? get_post_by_slug((string) $skill['post_slug']) : null;
        page_skill($skill, $skillPost);
        break;

    case 'skills':
        page_skills(get_skills(), flash());
        break;

    case 'home':
        page_home(get_posts(), flash());
        break;

    default:
        http_response_code(404);
        page_not_found();
}

// ---------- Action logic ----------
function handle_action(string $action): void
{
    switch ($action) {
        case 'google_window':
            page_google_window();
            exit;
        case 'google_start':
            google_start();
        case 'reader_banner_update':
            update_reader_banner();
            break;
        case 'copy_file_update':
            update_copy_file();
            break;
        case 'reader_register':
        case 'reader_login':
        case 'reader_logout':
            reader_action($action);
            break;
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
            if ($source === 'post' && $slug !== '') {
                redirect(url(['r' => 'post', 'slug' => $slug]));
            }
            redirect('/');

        case 'login':
            if (editor_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                session_regenerate_id(true);
                $_SESSION['admin'] = true;
                flash('Logged in.');
                redirect(url(['r' => 'admin']));
            }
            sleep(1); // slows down brute-force attempts
            flash('Sign-in failed. Check your editor credentials.');
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

        case 'post_update':
            require_admin();
            $id      = (int) ($_POST['id'] ?? 0);
            $title   = trim((string) ($_POST['title'] ?? ''));
            $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
            $body    = trim((string) ($_POST['body'] ?? ''));
            $cover   = trim((string) ($_POST['cover'] ?? ''));

            if ($id <= 0 || get_post_by_id($id) === null) {
                flash('That note no longer exists.');
                redirect(url(['r' => 'admin']));
            }
            if ($title === '' || $excerpt === '' || $body === ''
                || mb_strlen($title) > 150 || mb_strlen($excerpt) > 300 || mb_strlen($body) > 20000
                || mb_strlen($cover) > 300) {
                flash('Fill in every field within the length limits.');
                redirect(url(['r' => 'admin', 'edit' => (string) $id]));
            }
            update_post($id, $title, $excerpt, $body, $cover !== '' ? $cover : null);
            flash('Note updated.');
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

        case 'skill_create':
            require_admin();
            try {
                $copySettings = submitted_skill_copy_settings($_POST);
            } catch (InvalidArgumentException $error) {
                flash($error->getMessage());
                redirect(url(['r' => 'admin', 'edit_skill' => (string) (int) ($_POST['id'] ?? 0)]));
            }
            $name     = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $desc     = trim((string) ($_POST['description'] ?? ''));
            $command  = trim((string) ($_POST['command'] ?? ''));
            $postSlug = trim((string) ($_POST['post_slug'] ?? ''));
            $about    = trim((string) ($_POST['about'] ?? ''));
            $useWhen  = trim((string) ($_POST['use_when'] ?? ''));
            $example  = trim((string) ($_POST['example'] ?? ''));
            $extra    = trim((string) ($_POST['extra'] ?? ''));

            if ($name === '' || $category === '' || $desc === '' || $command === ''
                || mb_strlen($name) > 80 || mb_strlen($category) > 40
                || mb_strlen($desc) > 400 || mb_strlen($command) > 8000 || mb_strlen($postSlug) > 150
                || mb_strlen($about) > 1000 || mb_strlen($useWhen) > 400 || mb_strlen($example) > 400 || mb_strlen($extra) > 10000) {
                flash('Fill in every field within the length limits.');
            } else {
                create_skill($name, $category, $desc, $command, $postSlug !== '' ? $postSlug : null, $about, $useWhen, $example, $extra, $copySettings);
                flash('Skill added.');
            }
            redirect(url(['r' => 'admin']));

        case 'skill_update':
            require_admin();
            try {
                $copySettings = submitted_skill_copy_settings($_POST);
            } catch (InvalidArgumentException $error) {
                flash($error->getMessage());
                redirect(url(['r' => 'admin', 'edit_skill' => (string) (int) ($_POST['id'] ?? 0)]));
            }
            $id       = (int) ($_POST['id'] ?? 0);
            $name     = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $desc     = trim((string) ($_POST['description'] ?? ''));
            $command  = trim((string) ($_POST['command'] ?? ''));
            $postSlug = trim((string) ($_POST['post_slug'] ?? ''));
            $about    = trim((string) ($_POST['about'] ?? ''));
            $useWhen  = trim((string) ($_POST['use_when'] ?? ''));
            $example  = trim((string) ($_POST['example'] ?? ''));
            $extra    = trim((string) ($_POST['extra'] ?? ''));

            if ($id <= 0 || get_skill_by_id($id) === null) {
                flash('That skill no longer exists.');
                redirect(url(['r' => 'admin']));
            }
            if ($name === '' || $category === '' || $desc === '' || $command === ''
                || mb_strlen($name) > 80 || mb_strlen($category) > 40
                || mb_strlen($desc) > 400 || mb_strlen($command) > 8000 || mb_strlen($postSlug) > 150
                || mb_strlen($about) > 1000 || mb_strlen($useWhen) > 400 || mb_strlen($example) > 400 || mb_strlen($extra) > 10000) {
                flash('Fill in every field within the length limits.');
                redirect(url(['r' => 'admin', 'edit_skill' => (string) $id]));
            }
            update_skill($id, $name, $category, $desc, $command, $postSlug !== '' ? $postSlug : null, $about, $useWhen, $example, $extra, $copySettings);
            flash('Skill updated.');
            redirect(url(['r' => 'admin']));

        case 'skill_delete':
            require_admin();
            delete_skill((int) ($_POST['id'] ?? 0));
            flash('Skill deleted.');
            redirect(url(['r' => 'admin']));

        default:
            return;
    }
}
