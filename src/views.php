<?php
declare(strict_types=1);

/** Wraps a page with header, footer and flash message. $body prints the content. */
function render_page(string $title, ?string $flash, callable $body, bool $wide = false): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · <?= h(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400&family=JetBrains+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="style.css">
</head>
<body data-reader="<?= h((string) (reader_id() ?? (is_admin() ? 'editor' : 'guest'))) ?>"<?= $wide ? ' class="wide"' : '' ?>>
<header class="site">
  <a class="brand" href="<?= h(url()) ?>"><?= h(SITE_NAME) ?></a>
  <nav>
    <a href="<?= h(url()) ?>#notes">Notes</a>
    <a href="<?= h(url(['r' => 'skills'])) ?>">Skills</a>
    <?php if (reader_id() !== null): ?>
      <form method="post" action="index.php">
        <input type="hidden" name="action" value="reader_logout">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="secondary">Sign out</button>
      </form>
    <?php elseif (!is_admin()): ?>
      <a href="<?= h(url(['r' => 'reader'])) ?>">Reader sign in</a>
    <?php endif; ?>
    <a href="<?= h(url(['r' => 'admin'])) ?>"><?= is_admin() ? 'Dashboard' : 'Editor login' ?></a>
  </nav>
</header>
<main>
  <?php if ($flash !== null): ?>
    <p class="flash" role="status"><?= h($flash) ?></p>
  <?php endif; ?>
  <?php $body(); ?>
</main>
<footer class="site">
  <span><?= h(SITE_NAME) ?> <?= date('Y') ?></span>
  <span>Field notes on the systems you run</span>
</footer>
<script src="clipboard.js?v=7" defer></script>
</body>
</html>
<?php
}

/** Email capture form. $source is where it lives: 'home' or 'post'. */
function lead_form(string $source, string $slug = ''): void
{
    static $n = 0;
    $id = 'email-' . (++$n);
    ?>
    <form method="post" action="index.php" class="lead-form">
      <input type="hidden" name="action" value="lead">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="source" value="<?= h($source) ?>">
      <input type="hidden" name="slug" value="<?= h($slug) ?>">
      <div class="hp" aria-hidden="true">
        <label>Leave this field empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off"></label>
      </div>
      <label class="sr-only" for="<?= h($id) ?>">Work email</label>
      <input id="<?= h($id) ?>" type="email" name="email" required maxlength="254"
             placeholder="you@example.com" autocomplete="email">
      <button type="submit">Subscribe</button>
    </form>
    <?php
}

function page_not_found(): void
{
    render_page('Not found', null, function () { ?>
        <section class="narrow">
          <h1>This page doesn't exist.</h1>
          <p><a href="<?= h(url()) ?>">Back to the home page</a></p>
        </section>
    <?php });
}

