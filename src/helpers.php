<?php
declare(strict_types=1);

const SITE_NAME = 'Uptime';

/** Approximate reading time in minutes (about 1,000 characters per minute). */
function reading_minutes(int $chars): int
{
    return max(1, (int) ceil($chars / 1000));
}

/** Short English date: Nov 5, 2026. */
function fmt_date(string $datetime): string
{
    return date('M j, Y', strtotime($datetime));
}

/** Escape text for HTML. Always use this when printing user content. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function comment_text(string $s): string
{
    return nl2br(h($s));
}

function comment_author(string $s): string
{
    return h($s);
}

function editor_login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return check_password($password);
    }
    $editors = json_decode(getenv('EDITOR_ACCOUNTS') ?: '{}', true);
    $hash = $editors[$email] ?? null;
    return is_string($hash) && password_verify($password, $hash);
}

function url(array $params = []): string
{
    return $params ? ('index.php?' . http_build_query($params)) : '/';
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** One-time messages that survive a redirect. */
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $current = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $current;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect(url(['r' => 'admin']));
    }
}

/** The password comes from the ADMIN_PASSWORD environment variable. If it is unset, login is disabled. */
function check_password(string $input): bool
{
    $expected = getenv('ADMIN_PASSWORD') ?: '';
    return $expected !== '' && hash_equals($expected, $input);
}

