#!/usr/bin/env python3
"""Usage: check.py <file.html>   Checks head metadata and prints OK / PROBLEM lines."""
import sys
from html.parser import HTMLParser

class P(HTMLParser):
    def __init__(self):
        super().__init__(); self.meta = {}; self.title = ""; self._t = False
        self.canonical = None; self.lang = None; self.h1 = 0; self.viewport = False
    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag == "html": self.lang = a.get("lang")
        elif tag == "title": self._t = True
        elif tag == "h1": self.h1 += 1
        elif tag == "meta":
            key = a.get("name") or a.get("property")
            if key: self.meta[key.lower()] = a.get("content", "")
        elif tag == "link" and a.get("rel") == "canonical": self.canonical = a.get("href")
    def handle_endtag(self, tag):
        if tag == "title": self._t = False
    def handle_data(self, d):
        if self._t: self.title += d

p = P(); p.feed(open(sys.argv[1], encoding="utf-8", errors="replace").read())
def rep(ok, msg): print(("OK       " if ok else "PROBLEM  ") + msg)
t = p.title.strip(); d = p.meta.get("description", "")
rep(bool(t) and 30 <= len(t) <= 60, f"title: {len(t)} chars (aim for 30-60)")
rep(bool(d) and 70 <= len(d) <= 160, f"meta description: {len(d)} chars (aim for 70-160)")
rep(bool(p.canonical), "canonical link " + ("present" if p.canonical else "missing"))
rep(bool(p.lang), "html lang " + (p.lang or "missing"))
rep("viewport" in p.meta, "viewport meta " + ("present" if "viewport" in p.meta else "missing"))
rep(p.h1 == 1, f"h1 count: {p.h1} (should be 1)")
for k in ("og:title", "og:description", "og:image", "twitter:card"):
    rep(k in p.meta, f"{k} " + ("present" if k in p.meta else "missing"))
