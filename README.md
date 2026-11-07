# Uptime installation library

Uptime runs on PHP 8.3, Apache and SQLite. Readers can browse guides and skills without signing in. Editors can publish and edit posts, manage skills, moderate comments and view email registrations.

## Change a Copy button

Open **Editor login**, sign in, then find the skill under **Skills** on the dashboard. Choose **Edit**, change **Install command**, and save. The primary install step and Quick install both display and copy the saved command. For tool guides, edit the note and put each command between its own pair of triple-backtick lines. Each block gets a Copy button. Multi-line commands are supported.

## Editor access

The existing shared login uses the Render secret `ADMIN_PASSWORD` and a blank email field. For individual editors, set `EDITOR_ACCOUNTS` as a secret JSON object mapping lowercase email addresses to PHP password hashes. Generate each hash with PHP's `password_hash($password, PASSWORD_DEFAULT)`. Each configured editor has full dashboard access. Store credentials only in your service environment.

## Update the existing Render service

Merge these source changes into the branch connected to the current service, then deploy that commit in Render. Retain the existing persistent disk mounted at `/var/www/data` and the service environment. The upgrade reads the existing SQLite database in place. It adds Python and Codex CLI starter posts once, preserving any post that already uses either slug.

## Run locally with Docker

Copy `.env.example` to `.env` and set your local admin password. From the repository root run:

```sh
docker build -t uptime-library .
docker run --rm -p 8080:80 --env-file .env -v uptime-data:/var/www/data uptime-library
```

Open http://localhost:8080. Registration emails are stored in the database. The application does not send a newsletter.

