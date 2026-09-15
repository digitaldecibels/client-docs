---
description: Update documentation for what changed since it was last verified
argument-hint: "[--since <commit>] [--dry-run]"
---

Bring the documentation back in step with the code, touching only what changed.

Load `documentation-maintenance` and `documentation-rules` first.

## Dormant check

If `docs/.client-docs.yml` does not exist, say this project is not initialised,
suggest `/docs-init`, and stop. Do not analyse, do not generate.

## Procedure

1. Read the manifest, and the project's settings and rules if they exist.
   Leave out every document in `documents.skip`. For each documented file, the range is
   `last_verified_commit..HEAD`. `--since <commit>` overrides the starting
   point for every file.
2. List changed paths across that range with `git diff --name-only`.
3. Categorise each change and map it to affected documentation using the table
   in `documentation-maintenance`. Use that table exactly; do not invent your
   own mapping.
4. Read **only** the affected documentation.
5. Compare each affected claim against the implementation.
6. Update what is stale, inside generated markers only.
7. Where hand-written text contradicts the code, log a conflict in
   `docs/.client-docs-conflicts.md` and leave the text alone.
8. Advance `last_verified_commit` to HEAD for every file updated **or confirmed
   still correct**.

With `--dry-run`, do steps 1 to 5 and report what you would change. Write
nothing, including the manifest.

## Follow-ups, without being asked

- Append changelog entries if `CHANGELOG.md` exists, using the `changelog`
  skill's rules. Do not create it.
- Regenerate `docs/site/` if it exists.
- Ask up to three outstanding confirmations at the end. Skipping is fine.
- Report the open conflict count and the file path, in one line.

## Report

The format in `documentation-maintenance`: updated, checked with no change,
potentially stale, confirmations outstanding, and one suggested next command.
