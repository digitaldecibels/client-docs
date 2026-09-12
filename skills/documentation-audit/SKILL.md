---
name: documentation-audit
description: Deep verification of documentation against the implementation. The full discrepancy checklist, how to use handoff evidence to keep audits fast, and the VERIFIED / STALE / MISSING / UNVERIFIED / RECOMMENDATIONS report format. Load before /docs-audit. The audit reports; it does not edit.
---

# Documentation audit

Go looking for the places documentation and code have come apart.

`/docs-update` asks "what changed?". An audit asks "is any of this still true?",
which catches the things that were wrong from the start or drifted without a
commit that looked relevant.

**The audit does not make changes** unless explicitly asked. Its output is a
report someone acts on.

Load `documentation-rules` and `project-analysis` alongside this.

## Use recorded evidence first

For `CLIENT-HANDOFF.md`, the manifest's `handoff_evidence` says which files
support each section. Re-check those files first, and only widen to a full scan
when the evidence files are missing or have changed substantially.

That is the difference between an audit that takes a minute and one that reads
the repository again. It also makes the result deterministic: two runs against
the same commit reach the same conclusion.

## What to look for

Work through this deliberately. Each line is a real way documentation goes
wrong.

**Claims that are no longer true**
- Documented features that no longer exist
- Commands that would now fail
- File paths that have moved
- Framework or dependency versions that have changed
- API endpoints and routes that have changed
- Installation steps that no longer work
- Deployment steps that no longer match the hosting configuration
- Database details that have changed
- Configuration described that is no longer read

**Things that exist and are not documented**
- Important features with no mention
- Integrations with no mention
- Environment variables the code reads but nothing documents
- Cron jobs, queues and scheduled tasks
- Authentication and role behaviour

**Things documented that no longer exist**
- Environment variables documented but read nowhere
- Configuration described but absent
- Dependencies listed but removed

**Rot**
- TODOs that look completed
- Screenshots that show an older interface, where you can tell
- Statements that cannot be verified from anything in the repository

## Two failure modes worth separating

**Stale** means the documentation says something and the code says otherwise.
Name both, with the file path.

**Unverified** means the documentation says something and the repository has
nothing to say about it. This is not necessarily wrong. A statement about who
owns the DNS cannot be verified from a repository and may be perfectly true.
Report it as unverified, and if it matters, add it to `requires_confirmation`
rather than treating it as an error.

Conflating the two produces a report that cries wolf.

## The Drupal configuration caveat

Before reporting a content type, view, field or permission as stale or missing,
check the configuration assessment from `project-analysis`. If exported
configuration is absent or looks stale, the honest finding is:

> Cannot verify the content model. Configuration was last exported 8 months
> ago. Recommend a fresh export before auditing this section.

Not:

> STALE: docs claim two content types, config shows five.

The second is a guess dressed as a finding.

## Report format

```
VERIFIED
--------
- docs/DEPLOYMENT.md: Pantheon deployment matches pantheon.yml
- docs/DEVELOPMENT.md: all four lando commands exist in .lando.yml

STALE
-----
- docs/CONFIGURATION.md names SENDGRID_API_KEY; nothing reads it.
  The code now uses MAILGUN_API_KEY (web/modules/custom/x/src/Mailer.php:31)
- docs/INSTALLATION.md says "npm install"; the project uses bun
  (package.json, .lando.yml)

MISSING
-------
- A queue worker runs hourly (be_the_ray_utilities.services.yml) and is
  documented nowhere
- REDIS_HOST is read in settings.php and is not in CONFIGURATION.md

UNVERIFIED
----------
- docs/CLIENT-HANDOFF.md says backups run nightly. Nothing in the
  repository shows a backup schedule. Added to requires_confirmation.

RECOMMENDATIONS
---------------
1. Run /docs-update to fix the two stale items above.
2. Run /docs-confirm to answer the backup question.
3. Export configuration before the next handoff; the current export is
   8 months old.
```

Empty sections stay, with "none". A missing section reads as "not checked".

## Afterwards

Update the manifest:

- `last_audited` for every file examined.
- `last_verified_commit` to HEAD **only for files found accurate**. A file with
  stale findings keeps its old SHA, because it has not been fixed.
- Add anything genuinely unverifiable to `requires_confirmation`.

Then a summary that fits on a phone: counts per category, and one next step.

```
Audited 7 files: 4 verified, 2 stale, 1 unverified.
Next: /docs-update
```
