---
name: prompt-regression
description: "Use when the user changes a skill, system prompt or instruction and asks whether anything got worse. Replays a saved set of prompts and compares answers."
---

# prompt-regression

Replay saved prompts after a change and show which answers differ.

## Files
Keep the cases in `prompt-cases.json`:
```json
[{"id": "pdf-1", "prompt": "Extract the table on page 3 of report.pdf", "must_include": ["CSV"], "must_not_include": ["OCR"]}]
```

## Steps
1. Read `prompt-cases.json`. If it does not exist, offer to create it from the user's examples.
2. For each case, run the prompt in a fresh session (new context, no memory of earlier cases) and keep the answer.
3. Compare with the stored previous answer, if there is one, and check `must_include` and `must_not_include`.
4. Report a table: id, pass or fail, and for failures the difference in one or two lines.
5. Save the new answers next to the old ones so the next run has something to compare against.

## Rules
- A changed answer is not automatically a regression. Say whether it is better, worse or just different.
- Do not edit the skill or prompt being tested during the run.
