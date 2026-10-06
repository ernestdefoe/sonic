# Working in this repo

`ernestdefoe/sonic` — a free (MIT) Flarum 2 search driver backed by
[Sonic](https://github.com/valeriansaliou/sonic). Its reason to exist is being
LIGHT (requested for servers with limited resources), so every change is
judged on that first.

## Standing rules (Ernest's — cloud sessions do not load memory, so they live here)

- **Nothing new ships unchecked.** Before any release of a new feature, run the
  ship-check: N+1 pass (1 vs 20 items, guest AND admin, the count must not
  grow), lean pass (forum.js gzip size before/after, nothing big in forum
  attributes), security audit (semgrep, gitleaks, every route checks the
  actor). Fix what it finds first.
- **Lightweight, right the first time.** Same function with less code, never
  at the cost of correctness. No per-item queries. Short timeouts: an
  unreachable Sonic must never hang a page (connect 1 s, failures remembered
  30 s, searches fall back to the database driver). Measure, don't assert.
- **Raw SQL is prefix-safe.** Use the query builder, or wrap identifiers with
  `getGrammar()->wrap()` — raw SQL passes through verbatim and only breaks on
  forums with a table prefix. Test on a prefixed forum.
- **Search never leaks.** Sonic only returns ids; Flarum loads them through the
  searcher's `whereVisibleTo` query. Never load search hits any other way.
- **The password never leaves the server.** Not in forum attributes, not in
  the admin payload (`Listener/WriteOnlyPassword`), not in error messages.
- **All strings translatable** (`locale/en.yml`), zero hardcoded English in UI.
- **Conventional commit subjects** (`feat:` / `fix:` / `docs:` / `chore:`):
  `.github/workflows/draft-release.yml` picks the version bump from the SUBJECT.
  End every commit message with
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Commit straight to `main`. Never open a pull request.**
- **Rebuild `js/dist` before committing** (`cd js && npm run build`); dist is
  committed and is what Flarum loads.
- **Fix what is broken; don't ask first.** Report what was done. Confirm only
  for publishing outward, destructive operations, or a live site's data.

## Releasing

Pushing to `main` only drafts a release. A human publishes it (tag +
Packagist). Cloud sessions cannot tag or publish (HTTP 403 by session type):
push, confirm the draft exists, and say publishing is Ernest's click.

Customers update with `composer require ernestdefoe/sonic:^x.y`, not
`composer update`.

## Testing

Test on dev.ernestdefoe.online (container `devflarum_app` on
root@103.195.100.103, table prefix `dev_`). Run php/composer/flarum as
www-data, never root. A throwaway Sonic runs as container `sonic-dev` on the
dev container's network only (no public port); remove it afterwards and leave
dev as found. Never touch the live forums (`flarum_app`, `fbsfb2_app`).