function page_post(array $post, array $comments, string $csrf, ?string $flash): void
{
    render_page($post['title'], $flash, function () use ($post, $comments, $csrf) { ?>
        <p class="back"><a href="<?= h(url()) ?>#notes">All notes</a></p>

        <article class="article">
          <?php if (!empty($post['cover'])): ?>
            <figure class="article-cover">
              <img src="<?= h($post['cover']) ?>" width="800" height="500" alt="">
            </figure>
          <?php endif; ?>
          <header>
            <h1><?= h($post['title']) ?></h1>
            <p class="dek"><?= h($post['excerpt']) ?></p>
            <p class="note-meta">
              <time datetime="<?= h($post['created_at']) ?>"><?= h(fmt_date($post['created_at'])) ?></time>
              <span><?= reading_minutes(mb_strlen($post['body'])) ?> min read</span>
            </p>
          </header>
          <?php $linkedSkill = get_skill_by_post_slug((string) $post['slug']); ?>
          <?php if ($linkedSkill !== null && (reader_id() !== null || is_admin())): ?>
            <?php skill_guide($linkedSkill); ?>
            <h2 class="guide-sub">About this skill</h2>
          <?php elseif ($linkedSkill !== null): ?>
            <p><a href="<?= h(url(['r' => 'skill', 'name' => $linkedSkill['name']])) ?>">Sign in to see the install steps</a></p>
          <?php endif; ?>
          <div class="prose"><?= render_body((string) $post['body'], 'post/' . $post['id'], $post['slug'] === 'install-codex-cli') ?></div>
        </article>

        <aside class="inline-cta" aria-labelledby="inline-title">
          <h2 id="inline-title">New entries by email</h2>
          <p>One note when something's worth writing down. No spam, unsubscribe any time.</p>
          <?php lead_form('post', (string) $post['slug']); ?>
        </aside>

        <section class="comments" aria-labelledby="comments-title">
          <h2 id="comments-title">Comments (<?= count($comments) ?>)</h2>

          <?php if (!$comments): ?>
            <p class="muted">Be the first to comment.</p>
          <?php endif; ?>

          <?php foreach ($comments as $c): ?>
            <div class="comment">
              <div class="note-meta">
                <strong><?= comment_author($c['author']) ?></strong>
                <time datetime="<?= h($c['created_at']) ?>"><?= h(date('Y-m-d H:i', strtotime($c['created_at']))) ?></time>
              </div>
              <p><?= comment_text($c['body']) ?></p>
            </div>
          <?php endforeach; ?>

          <form method="post" action="index.php" class="form comment-form">
            <h3>Leave a comment</h3>
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="slug" value="<?= h($post['slug']) ?>">
            <div class="hp" aria-hidden="true">
              <label>Leave this field empty <input type="text" name="hp_field" tabindex="-1" autocomplete="off"></label>
            </div>
            <label>Name
              <input type="text" name="author" required maxlength="80">
            </label>
            <label>Comment
              <textarea name="body" required maxlength="2000" rows="4"></textarea>
            </label>
            <button type="submit">Post comment</button>
          </form>
        </section>
    <?php });
}

function page_login(?string $flash, string $csrf): void
{
    render_page('Admin', $flash, function () use ($csrf) { ?>
        <section class="narrow">
          <h1>Sign in to the dashboard</h1>
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label>Editor email <small class="hint">Leave blank for the shared admin login.</small><input type="email" name="email" autocomplete="username"></label>
            <label>Password
              <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit">Sign in</button>
          </form>
        </section>
    <?php });
}

