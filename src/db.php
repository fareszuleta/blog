<?php
declare(strict_types=1);

/**
 * SQLite connection (PDO) and blog queries.
 * The database is created automatically on first run.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = getenv('DATA_DIR') ?: __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/blog.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec('CREATE TABLE IF NOT EXISTS posts (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        slug       TEXT NOT NULL UNIQUE,
        title      TEXT NOT NULL,
        excerpt    TEXT NOT NULL,
        body       TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\'))
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
        author     TEXT NOT NULL,
        body       TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\'))
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS leads (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        email      TEXT NOT NULL UNIQUE,
        source     TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\'))
    )');

    // Migration: add the cover image column to databases created before it existed.
    $cols = array_column($pdo->query('PRAGMA table_info(posts)')->fetchAll(), 'name');
    if (!in_array('cover', $cols, true)) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN cover TEXT');
        $pdo->exec("UPDATE posts SET cover = 'img/shelves.svg' WHERE slug = 'how-to-qualify-leads' AND cover IS NULL");
        $pdo->exec("UPDATE posts SET cover = 'img/phone.svg' WHERE slug = 'cold-call-script' AND cover IS NULL");
    }

    seed_if_empty($pdo);
    return $pdo;
}

function seed_if_empty(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn() > 0) {
        return;
    }

    $samples = [
        [
            'how-to-qualify-leads',
            'How to Qualify Leads in 5 Steps',
            'Stop wasting time on prospects who will never buy.',
            "Qualifying a lead means deciding how much time it deserves. Not every contact is worth the same.\n\n" .
            "1. Confirm the problem: does the prospect have a real need your product solves?\n" .
            "2. Confirm the budget: can they afford what you sell?\n" .
            "3. Confirm authority: who makes the buying decision?\n" .
            "4. Confirm timing: is there a deadline or a reason to act now?\n" .
            "5. Confirm the next step: will they accept a meeting or a demo?\n\n" .
            "If they answer yes to fewer than three of these, nurture them with useful content and check back later.",
        ],
        [
            'cold-call-script',
            'A Cold Call Script That Actually Works',
            'A simple 30-second structure that earns attention without sounding like a catalog salesperson.',
            "Your goal in the first 30 seconds is not to sell. It is to earn permission to keep talking.\n\n" .
            "Suggested structure:\n" .
            "- A short introduction: who you are and which company you work for.\n" .
            "- A specific reason: mention something concrete about the prospect or their industry.\n" .
            "- An open question: \"How do you handle customer follow-up today?\"\n" .
            "- Your value proposition in one sentence, never in a paragraph.\n\n" .
            "Practice the script out loud. If it sounds natural spoken, it will work on the call.",
        ],
    ];

    $covers = [
        'how-to-qualify-leads' => 'img/shelves.svg',
        'cold-call-script'     => 'img/phone.svg',
    ];

    $stmt = $pdo->prepare('INSERT INTO posts (slug, title, excerpt, body, cover) VALUES (?, ?, ?, ?, ?)');
    foreach ($samples as $row) {
        $stmt->execute([...$row, $covers[$row[0]] ?? null]);
    }
}

function get_posts(): array
{
    return db()->query(
        'SELECT id, slug, title, excerpt, cover, created_at, length(body) AS chars FROM posts ORDER BY created_at DESC, id DESC'
    )->fetchAll();
}

function get_post_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM posts WHERE slug = ?');
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function get_comments(int $postId): array
{
    $st = db()->prepare(
        'SELECT id, author, body, created_at FROM comments WHERE post_id = ? ORDER BY created_at ASC, id ASC'
    );
    $st->execute([$postId]);
    return $st->fetchAll();
}

function get_recent_comments(int $limit = 100): array
{
    $st = db()->prepare(
        'SELECT c.id, c.author, c.body, c.created_at, p.title, p.slug
         FROM comments c JOIN posts p ON p.id = c.post_id
         ORDER BY c.created_at DESC, c.id DESC LIMIT ?'
    );
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function create_post(string $title, string $excerpt, string $body): void
{
    $pdo  = db();
    $base = slugify($title);
    $slug = $base;
    $i    = 2;

    $check = $pdo->prepare('SELECT 1 FROM posts WHERE slug = ?');
    while (true) {
        $check->execute([$slug]);
        $exists = (bool) $check->fetchColumn();
        $check->closeCursor();
        if (!$exists) {
            break;
        }
        $slug = $base . '-' . $i++;
    }

    $pdo->prepare('INSERT INTO posts (slug, title, excerpt, body) VALUES (?, ?, ?, ?)')
        ->execute([$slug, $title, $excerpt, $body]);
}

function get_post_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Updates title, excerpt and body. The slug never changes, so existing links keep working. */
function update_post(int $id, string $title, string $excerpt, string $body): void
{
    db()->prepare('UPDATE posts SET title = ?, excerpt = ?, body = ? WHERE id = ?')
        ->execute([$title, $excerpt, $body, $id]);
}

function delete_post(int $id): void
{
    db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
}

function add_comment(int $postId, string $author, string $body): void
{
    db()->prepare('INSERT INTO comments (post_id, author, body) VALUES (?, ?, ?)')
        ->execute([$postId, $author, $body]);
}

function delete_comment(int $id): void
{
    db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
}

function add_lead(string $email, string $source): void
{
    db()->prepare('INSERT OR IGNORE INTO leads (email, source) VALUES (?, ?)')->execute([$email, $source]);
}

function get_leads(int $limit = 200): array
{
    $st = db()->prepare('SELECT id, email, source, created_at FROM leads ORDER BY created_at DESC, id DESC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
