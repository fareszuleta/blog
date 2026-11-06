#!/usr/bin/env python3
"""Usage: group.py <git-range>   e.g. group.py v1.3.0..HEAD
Prints commit subjects grouped as Added / Fixed / Changed (by conventional-commit prefix
or leading verb). The agent rewrites each line as one plain sentence."""
import re, subprocess, sys

rng = sys.argv[1]
log = subprocess.run(["git", "log", "--no-merges", "--pretty=format:%h\t%s", rng],
                     capture_output=True, text=True, check=True).stdout.splitlines()
groups = {"Added": [], "Fixed": [], "Changed": []}
for row in log:
    sha, _, subj = row.partition("\t")
    low = subj.lower()
    if re.match(r"(feat|add)\b|add(ed|s)?\b", low): g = "Added"
    elif re.match(r"(fix|bug)\b|fix(ed|es)?\b", low): g = "Fixed"
    else: g = "Changed"
    groups[g].append(f"- {subj} ({sha})")
for name, items in groups.items():
    if items:
        print(f"### {name}\n" + "\n".join(items) + "\n")
