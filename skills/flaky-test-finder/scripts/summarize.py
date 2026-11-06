#!/usr/bin/env python3
"""Usage: summarize.py <logs-dir>
Counts, per test, in how many runs it failed. Understands pytest ("FAILED path::name"),
Go ("--- FAIL: Name") and Jest/Vitest ("FAIL path" and "✕ name") output."""
import re, sys, pathlib, collections

PATTERNS = [
    re.compile(r"^FAILED\s+(\S+)"),
    re.compile(r"^--- FAIL:\s+(\S+)"),
    re.compile(r"^\s*✕\s+(.+?)(?:\s+\(\d+\s*ms\))?$"),
    re.compile(r"^FAIL\s+(\S+)"),
]
logs = sorted(pathlib.Path(sys.argv[1]).glob("run-*.log"), key=lambda p: int(re.search(r"(\d+)", p.stem).group(1)))
if not logs:
    sys.exit("no run-*.log files found")
fails = collections.defaultdict(set)
for n, log in enumerate(logs, 1):
    for line in log.read_text(errors="replace").splitlines():
        for pat in PATTERNS:
            m = pat.match(line)
            if m:
                fails[m.group(1)].add(n)
                break
total = len(logs)
rows = sorted(fails.items(), key=lambda kv: -len(kv[1]))
print(f"{total} runs, {len(rows)} test(s) failed at least once")
for name, runs in rows:
    k = len(runs)
    kind = "ALWAYS FAILS" if k == total else "FLAKY"
    print(f"{kind:13} {k}/{total} ({k*100//total}%)  {name}")
