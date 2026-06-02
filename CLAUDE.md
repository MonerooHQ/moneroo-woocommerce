# CLAUDE.md

Guidance for Claude Code and other AI agents working in this repository.

## Project overview

This is the Moneroo for WooCommerce plugin — a WordPress payment gateway that lets WooCommerce stores accept Mobile Money, credit card, and bank transfers across Africa via the Moneroo payment platform. It is distributed on the WordPress Plugin Directory (slug: `moneroo`) and consumed by WordPress site operators who install it through the WP admin. The plugin wraps the `moneroo/moneroo-php` SDK and registers a custom `WC_Payment_Gateway` subclass.

## Tech stack

- PHP 7.4+ (minimum required; CI tests against 8.1)
- WordPress 5.0+ / WooCommerce 4.0+ (tested up to WP 6.8 / WC 9.8)
- `moneroo/moneroo-php` v0.1.0 — official Moneroo PHP SDK
- `axazara/php-cs` — PHP CS Fixer rule set for code style
- `@wordpress/scripts` ^27.9 — webpack-based JS build toolchain (for Blocks support)
- `spatie/ray` (dev) — debugging helper

## Getting started

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Build JS assets (required for WooCommerce Blocks support)
npm run build
```

Configure the plugin by activating it in WP Admin and entering a Moneroo private API key under WooCommerce → Settings → Payments → Moneroo.

## Common commands

| Task | Command |
|---|---|
| Build JS (production) | `npm run build` |
| Watch JS (development) | `npm start` |
| Format PHP code | `composer format` |
| Lint PHP (dry-run) | `composer sniff` |
| Scan unused dependencies | `composer unused` |
| Build plugin ZIP (local) | `bash test.sh` |

## Architecture

The plugin is intentionally lean (KISS principle). Key files and directories:

- `moneroo-for-woocommerce.php` — plugin entry point; registers hooks, autoloader, and feature compatibility declarations (HPOS, Cart/Checkout Blocks).
- `src/Moneroo_WC_Gateway.php` — extends `WC_Payment_Gateway`; handles payment initiation via `Moneroo\Payment::init()`, return URL routing, and webhook reception.
- `src/Handlers/Moneroo_WC_Payment_Handler.php` — processes payment responses (success / pending / insufficient / failed) and updates WooCommerce order statuses accordingly.
- `src/Moneroo_WC_Gateway_Blocks.php` — integrates the gateway with the WooCommerce Cart & Checkout Blocks API.
- `src/Settings/moneroo-settings.php` — returns the form fields array for the gateway admin settings page.
- `src/index.js` — JavaScript entry point compiled by `@wordpress/scripts` into `build/index.js` for Blocks.
- `assets/` — plugin icon and front-end assets.
- `languages/` — translation `.pot`/`.po` files; text domain is `moneroo`.
- `wp-assets/` — WordPress Plugin Directory screenshots/banner images (excluded from plugin ZIP).

On tag push, GitHub Actions builds the ZIP, publishes it to a Cloudflare R2 bucket, and deploys to the WordPress SVN repository via `10up/action-wordpress-plugin-deploy`.

## Conventions

- Code style is enforced by `axazara/php-cs` (PHP CS Fixer). Run `composer format` before every commit; CI will reject non-conforming code via `composer sniff`.
- Unused dependency scanning runs in CI via `composer unused`.
- Follow TDD: add test cases for every change (project follows KISS + TDD principles per README).
- The plugin version is stored as `__STABLE_TAG__` placeholder in both `moneroo-for-woocommerce.php` and `readme.txt`; the CI workflow replaces it with the Git tag at release time. Never hard-code a version number.
- PSR-4 autoloading under the `Moneroo\WooCommerce\` namespace, rooted at `src/`.
- All user-facing strings must be wrapped with `esc_html__()` / `wp_kses_post()` and use the `moneroo` text domain.

## Git Conventions

### 1. Branch names

Enforced regex (`branch_name_pattern`):
```
^(feature|fix|hotfix|chore|docs|refactor|test|ci|perf|build|style)/[a-z0-9._-]+$
```

- Lowercase only, kebab-case after the prefix, **max 50 characters** total.
- Use the full word `feature/` — **never** `feat/` (the short `feat` form is only for commit message types).
- Include the ticket id when relevant: `feature/AXA-123-add-stripe` (the ticket id is lowercased to satisfy the pattern — e.g. `feature/axa-123-add-stripe`).
- **Never** use a `claude/` prefix or any prefix outside the allowed set.
- `main`, `release`, `staging` are permanent protected branches — never push to them directly.
- If a branch is misnamed, rename it before pushing: `git branch -m <old> <new>`.

### 2. Commit messages
Enforced regex (`commit_message_pattern`), applied to **every** commit:
```
^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([^)]+\))?!?: .+
```
- Lowercase type, optional scope in parens, optional `!` for breaking changes, subject after `: `.
- Subject starts with a lowercase letter and has no trailing period.
- Examples: `feat(checkout): add Apple Pay support`, `fix(api): handle expired tokens`, `chore(deps): bump axios from 1.7.2 to 1.15.2`, `refactor!: drop Node 18 support`.
- Do not rewrite Dependabot commits — `chore(deps): bump X from a to b` is already enforced via `.github/dependabot.yml`.

### 3. Files that are always rejected
Never stage or commit:
- `.env`, `.env.*` (only `.env.example` and `.env.sample` are allowed), `**/.env`, `**/.env.*`
- Private keys: `**/id_rsa{,.pub}`, `**/id_dsa`, `**/id_ecdsa`, `**/id_ed25519`, `**/.ssh/id_*`
- Credentials: `**/.aws/credentials`, `**/credentials.json`, `**/service-account.json`, `**/firebase-adminsdk-*.json`, `**/secrets.{yml,yaml}`
- Extensions: `*.pem`, `*.key`, `*.p12`, `*.pfx`, `*.jks`, `*.keystore`, `*.ppk`, `*.asc`, `*.gpg`
- Any file larger than 100 MB (use git LFS)
If a secret is needed, use `.env.example` for env vars and an external secret manager for credentials.

### Pull requests targeting `main`, `release`, `staging`
All three are protected — a PR is required (direct push blocked):
- 1 approval, all conversations resolved, **squash or rebase merge only** (linear history enforced — no merge commits).
- Commits must be GPG- or SSH-signed. Signing is required for `main` (`required-signatures-main` ruleset).
- The PR **title** becomes the squash commit message and must match the commit-message regex above (enforced on all three branches).

**Required workflows run on PRs whose base is `main` only** (not `release`/`staging`): `Branch naming convention`, `PR title — Conventional Commits`, and `PR size labeler`.
If a check shows `Waiting for workflow to run` for over a minute, the third-party action is likely missing from the enterprise allowlist.

When the branch-naming or PR-title check fails, the baseline bot auto-posts rename/title suggestions, following the enforced regex patterns.
If the bot's suggestions are incorrect, edit the PR title or branch name to match the required format.

### Pre-push checklist
Before running `git push`:
1. Branch name matches the regex.
2. Every commit in `origin/main..HEAD` matches the commit pattern (`git log --format=%s origin/main..HEAD`).
3. No staged file is in the blocked paths/extensions list.
4. Commits are signed if the target is `main`.

If any check fails, fix it locally rather than letting the server reject the push.
