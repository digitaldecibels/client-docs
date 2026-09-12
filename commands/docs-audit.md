---
description: Verify all documentation against the implementation and report discrepancies
argument-hint: "[--fix] [file]"
---

Check whether the documentation is still true. Report; do not change anything.

## Dormant check

No `docs/.client-docs.yml` means the project is not initialised. Say so, suggest
`/docs-init`, stop.

## How to run it

Delegate to the `documentation-auditor` agent. It is read-only and it keeps the
repository analysis out of this conversation's context.

Give it the manifest path, the scope (all documentation, or the single file
named in the argument), and tell it to load `documentation-audit`,
`documentation-rules` and `project-analysis`.

## With --fix

Run the audit first and show the findings. Then apply only the **stale** ones,
inside generated markers, and only after the developer confirms. Never
auto-apply anything from the UNVERIFIED section: unverified means you do not
know, and changing a document because you do not know is how a correct statement
gets replaced by a wrong one.

## Afterwards

Relay the agent's report as it stands, then write the manifest updates it
recommends: `last_audited` for everything examined, `last_verified_commit`
advanced only for files found accurate, and new `requires_confirmation` entries.

End with the counts and one next step.
