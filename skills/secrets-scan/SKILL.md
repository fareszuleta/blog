---
name: secrets-scan
description: "Use when the user asks whether credentials, keys or tokens were committed or leaked in a repository or its history. Read-only; never prints a full secret."
---

# secrets-scan

Search a repository, and optionally its git history, for committed secrets.

## Steps
1. Scan the working tree:
   ```bash
   python3 scripts/scan.py .
   ```
2. Scan history too (slower), since a secret removed from the code is still in old commits:
   ```bash
   python3 scripts/scan.py . --history
   ```
3. For each finding report the file and line (or commit), the kind of secret and the masked value. The script masks values on purpose.
4. Tell the user what to do for each real secret: **revoke and rotate it first**, then remove it from the code. Removing it from git history does not make a leaked key safe.

## Rules
- Never print, copy or send a full secret anywhere.
- Treat matches as leads: say which look like test fixtures or placeholders.
- Do not rewrite history (`git filter-repo`) unless the user asks.
