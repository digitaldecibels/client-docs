---
description: Update the documentation chatbot's search index to match the current docs
---

Brings the chatbot's index in step with the documentation files, and reports
anything that will not be indexed.

Only for projects whose `docs/client-docs.config.yml` sets `chat.enabled: true`.

## Dormant checks

If `docs/.client-docs.yml` does not exist, print exactly this and stop:

```
client-docs is not set up in this project.
Run /docs-init to analyse it and generate documentation.
```

If `docs/client-docs.config.yml` has no `chat.enabled: true`, print exactly this
and stop:

```
This project does not feed its documentation to a chatbot.
Nothing to index.
```

## What to run

Find the indexer. In a Drupal project it is at
`web/modules/custom/private_docs/bin/index.php`. If the project keeps it
elsewhere, the developer will say so; do not guess at a second location.

First report, then act:

```bash
php <indexer> status --json
php <indexer> index
```

`status` is read-only. Run it first so you can tell the developer what is about
to change, and so a failure to reach the store is reported before anything is
sent.

Exit codes: 0 success, 1 finished with warnings, 2 missing configuration, 3 the
store or the embedding service failed.

## When it says it is not configured (exit 2)

The credentials live in a gitignored file at the repository root,
`.docs-index.env`. Do not create it with invented values and do not ask for the
keys in chat. Tell the developer which variables are missing and stop.

On a server there is a second route that uses the site's own settings instead:

```bash
drush docs:status
drush docs:index
```

Prefer that one when working on a deployed environment, because it can also index
content held in the database, which the file-based indexer cannot see.

## What to report

- How many pages were indexed, how many were unchanged, how many were removed.
- **Every page that was skipped, and why.** A page with no `audience` tag is
  usually an oversight; a page with `chat: false` is usually a decision. Say
  which kind each one is rather than listing them together.
- Any warning about a customer tag that is not in `chat.customers`, which is
  nearly always a typo.

Do not print the chunk contents.

## When to run it

After `/docs-update`, and after any edit to a tagged document. In projects with a
pre-commit hook this happens on commit anyway, so the usual reason to run it by
hand is that the hook reported it could not: no credentials on that machine, or
the store was unreachable at the time.
