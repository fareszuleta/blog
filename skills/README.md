# Skills

Real, installable agent skills. Each folder has a `SKILL.md` (name and description in the
frontmatter, then the instructions) and, where a script makes the result repeatable, a
`scripts/` folder. They work with OpenAI Codex and Claude Code.

## Install one

```bash
d=$(mktemp -d) && git clone --depth 1 https://github.com/fareszuleta/blog.git "$d"
mkdir -p ~/.agents/skills                      # Codex  (Claude Code: ~/.claude/skills)
cp -r "$d/skills/secrets-scan" ~/.agents/skills/
```

Or, inside Codex: `$skill-installer install https://github.com/fareszuleta/blog/tree/main/skills/secrets-scan`

Read the folder before you trust it: a skill can ship scripts.

## Skills with scripts (tested)

| Skill | Script |
|---|---|
| flaky-test-finder | `rerun.sh`, `summarize.py` |
| secrets-scan | `scan.py` (read-only, masks values) |
| log-triage | `triage.py` |
| seo-meta | `check.py` |
| design-tokens | `extract.py` |
| changelog-draft | `group.py` |

The other skills are instruction-only.
