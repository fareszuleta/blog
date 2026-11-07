---
name: design-tokens
description: "Use when the user asks to clean up CSS, find the palette or spacing scale of a stylesheet, or set up design tokens. Do not use to redesign a page."
---

# design-tokens

Find the values a stylesheet repeats and write them as one tokens file.

## Steps
1. Count the values:
   ```bash
   python3 scripts/extract.py style.css
   ```
2. Group near-duplicates (`#fff` and `#ffffff`; `0.9rem` and `0.92rem`) and propose one value for each group.
3. Name tokens by role, not by value: `--ink`, `--muted`, `--line`, `--space-2`, not `--green-500`.
4. Write the tokens as a `:root { ... }` block in a new file and show it. Do not rewrite the existing rules.
5. List the places in the CSS that could use each token, as a count.

## Rules
- Do not change the stylesheet unless the user asks to apply the tokens.
- Keep the scale small: about 6 colors, 5 spacing steps, 5 font sizes.
