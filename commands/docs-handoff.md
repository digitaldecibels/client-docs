---
description: Generate or update the client-facing handoff document and its shareable site
argument-hint: "[--sections <list>] [--site]"
---

Produce `docs/CLIENT-HANDOFF.md` for someone who did not build this.

Load `client-handoff`, `documentation-rules` and `project-analysis` first.

## Dormant check

No `docs/.client-docs.yml` means not initialised. Say so, suggest `/docs-init`,
stop.

## First run

Analyse the project, then write the sections from `client-handoff` that
genuinely apply. Skip the ones that do not; a Content Management section on a
site with no CMS is noise.

Separate three kinds of statement clearly: VERIFIED, CLIENT ACTION REQUIRED, and
REQUIRES CONFIRMATION. Put client actions near the top, because that is the part
they will actually read.

Record `handoff_evidence` in the manifest for every section: the claim, the
files that prove it, and the commit. Keep that in the manifest only. It never
appears in the client document.

## Repeat runs

Do not rewrite the document silently. Compare each section against its recorded
evidence, update what changed, and write a **Changes Since Last Handoff**
section covering only what a client would care about: new integrations, changed
deployment, new administrative workflows, removed features, new client actions.

Skip dependency bumps and internal refactors. If nothing client-relevant
changed, say exactly that in one sentence and do not pad it.

## Always

- Never print a secret value. Name the variable and its purpose.
- Never invent a URL, credential, procedure or piece of infrastructure.
- **Write the document. Do not build a site unless asked.** `/docs-handoff`
  produces `docs/CLIENT-HANDOFF.md` and nothing else. Passing `--site` also
  builds `docs/handoff-site/` using the `/docs-site --handoff` behaviour, which
  is the same thing that command does on its own.

  It used to build the site on every run. That is wrong on any project serving
  its documentation another way, because it leaves a second, stale copy of the
  content sitting in the repository that nothing links to and nobody rebuilds.
  One command, one artefact.
- Never include the conflict log or any internal note in client-facing output.

## Report

Where the document is, how many changes since last time, and how many items
need confirmation before it can go to the client. Name the site's location only
when `--site` was passed and it was actually built.
