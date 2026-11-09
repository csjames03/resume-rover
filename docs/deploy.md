# Deploying for free: Render + Neon + Groq

The whole stack fits in free tiers:

| Piece                   | Service                                                | Free tier notes                                                               |
| ----------------------- | ------------------------------------------------------ | ----------------------------------------------------------------------------- |
| App, queue, scheduler   | [Render](https://render.com)                           | One Docker web service. Sleeps after 15 min idle; the next visit takes ~1 min |
| PostgreSQL              | [Neon](https://neon.tech)                              | 0.5 GB. Plenty for this app                                                   |
| AI                      | [Groq](https://console.groq.com)                       | Rate-limited per model; the app tracks and explains the limits                |
| Résumé files (optional) | [Cloudflare R2](https://developers.cloudflare.com/r2/) | 10 GB. Render's disk is wiped on every restart, so files need a bucket        |

Everything runs from one container (`Dockerfile`): nginx + PHP-FPM, the queue worker and the scheduler, managed by supervisord. Each boot caches config, runs migrations, and loads the skills dictionary and starter job sources on a fresh database (`app:seed-defaults`).

## 1. Put the code on GitHub

Render deploys from a Git repository. Create an empty GitHub repo, then:

```bash
git remote add origin https://github.com/<you>/resume-rover.git
git push -u origin main
```

## 2. Create the database (Neon)

1. Create a project. Pick the region closest to your Render region (Singapore → `AWS Asia Pacific (Singapore)`).
2. On the dashboard, open **Connect**, turn **Connection pooling off**, and copy the connection string. It looks like `postgresql://user:password@ep-xxx.ap-southeast-1.aws.neon.tech/neondb?sslmode=require`.

Use the direct (unpooled) string: Laravel uses prepared statements, which don't mix well with PgBouncer's transaction mode.

## 3. Get a Groq key

[console.groq.com/keys](https://console.groq.com/keys) → **Create API key**. The free tier needs no card.

## 4. (Optional) Create a bucket for résumé files (Cloudflare R2)

Without a bucket, uploads still work and parsed résumés are kept (they're in the database), but the original file disappears when Render restarts. That breaks two things: attaching your résumé to application emails (the app refuses to send rather than silently dropping it) and re-parsing.

1. R2 → **Create bucket** (keep it private). Cloudflare may ask for a payment method even on the free plan.
2. R2 → **Manage API tokens** → **Create API token** with _Object Read & Write_ on that bucket only.
3. Note the **Access Key ID**, **Secret Access Key**, and the S3 endpoint `https://<account-id>.r2.cloudflarestorage.com`.

To skip this, set `RESUME_DISK=local` in Render instead of `s3`.

## 5. Generate an app key

```bash
php artisan key:generate --show
```

Copy the whole `base64:…` value. Keep it secret, and don't change it later: it encrypts sessions and the stored Gmail tokens.

## 6. Create the service (Render)

1. **New → Blueprint**, connect the GitHub repo. Render reads `render.yaml`.
2. Fill in the secrets it asks for:

| Variable                                                                   | Value                                                                                                   |
| -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `APP_KEY`                                                                  | from step 5                                                                                             |
| `APP_URL`                                                                  | `https://resume-rover.onrender.com` (the URL Render shows; fix it after the first deploy if it differs) |
| `DB_URL`                                                                   | Neon connection string from step 2                                                                      |
| `GROQ_API_KEY`                                                             | from step 3                                                                                             |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT` | from step 4 (or leave empty and set `RESUME_DISK=local`)                                                |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`                                 | optional, see step 8                                                                                    |

3. Deploy. The first build takes several minutes. When the log shows `supervisord started`, open the URL.

If the service URL differs from the `APP_URL` you entered, update `APP_URL` and redeploy. Asset URLs and the Gmail redirect are built from it.

## 7. Make yourself admin

Register your account on the live site first. Then grant admin from your own machine, pointing artisan at Neon for one command:

```powershell
# PowerShell
$env:DB_URL = "postgresql://...neon.tech/neondb?sslmode=require"
php artisan users:admin you@example.com
Remove-Item Env:DB_URL
```

Admins manage the shared job sources. To stop strangers signing up and using your Groq quota, set `REGISTRATION_ENABLED=false` in Render afterwards.

## 8. (Optional) Gmail on the live site

In the Google Cloud console, open your OAuth client and add:

- Authorized JavaScript origin: `https://resume-rover.onrender.com`
- Authorized redirect URI: `https://resume-rover.onrender.com/settings/email/callback`

Then set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in Render. While the Google app is in _Testing_ mode, only the accounts listed under **Test users** can connect Gmail, and connections expire after 7 days.

## What to expect on free tiers

- **Cold starts.** Render stops the service after 15 minutes without traffic. The queue worker and scheduler stop too, so jobs are only fetched while someone has the app open. Fine for a portfolio demo.
- **Groq limits.** Each model has per-minute token and per-day request limits. The app reads Groq's rate-limit headers, waits instead of failing, gives every account `AI_USER_DAILY_LIMIT` actions a day, and tells users when to try again. Settings → AI providers shows the live numbers. If one account's tailoring hits the per-minute token limit often, try a model with a larger allowance via `GROQ_MODEL`.
- **Email.** `MAIL_MAILER=log` means password-reset emails only go to the log. Add an SMTP provider (Resend, Brevo, …) if you need them.
- **Logs.** Everything (web, queue, scheduler) streams to Render's **Logs** tab.
