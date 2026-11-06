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

    $pdo->exec('CREATE TABLE IF NOT EXISTS skills (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        category    TEXT NOT NULL,
        description TEXT NOT NULL,
        command     TEXT NOT NULL,
        post_slug   TEXT,
        created_at  TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\'))
    )');

    // Migration: add the cover image column to databases created before it existed.
    $cols = array_column($pdo->query('PRAGMA table_info(posts)')->fetchAll(), 'name');
    if (!in_array('cover', $cols, true)) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN cover TEXT');
        $pdo->exec("UPDATE posts SET cover = 'img/shelves.svg' WHERE slug = 'how-to-qualify-leads' AND cover IS NULL");
        $pdo->exec("UPDATE posts SET cover = 'img/phone.svg' WHERE slug = 'cold-call-script' AND cover IS NULL");
    }

    seed_if_empty($pdo);
    seed_skills_if_empty($pdo);
    return $pdo;
}

function seed_if_empty(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn() > 0) {
        return;
    }

    $samples = [
        [
            'cut-your-lcp-in-an-afternoon',
            'Cut your LCP in an afternoon',
            'Three changes that moved a real site from 4.1s to 1.6s, in order of effort.',
            "Largest Contentful Paint measures when the biggest visible thing on the page finishes rendering. Most sites blow this on three things, and you can fix all three without touching your framework.\n\n" .
            "1. Preload the hero image. A background-image set in CSS is discovered late, after the stylesheet parses. Add a <link rel=\"preload\"> for it in <head> and the browser starts fetching it immediately.\n" .
            "2. Serve it at the size it's displayed. A 2400px source scaled down by CSS still costs the full download. Generate a srcset at the breakpoints you actually use.\n" .
            "3. Stop shipping render-blocking fonts. A @font-face with no font-display swaps to a flash of invisible text while it loads. Set font-display: swap and the browser paints with a fallback immediately.\n\n" .
            "On the site I measured, step 1 alone took LCP from 4.1s to 2.3s. Steps 2 and 3 brought it to 1.6s. None of this required a rebuild.",
        ],
        [
            'give-your-agent-memory-it-can-trust',
            'Give your agent memory it can trust',
            'Most agent memory fails quietly. Here is what actually holds up across sessions.',
            "An agent that forgets everything between sessions re-asks the same questions forever. An agent that remembers everything indiscriminately starts acting on stale facts. The difference between a useful memory system and a liability is what you choose to write down.\n\n" .
            "Write facts, not transcripts. \"The deploy key rotated on the 3rd\" survives being read six months later. A dump of the conversation where that happened does not — by then the context that made it meaningful is gone.\n\n" .
            "Timestamp everything, and let old entries expire. A memory system with no decay accumulates contradictions: which email address is current if you wrote two, a year apart, and never deleted either?\n\n" .
            "Separate what the agent observed from what you told it directly. Self-reported user preferences are usually reliable. Things the agent inferred on its own are not, and conflating the two is where most of the bad surprises come from.\n\n" .
            "The test that matters: open the memory file cold, with no other context. If a fact doesn't make sense on its own, it will mislead the agent just as easily as it misleads you.",
        ],
        [
            'writing-a-skill-your-agent-will-use',
            'Writing a skill your agent will actually use',
            'The failure mode is not that the skill is wrong. It is that the agent never reaches for it.',
            "A skill is only useful if the agent picks it at the right moment. Most skills that get written and never used have the same problem: the description describes the skill's content, not the situation that should trigger it.\n\n" .
            "Write the trigger first. Before any instructions, list the concrete phrases and situations where this skill should fire. \"Handles PDF extraction\" is weaker than \"when the user mentions a .pdf, pastes a PDF path, or asks to pull text or tables from a document.\"\n\n" .
            "Keep the body short enough to actually read. A skill that front-loads five paragraphs of background before the first actionable step gets skimmed, and the step that mattered is paragraph six.\n\n" .
            "Name it by what it does, not by what it's for. \"pdf-tools\" beats \"document-productivity-suite\" — the agent is matching the name against the task, not browsing a catalog.\n\n" .
            "Test it the obvious way: start a fresh session, describe the task the way a real person would, and see whether the skill loads without being named directly. If it doesn't, the trigger needs work, not the instructions.",
        ],
        [
            'inside-a-skill-what-youre-actually-installing',
            "Inside a skill: what you're actually installing",
            'A catalog makes adding a skill feel like a package install. The file you get is closer to a small program with instructions attached.',
            "A skill, in the agent-tooling sense, is usually three things bundled together: a short trigger description, a body of instructions, and sometimes scripts or reference files the agent can run or read. The trigger is metadata the agent matches against your request before it reads anything else, which is why a vague one (\"handles document processing\") gets skipped in favor of a specific one (\"fires when the user mentions a .pdf or asks to pull a table from a document\").\n\n" .
            "Catalogs make this easy to forget. A directory that lists hundreds of ready-made skills, agents, and slash commands for a given tool, installable with one CLI command, turns adding a skill into something that feels like adding a package. That convenience is exactly what makes it worth slowing down for: you are pulling in a file written by someone else, that an agent will read and sometimes act on, directly into a session where it already has your permissions.\n\n" .
            "Three things worth checking before installing a skill you did not write. Read the actual file, not just the catalog's description of it, since the description is marketing copy and the file is the thing that runs. Look inside any scripts folder the skill ships with: a skill that includes a script can execute code, and the skill's own summary of what that script does is not the same as reading it. Check whether the instructions tell the agent to fetch a URL or send data anywhere external, the same way you would check what a new dependency phones home to.\n\n" .
            "None of this is an exotic risk. It is the ordinary trust question you already apply to a library before you add it to a project: a skill is close enough to code that it runs with your agent's access, and being listed in a public catalog is not, by itself, a trust signal.\n\n" .
            "If you are the one publishing a skill, the two things worth getting right are the same two from writing a skill your agent will actually use: a trigger specific enough that it only fires on the task it is actually for, and a body that is upfront, near the top, about anything it asks the agent to fetch, run, or send. Someone deciding whether to install it should be able to tell at a glance, without reading every line.",
        ],
    ];

    $covers = [
        'cut-your-lcp-in-an-afternoon'                    => 'img/waterfall.svg',
        'give-your-agent-memory-it-can-trust'             => 'img/node-graph.svg',
        'writing-a-skill-your-agent-will-use'             => 'img/terminal.svg',
        'inside-a-skill-what-youre-actually-installing'   => 'img/package.svg',
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

/** Updates title, excerpt, body and cover. The slug never changes, so existing links keep working. */
function update_post(int $id, string $title, string $excerpt, string $body, ?string $cover): void
{
    db()->prepare('UPDATE posts SET title = ?, excerpt = ?, body = ?, cover = ? WHERE id = ?')
        ->execute([$title, $excerpt, $body, $cover, $id]);
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

function get_skills(): array
{
    return db()->query('SELECT * FROM skills ORDER BY id ASC')->fetchAll();
}

function get_skill_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM skills WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function create_skill(string $name, string $category, string $description, string $command, ?string $postSlug): void
{
    db()->prepare('INSERT INTO skills (name, category, description, command, post_slug) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $category, $description, $command, $postSlug]);
}

function update_skill(int $id, string $name, string $category, string $description, string $command, ?string $postSlug): void
{
    db()->prepare('UPDATE skills SET name = ?, category = ?, description = ?, command = ?, post_slug = ? WHERE id = ?')
        ->execute([$name, $category, $description, $command, $postSlug, $id]);
}

function delete_skill(int $id): void
{
    db()->prepare('DELETE FROM skills WHERE id = ?')->execute([$id]);
}

function seed_skills_if_empty(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn() > 0) {
        return;
    }
    $rows = [
        ['pdf-tables', 'documents', "Pulls structured tables out of a PDF and returns them as CSV, instead of a wall of OCR'd text.", 'skills add pdf-tables', 'pdf-tables-and-why-ocr-is-the-wrong-tool'],
        ['lcp-audit', 'web performance', 'Runs a Lighthouse pass against a URL and returns the changes with the best time-to-fix ratio, ranked by effort.', 'skills add lcp-audit', 'lcp-audit-ranking-fixes-by-effort'],
        ['memory-gc', 'agent memory', "Walks a long-lived memory file and flags entries past their stated expiry, so stale facts don't silently persist.", 'skills add memory-gc', 'memory-gc-why-agent-memory-needs-a-garbage-collector'],
        ['flaky-test-finder', 'testing', 'Reruns a test suite several times and reports which tests fail intermittently, with the failure rate per test.', 'skills add flaky-test-finder', 'flaky-test-finder-stop-rerunning-ci-by-hand'],
        ['deploy-rollback', 'deploy', "Finds the last known-good deploy for a service and prepares the rollback command — it stages the fix, it doesn't run it.", 'skills add deploy-rollback', 'deploy-rollback-why-the-agent-should-stage-not-execute'],
        ['changelog-draft', 'writing', 'Turns a range of commits into a changelog entry, grouped by the kind of change rather than by commit message.', 'skills add changelog-draft', 'changelog-draft-grouping-by-change-not-by-message'],
        ['schema-diff', 'data', 'Compares two database schemas and lists every column that changed type or went missing, before a migration runs.', 'skills add schema-diff', 'schema-diff-the-migration-check-everyone-skips'],
        ['citation-check', 'research', 'Checks that every claim in a draft that cites a source actually matches what that source says.', 'skills add citation-check', 'citation-check-does-the-source-actually-say-that'],
    ];
    $stmt = $pdo->prepare('INSERT INTO skills (name, category, description, command, post_slug) VALUES (?, ?, ?, ?, ?)');
    foreach ($rows as $row) {
        $stmt->execute($row);
    }
}
