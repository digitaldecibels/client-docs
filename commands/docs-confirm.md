---
description: Work through the open questions the repository cannot answer
argument-hint: "[--all]"
---

Ask the developer the things no amount of code reading can settle.

## Dormant check

No `docs/.client-docs.yml` means not initialised. Say so, suggest `/docs-init`,
stop. If the manifest exists but `requires_confirmation` is empty, say there is
nothing outstanding and stop.

## How to ask

Read `requires_confirmation` from the manifest. Order by impact: anything
blocking a client handoff comes first.

Ask **one question at a time**, and with each one say what the repository
already shows, so the developer is correcting a draft rather than writing from
nothing:

```
Who controls DNS for the production domain?
What I can see: pantheon.yml names the platform. Nothing shows the registrar.
Affects: docs/CLIENT-HANDOFF.md

(answer / skip / stop)
```

Ask at most five in one run unless `--all` is given. Long interrogations get
abandoned halfway, which leaves the manifest in a worse state than not asking.

**Skip is always a valid answer.** A skipped question stays in
`requires_confirmation` untouched.

## Recording an answer

Move it to `manually_verified` with the question, the answer and today's date.
Remove it from `requires_confirmation`. Then update the documentation it affects,
inside generated markers, replacing the REQUIRES CONFIRMATION block with the
answer.

A human answer is high confidence from then on. Never overwrite it later from
inference. If the code contradicts it, log a conflict, because they may know
something about the hosting account that the repository cannot show.

Never record a secret value, even if it is volunteered. Record that it exists
and where it is configured.

## Report

```
3 answered, 1 skipped, 2 remaining.
Updated: docs/CLIENT-HANDOFF.md, docs/DEPLOYMENT.md
Next: /docs-handoff
```