function page_admin(array $posts, array $comments, array $leads, array $skills, string $csrf, ?string $flash): void
{
    render_page('Dashboard', $flash, function () use ($posts, $comments, $leads, $skills, $csrf) { ?>
        <div class="admin-top">
          <h1>Dashboard</h1>
          <form method="post" action="index.php">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <button type="submit" class="secondary">Sign out</button>
          </form>
        </div>

        <section class="admin-block">
          <h2>New note</h2>
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="post_create">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label>Title <input type="text" name="title" required maxlength="150"></label>
            <label>Summary <input type="text" name="excerpt" required maxlength="300"></label>
            <label>Content <small class="hint">For a Copy button, put the command between three-backtick lines. To customize the clipboard, open with <code>```custom-copy</code>, enter the visible command, add <code>---copy---</code> on its own line, then the text to copy. Close with three backticks. Use <code>```alternating-copy</code> to copy the command first and the custom text second.</small><textarea name="body" required maxlength="20000" rows="10"></textarea></label>
            <button type="submit">Publish note</button>
          </form>
        </section>

        <section class="admin-block">
          <h2>Notes (<?= count($posts) ?>)</h2>
          <table>
            <thead><tr><th>Title</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
              <tr>
                <td><a href="<?= h(url(['r' => 'post', 'slug' => $p['slug']])) ?>"><?= h($p['title']) ?></a></td>
                <td><?= h(fmt_date($p['created_at'])) ?></td>
                <td class="admin-row-actions">
                  <a class="secondary" href="<?= h(url(['r' => 'admin', 'edit' => (string) $p['id']])) ?>">Edit</a>
                  <form method="post" action="index.php" onsubmit="return confirm('Delete this note and its comments?');">
                    <input type="hidden" name="action" value="post_delete">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <section class="admin-block">
          <h2>New skill</h2>
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="skill_create">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label>Name <input type="text" name="name" required maxlength="80" placeholder="pdf-tables"></label>
            <label>Category <input type="text" name="category" required maxlength="40" placeholder="documents"></label>
            <label>Description <input type="text" name="description" required maxlength="400"></label>
            <label>Install command <small class="hint">Shown in the guide and Quick install. Configure its Copy behavior below.</small><textarea name="command" required maxlength="8000" rows="4" spellcheck="false"></textarea></label>
            <label>Linked note slug (optional) <input type="text" name="post_slug" maxlength="150" placeholder="pdf-tables-and-why-ocr-is-the-wrong-tool"></label>
            <label>What it does <textarea name="about" maxlength="1000" rows="4"></textarea></label>
            <label>It runs when <input type="text" name="use_when" maxlength="400" value=""></label>
            <label>Example prompt <input type="text" name="example" maxlength="400" value=""></label>
            <label>Extra content (optional; supports ``` code blocks and &lt;!-- comments --&gt;) <textarea name="extra" maxlength="10000" rows="8"></textarea></label>
            <?php skill_copy_fields(); ?>
            <button type="submit">Add skill</button>
          </form>
        </section>

        <section class="admin-block">
          <h2>Skills (<?= count($skills) ?>)</h2>
          <?php if (!$skills): ?><p class="muted">No skills yet.</p><?php endif; ?>
          <?php if ($skills): ?>
          <table>
            <thead><tr><th>Name</th><th>Category</th><th>Linked note</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($skills as $s): ?>
              <tr>
                <td><?= h($s['name']) ?></td>
                <td><?= h($s['category']) ?></td>
                <td>
                  <?php if (!empty($s['post_slug']) && get_post_by_slug((string) $s['post_slug']) !== null): ?>
                    <a href="<?= h(url(['r' => 'post', 'slug' => (string) $s['post_slug']])) ?>">note</a>
                  <?php else: ?>
                    <span class="muted">none</span>
                  <?php endif; ?>
                </td>
                <td class="admin-row-actions">
                  <a class="secondary" href="<?= h(url(['r' => 'admin', 'edit_skill' => (string) $s['id']])) ?>">Edit</a>
                  <form method="post" action="index.php" onsubmit="return confirm('Delete this skill?');">
                    <input type="hidden" name="action" value="skill_delete">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </section>

        <section class="admin-block">
          <h2>Captured emails (<?= count($leads) ?>)</h2>
          <?php if (!$leads): ?><p class="muted">No emails yet.</p><?php endif; ?>
          <?php if ($leads): ?>
          <table>
            <thead><tr><th>Email</th><th>Source</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $l): ?>
              <tr>
                <td><?= h($l['email']) ?></td>
                <td><?= h($l['source'] === 'post' ? 'Note' : 'Home page') ?></td>
                <td><?= h(date('Y-m-d H:i', strtotime($l['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </section>

        <section class="admin-block">
          <h2>Recent comments (<?= count($comments) ?>)</h2>
          <?php if (!$comments): ?><p class="muted">No comments.</p><?php endif; ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment">
              <div class="note-meta">
                <strong><?= h($c['author']) ?></strong>
                <span>on <a href="<?= h(url(['r' => 'post', 'slug' => $c['slug']])) ?>"><?= h($c['title']) ?></a></span>
                <time datetime="<?= h($c['created_at']) ?>"><?= h(date('Y-m-d H:i', strtotime($c['created_at']))) ?></time>
              </div>
              <p><?= nl2br(h($c['body'])) ?></p>
              <form method="post" action="index.php" onsubmit="return confirm('Delete this comment?');">
                <input type="hidden" name="action" value="comment_delete">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="danger small">Delete comment</button>
              </form>
            </div>
          <?php endforeach; ?>
        </section>
    <?php });
}

function page_admin_edit(array $post, string $csrf, ?string $flash): void
{
    render_page('Edit · ' . $post['title'], $flash, function () use ($post, $csrf) { ?>
        <div class="admin-top">
          <h1>Edit note</h1>
          <a class="secondary" href="<?= h(url(['r' => 'admin'])) ?>">Back to dashboard</a>
        </div>

        <section class="admin-block">
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="post_update">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
            <label>Title <input type="text" name="title" required maxlength="150" value="<?= h($post['title']) ?>"></label>
            <label>Summary <input type="text" name="excerpt" required maxlength="300" value="<?= h($post['excerpt']) ?>"></label>
            <label>Content <small class="hint">For a Copy button, put the command between three-backtick lines. To customize the clipboard, open with <code>```custom-copy</code>, enter the visible command, add <code>---copy---</code> on its own line, then the text to copy. Close with three backticks. Use <code>```alternating-copy</code> to copy the command first and the custom text second.</small><textarea name="body" required maxlength="20000" rows="16"><?= h($post['body']) ?></textarea></label>
            <label>Cover image path <input type="text" name="cover" maxlength="300" placeholder="img/example.svg" value="<?= h((string) ($post['cover'] ?? '')) ?>"></label>
            <p class="fine">Slug stays <code><?= h($post['slug']) ?></code> — existing links to this note keep working.</p>
            <button type="submit">Save changes</button>
          </form>
        </section>
    <?php });
}

function skill_copy_fields(array $skill = []): void
{
    $settings = json_decode($skill['copy_settings'] ?? '{}', true);
    ?>
    <fieldset><legend>Copy buttons</legend>
      <p class="hint">Choose what each step copies. Alternating starts with the visible command, then copies your custom text.</p>
      <?php foreach (SKILL_COPY_STEPS as $key => $label): ?>
        <?php $entry = $settings[$key] ?? []; ?>
        <details>
          <summary><?= h($label) ?></summary>
          <label>Copy behavior
            <select name="copy_mode[<?= h($key) ?>]">
              <?php foreach (['visible' => 'Visible command', 'custom-copy' => 'Always custom text', 'alternating-copy' => 'Alternate command and custom text'] as $mode => $caption): ?>
                <option value="<?= h($mode) ?>" <?= ($entry['mode'] ?? 'visible') === $mode ? 'selected' : '' ?>><?= h($caption) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Custom clipboard text
            <textarea name="copy_text[<?= h($key) ?>]" rows="3" maxlength="8000" spellcheck="false"><?= h($entry['text'] ?? '') ?></textarea>
          </label>
        </details>
      <?php endforeach; ?>
    </fieldset>
    <?php
}

function page_admin_edit_skill(array $skill, string $csrf, ?string $flash): void
{
    render_page('Edit · ' . $skill['name'], $flash, function () use ($skill, $csrf) { ?>
        <div class="admin-top">
          <h1>Edit skill</h1>
          <a class="secondary" href="<?= h(url(['r' => 'admin'])) ?>">Back to dashboard</a>
        </div>

        <section class="admin-block">
          <form method="post" action="index.php" class="form">
            <input type="hidden" name="action" value="skill_update">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $skill['id'] ?>">
            <label>Name <input type="text" name="name" required maxlength="80" value="<?= h($skill['name']) ?>"></label>
            <label>Category <input type="text" name="category" required maxlength="40" value="<?= h($skill['category']) ?>"></label>
            <label>Description <input type="text" name="description" required maxlength="400" value="<?= h($skill['description']) ?>"></label>
            <label>Install command <small class="hint">Shown in the guide and Quick install. Configure its Copy behavior below.</small><textarea name="command" required maxlength="8000" rows="4" spellcheck="false"><?= h($skill['command']) ?></textarea></label>
            <label>Linked note slug (optional) <input type="text" name="post_slug" maxlength="150" value="<?= h((string) ($skill['post_slug'] ?? '')) ?>"></label>
            <label>What it does <textarea name="about" maxlength="1000" rows="4"><?= h((string) ($skill['about'] ?? '')) ?></textarea></label>
            <label>It runs when <input type="text" name="use_when" maxlength="400" value="<?= h((string) ($skill['use_when'] ?? '')) ?>"></label>
            <label>Example prompt <input type="text" name="example" maxlength="400" value="<?= h((string) ($skill['example'] ?? '')) ?>"></label>
            <label>Extra content (optional; supports ``` code blocks and &lt;!-- comments --&gt;) <textarea name="extra" maxlength="10000" rows="8"><?= h((string) ($skill['extra'] ?? '')) ?></textarea></label>
            <?php skill_copy_fields($skill); ?>
            <button type="submit">Save changes</button>
          </form>
        </section>
    <?php });
}

