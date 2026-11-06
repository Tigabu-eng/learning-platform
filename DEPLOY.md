# Deploying to learn.tarctpc.com

The platform deploys automatically from GitHub to TARC's existing host. This guide covers the one-time setup.

## Current production setup

This is already done. The rest of this guide explains how, in case it needs redoing.

| Item | Value |
|---|---|
| Host | Namecheap shared hosting (cPanel, CloudLinux, LiteSpeed), server `162.0.229.4` |
| SSH | User `tarcaatk`, port `21098`, key `tarc_deploy_key` authorized in cPanel |
| App folder (`DEPLOY_PATH`) | `/home/tarcaatk/learn.tarctpc.com` |
| Document root | `learn.tarctpc.com/current/public` |
| PHP | 8.3, set account-wide in cPanel → *Select PHP Version*. On this host, extensions only load for the account-wide version, and the WordPress site on `tpd.tarctpc.com` shares it |
| Database | MariaDB 11.4, database and user `tarcaatk_learn` (the password is only in `shared/.env`) |
| Settings | `/home/tarcaatk/learn.tarctpc.com/shared/.env`. Mail is logged and payments are in test mode until a mailbox and Stripe are set up |
| GitHub | `production` environment on github.com/Tigabu-eng/learning-platform |

## How it works

Every push and pull request runs the **CI/CD** workflow (`.github/workflows/tests.yml`):

| Job | What it checks |
|---|---|
| Tests (PHP 8.3, 8.4) | The full test suite on SQLite |
| Tests and migrations (MySQL) | The test suite, migrations and demo seed on MySQL 8.4, the production database |
| Front-end build | `npm ci && npm run build` |
| Deploy to production | Pushes to `main` only, after all checks pass |

The deploy job builds the app on GitHub (Composer packages and front-end assets) and uploads it to the host over SSH. On the host, `deploy/remote-deploy.sh` then:

1. unpacks the build into `releases/<date>-<commit>`
2. links in the shared `.env` and `storage` folder
3. runs the database migrations
4. caches the config, routes and views
5. switches `current` to the new release in one step, so visitors never see a half-finished deploy

The five most recent releases are kept, so a rollback takes one command. Afterwards, the job checks that `https://learn.tarctpc.com/up` responds.

If the production settings from step 4 are missing, the deploy job stops at its first step and lists what is missing.

## 1. Check the host

Ask whoever manages the hosting, or check in cPanel. The host needs:

- [ ] **SSH access** (cPanel → *SSH Access* or *Terminal*). Without SSH this pipeline can't deploy.
- [ ] **PHP 8.3 or newer on the command line.** Run `php -v` over SSH. If it shows an older version, find the 8.3+ binary, which on cPanel is often `/opt/cpanel/ea-php83/root/usr/bin/php` or `/usr/local/bin/ea-php83`. Use that path as `DEPLOY_PHP` in step 4.
- [ ] PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`, `gd`. Check with `php -m`, or turn them on in cPanel → *Select PHP Version* or *MultiPHP INI Editor*.
- [ ] **MySQL 8 or MariaDB 10.6+**
- [ ] The web server's PHP for the subdomain is also 8.3+ (cPanel → *MultiPHP Manager*).
- [ ] For video uploads, raise `upload_max_filesize` and `post_max_size` to at least `512M` (cPanel → *MultiPHP INI Editor*).

## 2. Set up the server (once)

Replace `USER` with the cPanel username. The examples use `/home/USER/learn.tarctpc.com` as the app folder.

1. **Create the subdomain:** cPanel → *Domains* → create `learn.tarctpc.com`. Set its **document root** to `learn.tarctpc.com/current/public`. That folder doesn't exist until the first deploy, which is expected.
2. **Turn on HTTPS:** cPanel → *SSL/TLS Status* → run AutoSSL for `learn.tarctpc.com`.
3. **Create the database:** cPanel → *MySQL Databases*. Create a database and a user, then add the user to the database with **All privileges**.
4. **Create the shared settings file** over SSH:

   ```bash
   mkdir -p ~/learn.tarctpc.com/shared
   nano ~/learn.tarctpc.com/shared/.env
   ```

   Paste the contents of [`deploy/.env.production.example`](deploy/.env.production.example) and fill in every `CHANGE` value. To get `APP_KEY`, run `php artisan key:generate --show` on any computer with the project, and paste the output. Then run `chmod 600 ~/learn.tarctpc.com/shared/.env`.

## 3. Create a deploy key

On your own computer, generate an SSH key used only by GitHub:

```bash
ssh-keygen -t ed25519 -C "github-deploy-tarc" -f tarc_deploy_key -N ""
```

- Add the **public** key, `tarc_deploy_key.pub`, to the host: cPanel → *SSH Access* → *Manage SSH Keys* → *Import Key*, then **Authorize** it. Or append it to `~/.ssh/authorized_keys` on the server.
- Get the host's fingerprint line for `DEPLOY_KNOWN_HOSTS`. Run `ssh-keyscan -p PORT your-server-hostname`, then check the result against the fingerprint shown in cPanel or by your host.

## 4. Add the GitHub secrets and variables

Go to github.com/Tigabu-eng/learning-platform → *Settings* → *Environments* → **New environment** called `production`. Tick *Required reviewers* if you want to approve each deploy. Then add these:

**Environment secrets**

| Name | Value |
|---|---|
| `DEPLOY_HOST` | The server hostname or IP, as used for SSH |
| `DEPLOY_USER` | The cPanel or SSH username |
| `DEPLOY_SSH_KEY` | The full contents of the **private** key `tarc_deploy_key` |
| `DEPLOY_KNOWN_HOSTS` | The output of `ssh-keyscan` from step 3 |

**Environment variables**

| Name | Example | Notes |
|---|---|---|
| `DEPLOY_PATH` | `/home/USER/learn.tarctpc.com` | The app folder on the server |
| `APP_URL` | `https://learn.tarctpc.com` | Used for the post-deploy check |
| `DEPLOY_PHP` | `/opt/cpanel/ea-php83/root/usr/bin/php` | Only if plain `php` on SSH is older than 8.3 |
| `DEPLOY_PORT` | `22` | Many shared hosts use a different SSH port, such as `21098` or `2222` |
| `DEPLOY_PHP_VERSION` | `8.3` | The PHP version used to build the release. Match the host's |

