---
name: readme-doctor
description: "Use when the user asks to verify or fix the setup or install steps in a README. Runs commands in a throwaway copy, never in the real project."
---

# readme-doctor

Check that the commands in a README actually work.

## Steps
1. Read the README and list the shell commands in the install and run sections, in order.
2. Show the list to the user. Mark any command that is destructive, needs secrets, or touches the network or system (`sudo`, `rm`, `docker run` with volumes, deploy commands) and **skip those**.
3. Copy the project to a temp dir: `cp -r . /tmp/readme-doctor && cd /tmp/readme-doctor`.
4. Run the remaining commands one by one in that copy. Record exit code and the last lines of output.
5. Report which commands failed, why, and the corrected line for the README. Note missing prerequisites (a tool version, an env var) that the README does not mention.

## Rules
- Never run commands in the original project directory.
- Never run commands the user has not seen.
