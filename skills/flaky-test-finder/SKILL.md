---
name: flaky-test-finder
description: "Use when the user reports flaky, intermittent or randomly failing tests, or asks which tests are unreliable. Do not use for a test that fails every time."
---

# flaky-test-finder

Rerun the suite several times and report which tests pass in some runs and fail in others.

## Steps
1. Ask for (or detect) the test command, for example `pytest -q`, `npm test` or `go test ./...`.
2. Rerun it 10 times (use fewer if a run takes more than a minute):
   ```bash
   bash scripts/rerun.sh 10 pytest -q
   ```
   The script prints a logs directory.
3. Summarize that directory:
   ```bash
   python3 scripts/summarize.py /tmp/flaky.XXXXXX
   ```
4. Report each test labelled FLAKY (failed in some runs) with its failure rate, and separately the ones that ALWAYS FAIL (those are broken, not flaky).
5. For the top flaky test, open its log and say what differs between a passing and a failing run (timing, ordering, shared state, network).

## Rules
- Do not edit tests or retry logic unless asked.
- State how many runs you did. Fewer runs mean weaker evidence.
