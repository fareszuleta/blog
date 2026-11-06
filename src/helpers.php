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

/**
 * CTF_MODE=1 enables an INTENTIONAL stored XSS in comments (author and body are
 * printed without escaping). Training use only: never enable it on a public site.
 */
function ctf_mode(): bool
{
    return getenv('CTF_MODE') === '1';
}

/** Comment body: escaped normally, raw HTML when CTF_MODE is on. */
function comment_text(string $s): string
{
    return nl2br(ctf_mode() ? $s : h($s));
}

/** Comment author: escaped normally, raw HTML when CTF_MODE is on. */
function comment_author(string $s): string
{
    return ctf_mode() ? $s : h($s);
}

/** The home page is static (public/index.html), served at "/". Every other page is index.php. */
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
