<?php
// Add starter guides once. An existing publication owns its slug.
$guides = [
    ['install-python-on-windows', 'Install Python on Windows', 'Set up Python with WinGet and verify it in PowerShell.', 'img/package.svg',
        "Open PowerShell on Windows 10 or 11 with WinGet available. Install the Python install manager.\n\n```\nwinget install 9NQ7512CXL7T --source msstore\n```\nOpen a new PowerShell window and install the Python 3 runtime.\n\n```\npymanager install 3\n```\nCheck the installed version.\n\n```\npython --version\n```"],
    ['install-codex-cli', 'Install Codex CLI', 'Install the command-line tool with npm and start your first session.', 'img/terminal.svg',
        "Open your terminal on a machine with Node.js and npm installed. Check that npm is available.\n\n```\nnpm --version\n```\nInstall Codex CLI for your user with npm.\n\n```\nnpm install -g @openai/codex\n```\nCheck the installation.\n\n```\ncodex --version\n```\nOpen your project directory, then launch Codex and follow the sign-in prompt.\n\n```\ncodex\n```"]
];
$insert = $pdo->prepare('INSERT INTO posts (slug, title, excerpt, cover, body) VALUES (?, ?, ?, ?, ?) ON CONFLICT(slug) DO NOTHING');
foreach ($guides as $guide) {
    $insert->execute($guide);
}

