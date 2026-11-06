---
name: log-triage
description: "Use when the user shares a large log file or output and asks what is going wrong or what the top errors are. Do not use for tracing a single request."
---

# log-triage

Group a noisy log by error signature and rank the causes.

## Steps
1. Run the grouper on the log file:
   ```bash
   python3 scripts/triage.py app.log 5
   ```
   It replaces numbers, ids and quoted values so similar errors land in one group.
2. For each of the top groups report: count, first and last time seen, one example line.
3. For the top one or two, read the lines around an example in the log to find what happened just before.
4. Say which group is probably the root cause and which are consequences (for example "db timeout" causing "request failed").

## Rules
- Do not paste secrets or personal data from the log into your answer; mask them.
- Say how many lines the log had and whether you read all of it.
