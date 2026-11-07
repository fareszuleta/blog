---
name: deploy-rollback
description: "Use when the user says a deploy broke something and asks how to roll back or what the last good version was. Stages the rollback command and does not run it."
---

# deploy-rollback

Find the last known-good deploy and prepare the rollback command. A person runs it.

## Steps
1. Identify how the service is deployed (git tags, a container registry, Kubernetes, a platform such as Render or Heroku). Ask if unclear.
2. List recent deploys with the tool that applies:
   - git: `git log --oneline -15` and `git tag --sort=-creatordate | head`
   - Kubernetes: `kubectl rollout history deployment/NAME`
   - Render: the service's Deploys tab (use "Rollback" on the last live deploy)
3. Pick the last deploy before the problem started. Say how you decided (time of the first error, last passing health check).
4. Write the exact command, for example `kubectl rollout undo deployment/NAME --to-revision=7`, with a one-line note on what it changes and what data it does not undo (migrations, queued jobs).
5. Add a check to run after: the health URL or command that proves it worked.

## Rules
- Never execute the rollback. Print it for the user.
- Warn clearly if a database migration ran in the bad deploy.
