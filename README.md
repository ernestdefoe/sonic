# Sonic Search for Flarum

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE.md)

## The problem

Flarum's built-in search leans on your database's fulltext index, which gets slow and
blunt as a forum grows. The usual fixes (Elasticsearch, OpenSearch, Meilisearch,
Typesense) are good but heavy: hundreds of megabytes of RAM before they index a single
post. On a small VPS or shared box that is more than the forum itself uses.

## What this does

A free, drop-in **Flarum 2** search driver backed by
[Sonic](https://github.com/valeriansaliou/sonic), a tiny search backend written in Rust.
An idle Sonic uses a few megabytes of RAM.

- Searches **discussions, posts and users**; you choose which per resource.
- Text searches go to Sonic. Browsing and filters (`tag:`, `author:` and so on) keep
  working exactly as before.
- **Permissions stay Flarum's.** Sonic only hands back ids; Flarum loads them through
  its normal visibility rules (private discussions, hidden or unapproved posts,
  restricted tags). Sonic never decides who sees what.
- **Never hangs a page.** If Sonic is down, searches fall back to the database within
  about a second, and the failure is remembered for 30 seconds so later requests skip
  Sonic straight away.
- **Light on writes.** A new reply is appended to its discussion's entry instead of
  re-reading the thread, and saves that don't change any searchable text (a user's
  last-seen time, a reply counter) never contact Sonic.
- The password is kept on the server. It is never sent to visitors, and the admin page
  only ever writes it.
- No PHP dependencies: the extension speaks Sonic's text protocol directly.

## Installation

```bash
composer require ernestdefoe/sonic
```

Then enable **Sonic Search** in the admin panel.

## Running Sonic

Sonic needs no config file; environment variables are enough. This keeps the port
private to the machine and caps memory at 128 MB (it idles at a few MB):

```bash
docker run -d --name sonic --restart unless-stopped \
  -p 127.0.0.1:1491:1491 --memory 128m \
  -e SONIC_CHANNEL__INET=0.0.0.0:1491 \
  -e SONIC_CHANNEL__AUTH_PASSWORD='change-me' \
  -e SONIC_STORE__KV__PATH=/var/lib/sonic/store/kv/ \
  -e SONIC_STORE__FST__PATH=/var/lib/sonic/store/fst/ \
  -v sonic-data:/var/lib/sonic/store \
  valeriansaliou/sonic:v1.10.2
```

If Flarum itself runs in Docker, put Sonic on the same Docker network instead of
publishing a port, and use the container name (`sonic`) as the host. Sonic is also
packaged as a single binary; see its README.

## Setting up

1. **Admin → Sonic Search.** Enter the host, port and password, then **Save**.
2. **Check connection.** It reports how many items each index holds.
3. **Rebuild index** (or `php flarum sonic:index`) to index your existing content.
4. Turn on the searches Sonic should answer and save.

Several forums can share one Sonic: each forum uses its own bucket (set one, or it
defaults to your forum's host name), and a rebuild only empties that bucket.

```bash
php flarum sonic:index          # rebuild every index
php flarum sonic:index --flush  # empty them
```

On a busy forum, run a real queue worker so indexing and rebuilds happen in the
background rather than during a visitor's request.

## Good to know

- Sonic is an "identifier index", not a document store. It matches whole words, with
  typo and prefix alternatives once it has consolidated its word graph (a rebuild
  triggers that straight away). Results come back in Sonic's order, at most 100 per
  search (Sonic's default `query_limit_maximum`).
- Sonic keeps the 1,000 most recent items per word by default (`retain_word_objects`).
  On a very large forum, very common words will only find recent content.
- If Sonic was offline while people posted, run **Rebuild index** afterwards.

## Updating

```bash
composer require ernestdefoe/sonic:^0.1
php flarum cache:clear
```

## Support

- **Support forum:** [Sonic Search on ernestdefoe.online](https://ernestdefoe.online/d/126)
- **Flarum community:** [Sonic Search on discuss.flarum.org](https://discuss.flarum.org/d/40008-sonic-search-for-flarum-2-that-fits-on-the-smallest-server)
- **Bug reports:** [GitHub issues](https://github.com/ernestdefoe/sonic/issues)

## Licence

[MIT](./LICENSE.md) © Ernest Defoe
