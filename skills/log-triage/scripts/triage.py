#!/usr/bin/env python3
"""Usage: triage.py <logfile> [top-n]
Groups error lines by signature (numbers, ids and quoted values replaced) and prints the
top groups with count, first/last timestamp seen and one example line."""
import re, sys, collections

ERR = re.compile(r"\b(error|exception|fatal|critical|traceback|panic|failed)\b", re.I)
TS = re.compile(r"\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}")
NORMALIZE = [
    (TS, "<ts>"),
    (re.compile(r"\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b", re.I), "<uuid>"),
    (re.compile(r"\b0x[0-9a-f]+\b", re.I), "<hex>"),
    (re.compile(r"(?<![A-Za-z_])\d+"), "<n>"),
    (re.compile(r"'[^']*'|\"[^\"]*\""), "<str>"),
]
path = sys.argv[1]
top = int(sys.argv[2]) if len(sys.argv) > 2 else 5
groups = collections.OrderedDict()
with open(path, errors="replace") as fh:
    for line in fh:
        line = line.rstrip("\n")
        if not ERR.search(line):
            continue
        sig = line
        for rx, rep in NORMALIZE:
            sig = rx.sub(rep, sig)
        sig = re.sub(r"\s+", " ", sig).strip()
        g = groups.setdefault(sig, {"n": 0, "first": None, "last": None, "ex": line})
        g["n"] += 1
        m = TS.search(line)
        if m:
            g["first"] = g["first"] or m.group(0)
            g["last"] = m.group(0)
ranked = sorted(groups.items(), key=lambda kv: -kv[1]["n"])[:top]
print(f"{sum(g['n'] for g in groups.values())} error lines, {len(groups)} distinct signatures")
for sig, g in ranked:
    print(f"\n{g['n']}x  first={g['first'] or '-'}  last={g['last'] or '-'}\n  {g['ex'][:200]}")
