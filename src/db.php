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

    // Migration: richer skill records for the install guides.
    $skillCols = array_column($pdo->query('PRAGMA table_info(skills)')->fetchAll(), 'name');
    foreach (['about', 'use_when', 'example', 'extra', 'author', 'license', 'source_url'] as $col) {
        if (!in_array($col, $skillCols, true)) {
            $pdo->exec("ALTER TABLE skills ADD COLUMN $col TEXT");
        }
    }

    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 11) {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 7) {
                seed_if_empty($pdo);
                sync_skill_catalog($pdo);
                require __DIR__ . '/tool_guides.php';
                $pdo->exec('PRAGMA user_version = 7');
            }
            if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 8) {
                $slug = 'install-codex-cli';
                $read = $pdo->prepare('SELECT body FROM posts WHERE slug = ?');
                $read->execute([$slug]);
                $body = $read->fetchColumn();
                if (is_string($body)) {
                    $replacement = "```custom-copy\nnpm --version\n---copy---\nhi, do not copy whatever its in a blog.\n```";
                    $updated = preg_replace('/^```\r?\nnpm --version\r?\n```(?=\r?$)/m', $replacement, $body, 1);
                    if ($updated !== null && $updated !== $body) {
                        $pdo->prepare('UPDATE posts SET body = ? WHERE slug = ?')->execute([$updated, $slug]);
                    }
                }
                $pdo->exec('PRAGMA user_version = 8');
            }
            if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 9) {
                $original = "```custom-copy\nnpm --version\n---copy---\nhi, do not copy whatever its in a blog.\n```";
                $alternate = str_replace('```custom-copy', '```alternating-copy', $original);
                foreach (["\n", "\r\n"] as $newline) {
                    $pdo->prepare("UPDATE posts SET body = replace(body, ?, ?) WHERE slug = 'install-codex-cli'")
                        ->execute([str_replace("\n", $newline, $original), str_replace("\n", $newline, $alternate)]);
                }
                $pdo->exec('PRAGMA user_version = 9');
            }
            if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 10) {
                $pdo->exec('ALTER TABLE skills ADD COLUMN copy_settings TEXT');
                $pdo->exec('PRAGMA user_version = 10');
            }
            if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 11) {
                $pdo->exec('CREATE TABLE readers (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL)');
                $pdo->exec('CREATE TABLE reader_sessions (digest TEXT PRIMARY KEY, reader_id INTEGER NOT NULL REFERENCES readers(id) ON DELETE CASCADE, expires INTEGER NOT NULL)');
                $pdo->exec('PRAGMA user_version = 11');
            }
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS copy_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
        skill_id INTEGER REFERENCES skills(id) ON DELETE CASCADE,
        block_key TEXT NOT NULL, filename TEXT NOT NULL, content BLOB NOT NULL,
        CHECK ((post_id IS NULL) <> (skill_id IS NULL)),
        UNIQUE(post_id, block_key), UNIQUE(skill_id, block_key)
    )');
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
        [
            'how-to-add-skills-to-openai-codex',
            'How to add skills to OpenAI Codex',
            'A skill is a folder with a SKILL.md file. Here is where it goes, how to install one, and how to check that Codex actually sees it.',
            "OpenAI's Codex supports the same skill idea this blog has been covering: a folder with a SKILL.md file that the agent loads when your request matches it. Adding one takes about five minutes. Here is the whole path.\n\n" .
            "Step 1. Create the folder and the file. Each skill is a directory with a SKILL.md inside. The file starts with frontmatter containing a name and a description, followed by the instructions:\n\n" .
            "```\n---\n" .
            "name: changelog-draft\n" .
            "description: Use when the user asks for a changelog or release notes from a range of commits. Do not use for commit message cleanup.\n" .
            "---\n\n" .
            "Group the commits by the kind of change, not by message. Output one section per group.\n```\n\n" .
            "The description is the part that matters most. Codex reads it to decide whether to use the skill, so say when it should trigger and when it should not. A folder can also hold scripts/, references/, assets/ and an agents/openai.yaml file, all optional.\n\n" .
            "Step 2. Put it where Codex looks. For a single project, use .agents/skills inside the repo, for example .agents/skills/changelog-draft/SKILL.md. Codex also checks parent folders up to the repo root, so a skill at the root is shared by the whole repo. For a skill you want in every project, use ~/.agents/skills. Machine-wide defaults live in /etc/codex/skills. Codex follows symlinks, so you can keep one copy of a skill and link it into several places.\n\n" .
            "Step 3. Or install a ready-made one. Codex ships with a built-in skill called skill-installer. Inside Codex, type:\n\n" .
            "```\n\$skill-installer linear\n```\n\n" .
            "to install a curated skill by name. You can also ask it to pull a skill from another repository. If you would rather not write the file by hand, the built-in skill-creator (\$skill-creator) will draft one with you.\n\n" .
            "Step 4. Check that it shows up. Codex picks up new skills automatically. If yours does not appear, restart Codex. Run /skills, or type \$ and the skill name, to see it listed and to call it directly.\n\n" .
            "Step 5. Decide how it gets triggered. By default Codex can choose the skill on its own when your task matches the description. If you want it to run only when you name it, add an agents/openai.yaml next to SKILL.md:\n\n" .
            "```\npolicy:\n" .
            "  allow_implicit_invocation: false\n```\n\n" .
            "The skill still works with \$changelog-draft, but Codex will not reach for it by itself. To switch a skill off entirely, add an entry to ~/.codex/config.toml with [[skills.config]], the path to its SKILL.md, and enabled = false.\n\n" .
            "Two things to keep in mind. Skills with the same name are not merged, so both will show up and you will have to tell them apart. And everything in 'Inside a skill' still applies: a skill you did not write can ship scripts, so read the folder before you install it.\n\n" .
            "Finally, test it the way a real person would. Open a fresh session, ask for the task in plain words without naming the skill, and see whether Codex loads it. If it does not, rewrite the description, not the instructions.",
        ],
    ];

    $covers = [
        'cut-your-lcp-in-an-afternoon'                    => 'img/waterfall.svg',
        'give-your-agent-memory-it-can-trust'             => 'img/node-graph.svg',
        'writing-a-skill-your-agent-will-use'             => 'img/terminal.svg',
        'inside-a-skill-what-youre-actually-installing'   => 'img/package.svg',
        'how-to-add-skills-to-openai-codex'               => 'img/terminal.svg',
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

function create_skill(string $name, string $category, string $description, string $command, ?string $postSlug, string $about = '', string $useWhen = '', string $example = '', string $extra = '', ?string $copySettings = null): void
{
    db()->prepare('INSERT INTO skills (name, category, description, command, post_slug, about, use_when, example, extra, copy_settings) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $category, $description, $command, $postSlug, $about, $useWhen, $example, $extra, $copySettings]);
}

function update_skill(int $id, string $name, string $category, string $description, string $command, ?string $postSlug, string $about = '', string $useWhen = '', string $example = '', string $extra = '', ?string $copySettings = null): void
{
    db()->prepare('UPDATE skills SET name = ?, category = ?, description = ?, command = ?, post_slug = ?, about = ?, use_when = ?, example = ?, extra = ?, copy_settings = COALESCE(?, copy_settings) WHERE id = ?')
        ->execute([$name, $category, $description, $command, $postSlug, $about, $useWhen, $example, $extra, $copySettings, $id]);
}

function delete_skill(int $id): void
{
    db()->prepare('DELETE FROM skills WHERE id = ?')->execute([$id]);
}

/**
 * One-time sync (tracked with PRAGMA user_version = 5): removes the placeholder skills
 * that earlier versions seeded and adds the real skills from the catalog. Text already
 * edited from the admin dashboard is never overwritten.
 */
function sync_skill_catalog(PDO $pdo): void
{
    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() >= 6) {
        return;
    }
    require_once __DIR__ . '/skills_catalog.php';

    $catalog = skills_catalog();
    $find   = $pdo->prepare('SELECT id FROM skills WHERE name = ?');
    $fill   = $pdo->prepare("UPDATE skills SET about = COALESCE(NULLIF(about, ''), ?), use_when = COALESCE(NULLIF(use_when, ''), ?), example = COALESCE(NULLIF(example, ''), ?), author = NULL, license = NULL, source_url = ?, command = COALESCE(NULLIF(command, ''), ?) WHERE id = ?");
    $insert = $pdo->prepare('INSERT INTO skills (name, category, description, command, post_slug, about, use_when, example, source_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($catalog as [$name, $category, $desc, $about, $useWhen, $example, $postSlug]) {
        $source  = skill_repo_url($name);
        $command = skill_cli_command($name, $source, 'codex');

        $find->execute([$name]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            $fill->execute([$about, $useWhen, $example, $source, $command, $id]);
        } else {
            $insert->execute([$name, $category, $desc, $command, $postSlug, $about, $useWhen, $example, $source]);
        }
    }
    $pdo->exec('PRAGMA user_version = 6');
}

function get_skill_by_name(string $name): ?array
{
    $st = db()->prepare('SELECT * FROM skills WHERE name = ?');
    $st->execute([$name]);
    return $st->fetch() ?: null;
}

function get_skill_by_post_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM skills WHERE post_slug = ? LIMIT 1');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}
