# Closing Notes

A dynamic sales landing page with a blog, built with PHP 8.3 + SQLite. Includes notes, comments, email capture and an admin dashboard.

## Structure

```
public/     web root: index.php (controller), style.css
src/        db.php (SQLite and queries), helpers.php (CSRF, session, utilities), views.php (HTML)
data/       SQLite database (created automatically)
Dockerfile  PHP + Apache image
```

## Run with Docker

```bash
cd ~/Projects/blog-ventas
docker build -t blog-ventas .
docker volume create blog-data
docker run -d --name blog-ventas -p 8080:80 \
  --env-file .env \
  -v blog-data:/var/www/data \
  --restart unless-stopped \
  blog-ventas
```

Open http://localhost:8080

- **Public:** landing page, notes, comments and email capture.
- **Admin:** http://localhost:8080/index.php?r=admin. Sign in with `ADMIN_PASSWORD` to publish and delete notes, delete comments and view captured emails.

Copy `.env.example` to `.env` and set `ADMIN_PASSWORD`. If it is not set, login is disabled. `.env` is git-ignored and must never be committed.

## Deploy on Render

1. In Render: **New → Blueprint**, then select this GitHub repo. Render reads `render.yaml`.
2. In the service's **Environment** tab, set `ADMIN_PASSWORD` to a strong password.
3. Deploy. The SQLite database lives on the persistent disk at `/var/www/data`.

`render.yaml` deploys with `CTF_MODE=1` for the public CTF target. For a plain sales site, change that value to `0` before deploying.

## CTF mode (intentionally vulnerable)

Setting `CTF_MODE=1` turns on a **deliberate stored XSS** in comments (author and body are printed without escaping, on post pages and in the admin dashboard). It exists for security training and CTF exercises.

- Keep `CTF_MODE=0` (the default) on any real or public deployment.
- Only run `CTF_MODE=1` on a local machine or an isolated lab network.

## Security included

- Prepared statements (PDO) against SQL injection.
- Output escaping (`h()`) against XSS. User content is never printed unescaped.
- CSRF token on every POST form.
- Honeypot field on comment and email forms.
- One-second delay on wrong passwords.
- `X-Frame-Options`, `X-Content-Type-Options` and `Referrer-Policy` headers.

## Before going live on the internet

- Serve over **HTTPS** (a reverse proxy such as Caddy or Nginx, or your hosting provider's).
- Rate-limit comments and email sign-ups per IP if you get spam.
- Add moderation: comments currently publish immediately.
- Replace the testimonial placeholder on the home page with a real one.
- Connect an email provider: captured emails are stored, but no welcome email or template is sent yet.
