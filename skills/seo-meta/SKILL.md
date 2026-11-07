---
name: seo-meta
description: "Use when the user asks for an SEO check of a page, or why a link preview (Open Graph, Twitter card) looks wrong. Do not use for keyword research."
---

# seo-meta

Check a page's head metadata.

## Steps
1. Get the HTML file (or save the page with `curl -s URL > page.html`).
2. Run the checker:
   ```bash
   python3 scripts/check.py page.html
   ```
3. For each PROBLEM line, write the exact tag to add or the text to shorten. Example: `<meta name="description" content="...">` at 70 to 160 characters.
4. Suggest values written for this page, not generic ones, and keep the title under 60 characters.

## Rules
- Do not edit the page unless asked.
- The checker reads one file; say if pages built by JavaScript need a rendered check instead.
