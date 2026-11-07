---
name: dependency-audit
description: "Use when the user asks about outdated, vulnerable or risky dependencies in a project. Reports only; never upgrades."
---

# dependency-audit

List vulnerable and outdated dependencies, ranked by risk.

## Steps
1. Detect the ecosystem from the lockfile and run its audit (read-only):
   - npm: `npm audit --json` and `npm outdated`
   - Python: `pip install pip-audit && pip-audit` and `pip list --outdated`
   - Go: `govulncheck ./...`
   - Ruby: `bundle audit check`
2. Rank by severity (critical, high, moderate, low), then by whether the package is a direct or a transitive dependency.
3. For each finding: package, installed version, fixed version, severity, and whether it is reachable (is the vulnerable function used by this project? say "unknown" if you did not check).
4. Suggest the upgrade command for each, but do not run it.

## Rules
- Do not run `npm audit fix`, `npm update` or any command that changes the lockfile.
- Mark major-version bumps as breaking risks.