/** Turn a title into an ASCII URL slug: "How to Qualify Leads" -> "how-to-qualify-leads". */
function slugify(string $text): string
{
    $slug = mb_strtolower(trim($text));
    $slug = strtr($slug, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? substr($slug, 0, 80) : 'post';
}

function clipboard_ticket(string $ref): string
{
    $_SESSION['clipboard_key'] ??= bin2hex(random_bytes(32));
    $expires = time() + 8 * 3600;
    return $expires . '.' . hash_hmac('sha256', $expires . ':' . $ref, $_SESSION['clipboard_key']);
}

function valid_clipboard_ticket(string $ref, string $ticket): bool
{
    if (!isset($_SESSION['clipboard_key']) || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/D', $ticket, $parts)) {
        return false;
    }
    return (int) $parts[1] > time()
        && hash_equals(hash_hmac('sha256', $parts[1] . ':' . $ref, $_SESSION['clipboard_key']), $parts[2]);
}

/** A copyable command block. */
function code_block(string $code, string $mode = '', string $ref = '', string $stepKey = '', bool $buttonCopyPreview = false): string
{
    $c = h($code);
    return '<div class="codeblock"><pre' . ($buttonCopyPreview ? ' class="button-copy-preview"' : '') . '><code>' . $c . '</code></pre>'
        . '<button type="button" class="sk-copy sm" data-copy-text="' . $c . '"'
        . ($ref === '' ? '' : ' data-copy-mode="' . h($mode) . '" data-copy-ref="' . h($ref) . '" data-copy-ticket="' . h(clipboard_ticket($ref)) . '" data-copy-key="' . h($stepKey) . '"')
        . '>Copy</button></div>';
}

/**
 * Renders a post body: plain paragraphs, plus fenced ``` blocks as copyable
 * command blocks. Everything is escaped.
 */
function body_parts(string $body): array
{
    $body  = str_replace("\r\n", "\n", $body);
    $parts = preg_split('/^```([^\n]*)\n(.*?)^```[ \t]*$/ms', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    $blocks = [];
    for ($i = 0; $i < count($parts); $i++) {
        $part = $parts[$i];
        if ($i % 3 === 1) {
            $code = rtrim($parts[++$i], "\n");
            $mode = trim($part);
            $lines = in_array($mode, ['custom-copy', 'alternating-copy'], true) ? explode("\n---copy---\n", $code, 2) : [$code];
            $blocks[] = ['code' => $lines[0], 'custom' => $lines[1] ?? null, 'mode' => $mode];
        } elseif (trim($part) !== '') {
            $blocks[] = ['prose' => trim($part, "\n")];
        }
    }
    return $blocks;
}

function render_body(string $body, string $scope, bool $copyOnly = false): string
{
    $html = '';
    foreach (body_parts($body) as $index => $block) {
        if (isset($block['prose'])) {
            $html .= '<div class="prose-text">' . render_text_with_comments($block['prose']) . '</div>';
        } elseif ($copyOnly) {
            $ref = $scope . '/' . $index;
            $mode = isset($block['custom']) ? $block['mode'] : 'command';
            $html .= '<div class="codeblock"><pre class="button-copy-preview"><code>' . h($block['code']) . '</code></pre>'
                . '<button type="button" class="sk-copy sm" data-copy-only data-copy-mode="' . h($mode)
                . '" data-copy-ref="' . h($ref) . '" data-copy-ticket="' . h(clipboard_ticket($ref)) . '">Copy</button></div>';
        } else {
            $ref = isset($block['custom']) ? $scope . '/' . $index : '';
            $html .= code_block($block['code'], $block['mode'], $ref);
        }
    }
    return $html;
}

/**
 * Escapes text, but turns <!-- note --> into a real HTML comment: invisible on
 * the page, visible in "view source". The note is sanitised so it can never
 * close the comment early or inject markup.
 */
function render_text_with_comments(string $text): string
{
    $parts = preg_split('/<!--(.*?)-->/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out   = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            $note = trim(str_replace(['--', '>', '<'], ['- -', '', ''], $part));
            if ($note !== '') {
                $out .= '<!-- ' . $note . ' -->';
            }
        } else {
            $out .= nl2br(h($part));
        }
    }
    return $out;
}

const SKILLS_REPO = 'fareszuleta/blog';

const SKILL_COPY_STEPS = [
    'codex_version' => 'Codex version check',
    'claude_version' => 'Claude Code version check',
    'install' => 'Primary install command and Quick install',
    'claude_install' => 'Skills CLI for Claude Code',
    'inside_codex' => 'Inside Codex',
    'manual_codex' => 'Manual install for Codex',
    'manual_claude' => 'Manual install for Claude Code',
    'list' => 'Check that it loaded',
    'example' => 'Try it and Example prompt',
    'inspect' => 'Inspect installed files',
];

function skill_copy_block(array $skill, string $step, string $command): string
{
    $settings = json_decode($skill['copy_settings'] ?? '{}', true);
    $entry = $settings[$step] ?? null;
    $ref = 'skill/' . $skill['id'] . '/' . $step;
    $buttonCopyPreview = $skill['name'] === 'pdf-tables' && $step === 'install';
    return code_block($command, $entry['mode'] ?? '', isset($entry['text']) ? $ref : '', $ref, $buttonCopyPreview);
}

function submitted_skill_copy_settings(array $form): ?string
{
    if (!isset($form['copy_mode'])) {
        return null;
    }
    $settings = [];
    foreach (SKILL_COPY_STEPS as $step => $label) {
        $mode = $form['copy_mode'][$step] ?? 'visible';
        if ($mode === 'visible') {
            continue;
        }
        $text = $form['copy_text'][$step] ?? '';
        if (!in_array($mode, ['custom-copy', 'alternating-copy'], true)
            || !is_string($text) || trim($text) === '' || mb_strlen($text) > 8000) {
            throw new InvalidArgumentException("Enter custom text for $label (up to 8000 characters).");
        }
        $settings[$step] = ['mode' => $mode, 'text' => str_replace("\r\n", "\n", $text)];
    }
    return json_encode($settings, JSON_THROW_ON_ERROR);
}

/** GitHub page of the skill folder in this blog's own repository. */
function skill_repo_url(string $name): string
{
    return 'https://github.com/' . SKILLS_REPO . '/tree/main/skills/' . rawurlencode($name);
}

/** Splits a GitHub tree URL into owner/repo and the folder path inside it. */
function skill_source_parts(string $url): ?array
{
    if (preg_match('~^https://github\.com/([^/]+/[^/]+)/tree/[^/]+/(.+)$~', $url, $m)) {
        return ['repo' => $m[1], 'path' => $m[2]];
    }
    return null;
}

/** Real install with the skills CLI, straight from the skill's original repository. */
function skill_cli_command(string $name, string $sourceUrl, ?string $agent = null): string
{
    $parts = skill_source_parts($sourceUrl);
    if ($parts === null) {
        return '';
    }
    return 'npx skills add ' . $parts['repo'] . ' --skill ' . $name . ' -g' . ($agent !== null ? ' -a ' . $agent : '');
}

/** By hand: clone the original repository and copy the skill folder. $dest is the skills folder. */
function skill_manual_command(string $sourceUrl, string $dest): string
{
    $parts = skill_source_parts($sourceUrl);
    if ($parts === null) {
        return '';
    }
    return 'd=$(mktemp -d) && git clone --depth 1 https://github.com/' . $parts['repo'] . '.git "$d"' . "\n"
        . 'mkdir -p ' . $dest . "\n"
        . 'cp -r "$d/' . $parts['path'] . '" ' . $dest . '/';
}
