#!/usr/bin/env python3
"""Read-only secret scanner. Usage: scan.py [path] [--history]
Prints file:line and the pattern name. Secrets are masked, never printed in full."""
import os, re, subprocess, sys

PATTERNS = {
    "AWS access key id": r"\bAKIA[0-9A-Z]{16}\b",
    "GitHub token": r"\bgh[pousr]_[A-Za-z0-9]{36,}\b",
    "Slack token": r"\bxox[baprs]-[A-Za-z0-9-]{10,}\b",
    "Stripe live key": r"\b[sr]k_live_[A-Za-z0-9]{16,}\b",
    "Google API key": r"\bAIza[0-9A-Za-z_-]{35}\b",
    "Private key block": r"-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----",
    "Hard-coded password/secret": r"(?i)\b(?:password|passwd|secret|api[_-]?key|token)\b\s*[:=]\s*['\"][^'\"\s]{8,}['\"]",
}
COMPILED = {k: re.compile(v) for k, v in PATTERNS.items()}
SKIP_DIRS = {".git", "node_modules", "vendor", ".venv", "venv", "__pycache__", "dist", "build"}

def mask(s):
    s = s.strip()
    return s[:4] + "…" + "*" * 4 if len(s) > 8 else "****"

def scan_text(label, lineno, text, out):
    for name, rx in COMPILED.items():
        m = rx.search(text)
        if m:
            out.append(f"{label}:{lineno}  {name}  {mask(m.group(0))}")

def scan_tree(root):
    out = []
    for d, dirs, files in os.walk(root):
        dirs[:] = [x for x in dirs if x not in SKIP_DIRS]
        for f in files:
            p = os.path.join(d, f)
            try:
                if os.path.getsize(p) > 1_000_000:
                    continue
                with open(p, "r", encoding="utf-8") as fh:
                    for i, line in enumerate(fh, 1):
                        scan_text(p, i, line, out)
            except (UnicodeDecodeError, OSError):
                continue
    return out

def scan_history(root):
    out = []
    proc = subprocess.run(["git", "-C", root, "log", "-p", "--all", "--no-color", "--pretty=format:\x01COMMIT %h"],
                          capture_output=True, text=True, errors="replace")
    commit = "?"
    for line in proc.stdout.splitlines():
        if line.startswith("\x01COMMIT "):
            commit = line[len("\x01COMMIT "):]
        elif line.startswith("+") and not line.startswith("+++"):
            scan_text(f"commit {commit}", 0, line[1:], out)
    return out

if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    root = args[0] if args else "."
    findings = scan_tree(root)
    if "--history" in sys.argv:
        findings += scan_history(root)
    print("\n".join(findings) if findings else "no findings")
    print(f"{len(findings)} finding(s)", file=sys.stderr)
    sys.exit(1 if findings else 0)
