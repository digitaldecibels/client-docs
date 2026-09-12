---
description: Show which documentation is current and which is stale, without changing anything
---

A read-only health check. Fast, and safe to run whenever.

Load `documentation-maintenance` for the manifest schema and the change mapping.

## Dormant check

If `docs/.client-docs.yml` does not exist, print exactly this and stop:

```
client-docs is not set up in this project.
Run /docs-init to analyse it and generate documentation.
```

Do not analyse the repository. Do not create anything. A status command that
does work is a status command people stop trusting.

## What to check

For each documented file, take `last_verified_commit..HEAD`, list changed paths,
and map them with the same table `/docs-update` uses. Do not read the
documentation itself; this command answers "what might be stale", not "what is
wrong".

Also report uncommitted changes from `git status`, since they affect nothing yet
but predict the next update.

## Output

```
Documentation status
  Current       4 files
  Stale         2 files
  Unverified    1 file

Stale:
  docs/CONFIGURATION.md   12 commits behind, .env.example changed
  docs/CLIENT-HANDOFF.md  12 commits behind, pantheon.yml changed

Uncommitted changes touch: docs/ARCHITECTURE.md

3 items need confirmation.   2 open conflicts (docs/.client-docs-conflicts.md)

Next: /docs-update
```
