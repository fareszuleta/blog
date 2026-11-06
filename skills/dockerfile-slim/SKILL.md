---
name: dockerfile-slim
description: "Use when the user says a Docker image is too big or a build is slow, or asks to review a Dockerfile. Suggests edits; does not rebuild."
---

# dockerfile-slim

Review a Dockerfile for size and build speed.

## Checks
- A heavy base image where a slim or alpine variant works (`python:3.12-slim`, `node:20-slim`).
- No multi-stage build: compilers and dev dependencies end up in the final image.
- `COPY . .` before installing dependencies, which breaks the layer cache on every code change.
- Package manager caches left in the image (`apt-get` without `rm -rf /var/lib/apt/lists/*`, `pip` without `--no-cache-dir`).
- Many separate `RUN` lines that could be one.
- A missing `.dockerignore` (`.git`, `node_modules`, `*.sqlite` get copied in).
- Running as root without a reason.

## Steps
1. Read the Dockerfile and `.dockerignore`.
2. If Docker is available, record the current size: `docker image ls NAME`.
3. List each problem with the exact replacement lines.

## Rules
- Suggest the changes; do not rebuild or push images unless asked.
- Warn when a slimmer base image may lack a library the app needs.
