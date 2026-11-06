---
name: responsive-check
description: "Use when the user says a page looks wrong on mobile or tablet, or asks for a responsive review of a URL. Do not use for performance."
---

# responsive-check

Open a page at three widths and report what breaks.

## Steps
1. Make sure the page is reachable (a local server or a URL).
2. Take a screenshot at each width with a headless browser:
   ```bash
   npx playwright screenshot --viewport-size=390,844 "$URL" phone.png
   npx playwright screenshot --viewport-size=768,1024 "$URL" tablet.png
   npx playwright screenshot --viewport-size=1440,900 "$URL" desktop.png
   ```
3. Look at each image. List: horizontal scroll, text cut off or overlapping, images wider than the screen, buttons and links smaller than 44px, content that disappears.
4. For each problem give the likely CSS cause (a fixed `width`, a missing `flex-wrap`, a missing `max-width: 100%`).

## Rules
- Report first; fix only if asked.
- Name the widths you tested.
