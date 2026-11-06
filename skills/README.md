# Skills

Real, public agent skills, copied **unmodified** from their original repositories and kept here so
they can be installed from this repo. Each folder has the original `SKILL.md`, the original license
and an `ATTRIBUTION.md` with the source and commit. They work with OpenAI Codex and Claude Code
(`git-guardrails-claude-code` is Claude Code only, because it uses Claude Code hooks).

Credit and thanks to the authors. For updates and issues, use the originals.

## Install one

```bash
d=$(mktemp -d) && git clone --depth 1 https://github.com/fareszuleta/blog.git "$d"
mkdir -p ~/.agents/skills                      # Codex  (Claude Code: ~/.claude/skills)
cp -r "$d/skills/frontend-design" ~/.agents/skills/
```

Or inside Codex: `$skill-installer install https://github.com/fareszuleta/blog/tree/main/skills/frontend-design`

Read the folder before you trust it: a skill can ship scripts.

## Included

| Skill | Author | License | Source |
|---|---|---|---|
| [frontend-design](./frontend-design) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/frontend-design) |
| [web-artifacts-builder](./web-artifacts-builder) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/web-artifacts-builder) |
| [theme-factory](./theme-factory) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/theme-factory) |
| [webapp-testing](./webapp-testing) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/webapp-testing) |
| [mcp-builder](./mcp-builder) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/mcp-builder) |
| [skill-creator](./skill-creator) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/skill-creator) |
| [internal-comms](./internal-comms) | Anthropic | Apache-2.0 | [original](https://github.com/anthropics/skills/tree/main/skills/internal-comms) |
| [test-driven-development](./test-driven-development) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/test-driven-development) |
| [systematic-debugging](./systematic-debugging) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/systematic-debugging) |
| [requesting-code-review](./requesting-code-review) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/requesting-code-review) |
| [receiving-code-review](./receiving-code-review) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/receiving-code-review) |
| [using-git-worktrees](./using-git-worktrees) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/using-git-worktrees) |
| [brainstorming](./brainstorming) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/brainstorming) |
| [writing-plans](./writing-plans) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/writing-plans) |
| [verification-before-completion](./verification-before-completion) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/verification-before-completion) |
| [writing-skills](./writing-skills) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/writing-skills) |
| [dispatching-parallel-agents](./dispatching-parallel-agents) | Jesse Vincent | MIT | [original](https://github.com/obra/superpowers/tree/main/skills/dispatching-parallel-agents) |
| [git-guardrails-claude-code](./git-guardrails-claude-code) | Matt Pocock | MIT | [original](https://github.com/mattpocock/skills/tree/main/skills/misc/git-guardrails-claude-code) |
| [grilling](./grilling) | Matt Pocock | MIT | [original](https://github.com/mattpocock/skills/tree/main/skills/productivity/grilling) |
| [handoff](./handoff) | Matt Pocock | MIT | [original](https://github.com/mattpocock/skills/tree/main/skills/productivity/handoff) |
| [setup-pre-commit](./setup-pre-commit) | Matt Pocock | MIT | [original](https://github.com/mattpocock/skills/tree/main/skills/misc/setup-pre-commit) |
| [seo-audit](./seo-audit) | Corey Haines | MIT | [original](https://github.com/coreyhaines31/marketingskills/tree/main/skills/seo-audit) |
| [copywriting](./copywriting) | Corey Haines | MIT | [original](https://github.com/coreyhaines31/marketingskills/tree/main/skills/copywriting) |
| [cro](./cro) | Corey Haines | MIT | [original](https://github.com/coreyhaines31/marketingskills/tree/main/skills/cro) |
