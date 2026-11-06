---
name: changelog-draft
description: "Use when the user asks for a changelog, release notes or a summary of changes between two tags, branches or commits. Do not use for a single commit message."
---

# changelog-draft

Turn a range of commits into a changelog entry grouped by the kind of change.

## Steps
1. Get the range from the user (for example `v1.3.0..HEAD`). If they give none, use the last tag: `git describe --tags --abbrev=0`.
2. Group the commits:
   ```bash
   python3 scripts/group.py v1.3.0..HEAD
   ```
3. Rewrite each line as one plain sentence about what changed for the user, not what the developer did. Drop commits that change nothing visible (formatting, CI) or list them under "Internal".
4. Move any line the script put in the wrong group (it guesses from the first word).
5. Output Markdown with `### Added`, `### Changed`, `### Fixed`, and the version and date as the heading.

## Rules
- Do not invent changes. Every line must trace to a commit.
- Keep the commit hash only if the user asks for it.
