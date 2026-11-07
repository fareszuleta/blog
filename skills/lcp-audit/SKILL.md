---
name: lcp-audit
description: "Use when the user asks why a specific URL is slow, mentions LCP, Largest Contentful Paint, first paint or Core Web Vitals. Do not use for backend latency or database tuning."
---

# lcp-audit

Find what delays Largest Contentful Paint on one URL and rank the fixes by effort.

## Steps
1. Run Lighthouse against the URL (mobile, performance only):
   ```bash
   npx lighthouse "$URL" --only-categories=performance --output=json --output-path=/tmp/lh.json --chrome-flags="--headless" --quiet
   ```
2. Read `audits["largest-contentful-paint"]` for the value and `audits["largest-contentful-paint-element"]` for the element.
3. Check these audits for the cause: `render-blocking-resources`, `uses-responsive-images`, `uses-optimized-images`, `font-display`, `server-response-time`, `prioritize-lcp-image`.
4. Report a table: fix, expected gain, effort (minutes / hours / days). Order by gain per effort, cheapest first.

## Rules
- Report only. Do not edit the site's code unless the user asks.
- Run Lighthouse at least twice if the numbers look unstable and report the range.
- Say which URL and which device profile you measured.
