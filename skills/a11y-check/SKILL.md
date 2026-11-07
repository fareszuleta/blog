---
name: a11y-check
description: "Use when the user mentions accessibility, WCAG, screen readers, color contrast or keyboard navigation for a page or component. Do not use for visual design feedback."
---

# a11y-check

Review HTML and CSS for common accessibility failures and list each with the line to fix.

## Checks
- Images without `alt` (decorative images need `alt=""`).
- Form inputs without a `<label>` or `aria-label`.
- Buttons and links with no text (icon-only controls need an accessible name).
- Heading order that skips levels; more than one `h1`.
- Missing `lang` on `<html>`.
- Color contrast below 4.5:1 for body text and 3:1 for large text. Compute it from the actual hex values.
- Focus: `outline: none` with no replacement style; positive `tabindex` values.
- Elements with click handlers that are not buttons or links (`div onclick`).

## Steps
1. Read the HTML and the CSS it uses.
2. Run through the checks above.
3. Report each issue as: file:line, what is wrong, the exact fix.

## Rules
- Report first; apply fixes only if asked.
- Say what you could not check statically (screen reader behavior, real focus order) and suggest testing it by hand.
