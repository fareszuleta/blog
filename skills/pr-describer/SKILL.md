---
name: pr-describer
description: "Use when the user asks for a pull request description, a PR summary, or review notes for a branch. Do not use to open the PR."
---

# pr-describer

Write a pull request description from the diff.

## Steps
1. Find the base: `git merge-base HEAD origin/main` (or the repo's default branch).
2. Read `git log --oneline BASE..HEAD` and `git diff BASE..HEAD --stat`, then the diff of the important files.
3. Write:
   - **Title:** under 70 characters, says what changed.
   - **Why:** the problem this solves, in one or two sentences.
   - **What changed:** 3 to 6 bullets, by behavior, not by file.
   - **Risky parts:** migrations, config changes, anything hard to roll back.
   - **How to test:** commands or clicks a reviewer can follow.
4. Show it and let the user edit it.

## Rules
- Do not create the PR or push anything.
- Do not claim tests pass unless you ran them.