/** Install guide for one skill: numbered steps with copyable commands. */
function skill_guide(array $s): void
{
    $n = (string) $s['name'];
    $example = (string) ($s['example'] ?? '');
    ?>
    <section class="guide" aria-labelledby="guide-title-<?= h($n) ?>">
      <h2 id="guide-title-<?= h($n) ?>">Follow these steps to install <?= h($n) ?></h2>
      <ol class="steps">
        <!-- step 1: make sure Codex is installed -->
        <li>
          <h3>Check that your agent is installed</h3>
          <p>Skills are folders with a SKILL.md. They work in Codex and in Claude Code. Open a project folder first.</p>
          <p class="step-label">Codex</p>
          <?= skill_copy_block($s, 'codex_version', 'codex --version') ?>
          <p class="step-label">Claude Code</p>
          <?= skill_copy_block($s, 'claude_version', 'claude --version') ?>
        </li>
        <!-- step 2: three ways to install; the registry command is the default -->
        <li>
          <h3>Install the skill</h3>
          <p>The skill is a real folder in this blog's own repository<?php if (!empty($s['source_url'])): ?> (<a href="<?= h((string) $s['source_url']) ?>">view it on GitHub</a>)<?php endif; ?>. Pick one way.</p>
          <?php $srcUrl = (string) ($s['source_url'] ?? ''); ?>
          <?php if ($srcUrl !== ''): ?>
            <p class="step-label">Primary install command</p>
            <?= skill_copy_block($s, 'install', (string) $s['command']) ?>
            <p class="step-label">With the skills CLI, for Claude Code</p>
            <?= skill_copy_block($s, 'claude_install', skill_cli_command($n, $srcUrl, 'claude-code')) ?>
            <p class="step-label">Inside Codex</p>
            <?= skill_copy_block($s, 'inside_codex', '$skill-installer install ' . $srcUrl) ?>
            <p class="step-label">By hand, for Codex</p>
            <?= skill_copy_block($s, 'manual_codex', skill_manual_command($srcUrl, '~/.agents/skills')) ?>
            <p class="step-label">By hand, for Claude Code</p>
            <?= skill_copy_block($s, 'manual_claude', skill_manual_command($srcUrl, '~/.claude/skills')) ?>
          <?php else: ?>
            <?= skill_copy_block($s, 'install', (string) $s['command']) ?>
          <?php endif; ?>
          <p class="step-note">The skills CLI installs for every project (<code>-g</code>). Leave out <code>-g</code> to install into the current project only.</p>
        </li>
        <!-- step 3: /skills lists every skill Codex found -->
        <li>
          <h3>Check that it loaded</h3>
          <p>Type this in Codex (in Claude Code, type <code>/</code> and look for the name). <?= h($n) ?> should be in the list. If it is not, restart the app. Installed files go to <code>~/.agents/skills</code> (Codex) or <code>~/.claude/skills</code> (Claude Code).</p>
          <?= skill_copy_block($s, 'list', '/skills') ?>
        </li>
        <!-- step 4: test the trigger without naming the skill -->
        <li>
          <h3>Try it</h3>
          <p>Describe the task in plain words, without naming the skill. If Codex loads it, the trigger works.</p>
          <?php if ($example !== ''): ?><?= skill_copy_block($s, 'example', $example) ?><?php endif; ?>
        </li>
        <!-- step 5: read SKILL.md and scripts/ before trusting it -->
        <li>
          <h3>Read it before you trust it</h3>
          <p>A skill can ship scripts and can tell the agent to fetch a URL. Look at what you installed.</p>
          <?= skill_copy_block($s, 'inspect', "ls ~/.agents/skills/" . $n . "\ncat ~/.agents/skills/" . $n . "/SKILL.md") ?>
        </li>
      </ol>
    </section>
    <?php
}