Delete the local key files once the secrets are saved.

## 5. Point the domain

In the DNS for `tarctpc.com`, add a record for `learn` pointing to the host. Use an `A` record with the server's IP, or a `CNAME` if your host recommends one. If the DNS is managed in the same cPanel, step 2.1 usually adds it automatically.

## 6. First deploy

Merge to `main`, or open *Actions* → *CI/CD* → **Run workflow** on `main`. When it finishes, create the real admin account over SSH:

```bash
cd ~/learn.tarctpc.com/current
php artisan tinker
>>> App\Models\User::create(['name' => 'Your Name', 'email' => 'you@tarctpc.com', 'password' => 'a-strong-password', 'role' => 'admin']);
```

To make an existing account a **super admin** (the role that can add or remove admins), for example the first one or to recover access:

```bash
cd ~/learn.tarctpc.com/current
php artisan user:super-admin you@example.com
```

Production is **not** seeded with the demo accounts. Never run plain `db:seed` there.

To load the RTA Certification Program outline (13 modules, sample quizzes and final exam) as a **draft**, run this once. It does nothing if the course already exists, and it uses the first admin as instructor unless `RTA_INSTRUCTOR_EMAIL` is set:

```bash
cd ~/learn.tarctpc.com/current
php artisan db:seed --class=RtaCourseSeeder --force
```

## Rolling back

```bash
cd ~/learn.tarctpc.com
ls -1t releases                                # newest first
ln -sfn releases/<previous release> current
```

A rollback doesn't undo database migrations. If a release changed the database, restore a backup instead.

## Turning on email

Proctor emails, decisions sent to students and new-request alerts are only written to the log until a mailbox is set up (`MAIL_MAILER=log`).

1. In cPanel → **Email Accounts**, create `noreply@tarctpc.com` with a strong password.
2. In `~/learn.tarctpc.com/shared/.env`, set:
   ```
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps
   MAIL_HOST=mail.tarctpc.com
   MAIL_PORT=465
   MAIL_USERNAME=noreply@tarctpc.com
   MAIL_PASSWORD=the-mailbox-password
   MAIL_FROM_ADDRESS="noreply@tarctpc.com"
   ```
3. Run `php artisan config:cache` in `~/learn.tarctpc.com/current`.
4. Test with an exam code generated for yourself, with "Email the code" ticked.

Replies go to `TARC_SUPPORT_EMAIL` (admin@thetarc.com), not to the noreply mailbox.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Deploy fails at "Check deploy settings" | Add the variables and secrets it lists to the `production` environment |
| `Host key verification failed` | `DEPLOY_KNOWN_HOSTS` is missing, or it's for the wrong host or port |
| `Permission denied (publickey)` | The public key isn't authorized in cPanel, or `DEPLOY_USER` is wrong |
| `needs 8.3 or newer` | Set `DEPLOY_PHP` to the host's PHP 8.3+ binary |
| `Missing .../shared/.env` | Do step 2.4 |
| 500 error on the site | Check `~/learn.tarctpc.com/shared/storage/logs/` |
| 404 or a directory listing | The subdomain's document root must be `learn.tarctpc.com/current/public` |
