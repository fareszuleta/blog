#!/usr/bin/env python3
"""Usage: extract.py <file.css> [more.css ...]
Counts repeated colors, font sizes, font families and spacing values. Read-only:
the agent decides the names and writes the tokens file."""
import re, sys, collections

COLOR = re.compile(r"#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|hsla?\([^)]*\)")
SIZE = re.compile(r"font-size\s*:\s*([^;}]+)")
FAMILY = re.compile(r"font-family\s*:\s*([^;}]+)")
SPACE = re.compile(r"(?:margin|padding|gap)(?:-[a-z]+)?\s*:\s*([^;}]+)")
colors, sizes, fams, spaces = (collections.Counter() for _ in range(4))
for path in sys.argv[1:]:
    css = re.sub(r"/\*.*?\*/", "", open(path, errors="replace").read(), flags=re.S)
    colors.update(c.lower() for c in COLOR.findall(css))
    sizes.update(s.strip() for s in SIZE.findall(css))
    fams.update(f.strip() for f in FAMILY.findall(css))
    for v in SPACE.findall(css):
        spaces.update(re.findall(r"-?\d*\.?\d+(?:px|rem|em)", v))
for title, c in (("colors", colors), ("font sizes", sizes), ("font families", fams), ("spacing values", spaces)):
    print(f"\n{title} ({len(c)} distinct)")
    for value, n in c.most_common(12):
        print(f"  {n:3}x  {value}")