function page_skills(array $skills, ?string $flash): void
{
    $counts = [];
    foreach ($skills as $s) {
        $counts[$s['category']] = ($counts[$s['category']] ?? 0) + 1;
    }
    ksort($counts);
    $codexUrl = url(['r' => 'post', 'slug' => 'how-to-add-skills-to-openai-codex']);

    render_page('Skills', $flash, function () use ($skills, $counts, $codexUrl) { ?>
        <div class="shell">
          <aside class="side" aria-label="Categories">
            <p class="side-title">Browse</p>
            <div class="side-list" data-cats>
              <button type="button" data-cat="all" aria-pressed="true"><span>All skills</span><b><?= count($skills) ?></b></button>
              <?php foreach ($counts as $cat => $n): ?>
                <button type="button" data-cat="<?= h((string) $cat) ?>" aria-pressed="false"><span><?= h((string) $cat) ?></span><b><?= (int) $n ?></b></button>
              <?php endforeach; ?>
            </div>
            <p class="side-title">Guides</p>
            <div class="side-list">
              <a href="<?= h($codexUrl) ?>">Add skills to OpenAI Codex</a>
              <a href="<?= h(url()) ?>#notes">All notes</a>
            </div>
          </aside>

          <div class="content">
            <header class="titlebar">
              <span class="tile" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 13 9 5 9-5"/><path d="m3 17.5 9 5 9-5" opacity=".45"/></svg>
              </span>
              <div>
                <h1>Skills</h1>
                <p>Small skills that do one job. Open one for its install steps.</p>
              </div>
            </header>

            <div class="toolbar">
              <label class="search">
                <span class="sr-only">Search skills</span>
                <input type="search" placeholder="Search skills" data-search autocomplete="off">
              </label>
              <label class="sort">
                <span>Sort by</span>
                <select data-sort>
                  <option value="name">Name</option>
                  <option value="category">Category</option>
                </select>
              </label>
              <p class="count"><b data-count><?= count($skills) ?></b> skills</p>
            </div>

            <?php if (!$skills): ?><p class="muted">No skills yet. Add one from the admin dashboard.</p><?php endif; ?>
            <section class="skgrid" data-grid aria-label="Skills">
              <?php foreach ($skills as $s): ?>
                <a class="skc" href="<?= h(url(['r' => 'skill', 'name' => (string) $s['name']])) ?>"
                   data-name="<?= h(strtolower((string) $s['name'])) ?>"
                   data-cat="<?= h((string) $s['category']) ?>"
                   data-text="<?= h(strtolower($s['name'] . ' ' . $s['category'] . ' ' . $s['description'])) ?>">
                  <span class="tile sm" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 13 9 5 9-5"/></svg>
                  </span>
                  <span class="skc-body">
                    <strong><?= h($s['name']) ?></strong>
                    <span class="skc-desc"><?= h($s['description']) ?></span>
                    <span class="skc-tags">
                      <span class="chip"><?= h($s['category']) ?></span>
                      <span class="skc-go">Install steps</span>
                    </span>
                  </span>
                </a>
              <?php endforeach; ?>
            </section>
            <p class="empty" data-empty hidden>No skill matches that search. Clear the search or pick All skills.</p>
          </div>
        </div>

        <script>
        (function () {
          var grid = document.querySelector('[data-grid]');
          if (!grid) return;
          var cards = Array.prototype.slice.call(grid.children);
          var search = document.querySelector('[data-search]');
          var sort = document.querySelector('[data-sort]');
          var count = document.querySelector('[data-count]');
          var empty = document.querySelector('[data-empty]');
          var cat = 'all';
          function apply() {
            var q = search.value.trim().toLowerCase(), shown = 0;
            cards.forEach(function (c) {
              var ok = (cat === 'all' || c.dataset.cat === cat) && (q === '' || c.dataset.text.indexOf(q) !== -1);
              c.hidden = !ok; if (ok) shown++;
            });
            count.textContent = shown; empty.hidden = shown !== 0;
          }
          document.querySelectorAll('[data-cats] button').forEach(function (b) {
            b.addEventListener('click', function () {
              cat = b.dataset.cat;
              document.querySelectorAll('[data-cats] button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
              apply();
            });
          });
          search.addEventListener('input', apply);
          sort.addEventListener('change', function () {
            var key = sort.value;
            cards.sort(function (a, b) {
              var ka = key === 'category' ? a.dataset.cat + a.dataset.name : a.dataset.name;
              var kb = key === 'category' ? b.dataset.cat + b.dataset.name : b.dataset.name;
              return ka < kb ? -1 : ka > kb ? 1 : 0;
            });
            cards.forEach(function (c) { grid.appendChild(c); });
          });
        })();
        </script>
    <?php }, true);
}

function page_skill(array $s, ?array $post): void
{
    render_page($s['name'] . ' · install guide', null, function () use ($s, $post) { ?>
        <nav class="crumbs" aria-label="Breadcrumb">
          <a href="<?= h(url()) ?>">Home</a><span>/</span><a href="<?= h(url(['r' => 'skills'])) ?>">Skills</a><span>/</span><b><?= h($s['name']) ?></b>
        </nav>
        <div class="detail">
          <article class="detail-main">
            <header class="detail-head">
              <span class="chip"><?= h($s['category']) ?></span>
              <h1><?= h($s['name']) ?></h1>
              <p class="dek"><?= h($s['description']) ?></p>
            </header>

            <?php if (!empty($s['about'])): ?>
              <section class="about-skill">
                <h2>What it does</h2>
                <p><?= h($s['about']) ?></p>
                <?php if (!empty($s['use_when'])): ?>
                  <p><strong>It runs when:</strong> <?= h($s['use_when']) ?></p>
                <?php endif; ?>
              </section>
            <?php endif; ?>

            <?php skill_guide($s); ?>

            <?php if (!empty($s['extra'])): ?>
              <section class="about-skill">
                <h2>More details</h2>
                <div class="prose"><?= render_body((string) $s['extra'], 'extra/' . $s['id']) ?></div>
              </section>
            <?php endif; ?>

            <?php if ($post !== null): ?>
              <section class="about-skill">
                <h2>Background</h2>
                <p><a href="<?= h(url(['r' => 'post', 'slug' => (string) $post['slug']])) ?>"><?= h($post['title']) ?></a></p>
                <p class="muted"><?= h($post['excerpt']) ?></p>
              </section>
            <?php endif; ?>
          </article>

          <aside class="glance" aria-label="Quick install">
            <h2>Quick install</h2>
            <?= skill_copy_block($s, 'install', (string) $s['command']) ?>
            <?php if (!empty($s['example'])): ?>
              <h2>Example prompt</h2>
              <?= skill_copy_block($s, 'example', (string) $s['example']) ?>
            <?php endif; ?>
            <?php if (!empty($s['source_url'])): ?>
              <h2>Source</h2>
              <p><a href="<?= h((string) $s['source_url']) ?>">View this skill on GitHub</a></p>
            <?php endif; ?>
            <p><a href="<?= h(url(['r' => 'skills'])) ?>">Back to all skills</a></p>
          </aside>
        </div>
    <?php }, true);
}
function page_home(array $posts, ?string $flash): void
{
    render_page('Installation library', $flash, function () use ($posts) { ?>
      <section class="library-intro">
        <div><h1>Installation<br>library<span>.</span></h1>
        <p>Set up your tools and agent skills with step-by-step guides and commands you can copy.</p>
        <div class="hero-links"><a href="#notes">Browse guides ↓</a><a href="<?= h(url(['r' => 'skills'])) ?>">Explore skills ↗</a></div></div>
        <div class="system-art" aria-hidden="true"><i></i><i></i><i></i><b>&gt;_</b></div>
      </section>
      <section id="notes" class="library-list"><header><h2>Published guides</h2><span><?= count($posts) ?> entries</span></header>
        <?php if (!$posts): ?><p>No guides have been published yet.</p><?php endif; ?>
        <?php foreach ($posts as $post): ?>
          <a class="library-row" href="<?= h(url(['r' => 'post', 'slug' => $post['slug']])) ?>">
            <img src="<?= h($post['cover'] ?: 'img/terminal.svg') ?>" alt="" width="104" height="70">
            <div><small><?= h(fmt_date($post['created_at'])) ?> · <?= reading_minutes((int) $post['chars']) ?> min read</small>
            <h3><?= h($post['title']) ?></h3><p><?= h($post['excerpt']) ?></p></div><b aria-hidden="true">↗</b>
          </a>
        <?php endforeach; ?>
      </section>
      <aside class="inline-cta"><h2>New guides by email</h2><?php lead_form('home'); ?></aside>
    <?php }, true);
}


