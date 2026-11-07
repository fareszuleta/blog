---
name: memory-gc
description: "Use when the user asks to clean, audit or prune an agent memory file, or to find stale memories. Do not use to add new memories."
---

# memory-gc

Find memory entries that are probably no longer true, and let the user decide what to remove.

## Steps
1. Locate the memory file or folder the user means (for example `MEMORY.md` and the files it links to). Ask if it is not obvious.
2. For every entry, note its date, its stated expiry if it has one, and whether it describes a fact that changes (a version, a deadline, a person's role, a path).
3. Flag an entry when: its expiry date has passed; it mentions a date that is now in the past; it names a file, function or flag that no longer exists (check with `ls` or `grep`); or it contradicts a newer entry.
4. Show the flagged entries as a list: entry, reason, suggested action (delete / update / keep).

## Rules
- Never delete or edit memory without the user's explicit yes for each change.
- When unsure, mark it "keep, unverified" instead of flagging it.
