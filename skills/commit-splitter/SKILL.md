---
name: commit-splitter
description: "Use when the user has a large set of uncommitted changes and wants them split into small, logical commits. Do not use for amending or rewriting history."
---

# commit-splitter

Group working-tree changes by purpose and propose one commit for each.

## Steps
1. Read the changes: `git status --short` and `git diff` (plus `git diff --staged`).
2. Group files and hunks by purpose (a feature, a fix, a refactor, docs, tests). One group should answer one question: "why did this change?".
3. Show the plan first: for each commit the files (or hunks) and the message, in the style of the repo's recent `git log`.
4. Wait for the user to agree. Then, for each group: stage only its files (`git add path` or `git add -p`), commit, and check `git status`.

## Rules
- Do not commit before the user approves the plan.
- Never use `git reset --hard`, `git checkout .` or force pushes.
- Never commit files that look like secrets (`.env`, keys). Tell the user instead.
