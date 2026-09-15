---
name: documentation-maintenance
description: Keep documentation in step with code incrementally. The manifest schema, how last_verified_commit anchors staleness detection, the change categorisation and mapping rules that decide which docs a change affects, and the manifest migration rules across plugin versions. Load before /docs-update, /docs-status or the hook logic.
---

# Documentation maintenance

Update what changed. Leave the rest alone.

Load `documentation-rules` alongside this, particularly the sections on
protecting hand-written content and the conflict log.

## The manifest

`docs/.client-docs.yml`. It is the record of what is documented and how current
each file is, **and it is the plugin's activation flag**: if it does not exist,
the plugin is dormant and every command except `/docs-init` should say so and
stop.

```yaml
generated_by_version: 1.0.0

project:
  name: Bucknell Be The Ray
  description: Drupal 11 giving microsite

stack:
  languages: [PHP, JavaScript]
  frameworks: [Drupal 11]
  cms: Drupal 11
  frontend: [Tailwind CSS 4, Alpine.js, Vite 8]
  backend: [PHP 8.4]
  database: MariaDB 10.6

development:
  package_manager: [composer, bun]
  local_environment: Lando

deployment:
  platform: Pantheon
  ci_cd: none detected

config_assessment:            # see project-analysis
  directory: config/
  status: current             # current | stale | absent
  note: exported 2 days ago, custom code changed today

integrations:
  - name: Adobe Fonts
    purpose: web fonts
    evidence: [web/themes/be_the_ray/be_the_ray.theme]

documentation:
  files:
    - path: docs/CONFIGURATION.md
      last_verified_commit: a1b2c3d
      last_audited: 2026-09-09
      status: current         # current | stale | unverified
  manually_verified:
    - question: Which Pantheon site is production?
      answer: bucknell-be-the-ray, live environment
      date: 2026-09-09
  requires_confirmation:
    - id: dns
      question: Who controls DNS for the production domain?
      affects: docs/CLIENT-HANDOFF.md
      known: pantheon.yml shows the platform, nothing shows the registrar

handoff_evidence:
  deployment:
    claim: Deployed to Pantheon, config imported by a Quicksilver hook
    evidence: [pantheon.yml]
    verified_commit: a1b2c3d

last_handoff:
  date: 2026-09-09
  commit: a1b2c3d
```

**Never store secrets in it.** Record that a variable is configured and where,
not its value.

**The manifest is the plugin's record, not the developer's settings.** Choices
a developer makes about this project live in `docs/client-docs.config.yml` and
`docs/client-docs.rules.md`, which the plugin reads and never writes. See the
first section of documentation-rules. In particular, `documents.skip` removes a
document from the change mapping below: a change that maps only to a skipped
document affects nothing.

## last_verified_commit is the whole mechanism

Each documented file records the commit SHA at which it was last confirmed
correct. Staleness is then `git diff <that SHA>..HEAD`.

This matters because it catches **all** drift, including changes made in
sessions where nobody thought about documentation. "What changed recently" or
"what changed this session" both miss the commit from three weeks ago that
renamed an environment variable.

Advance the SHA to HEAD when a file is **updated or confirmed still correct**.
A file you checked and found accurate gets its SHA advanced too, otherwise
`/docs-status` keeps reporting it stale and people stop believing it.

If the manifest predates SHA tracking or the SHA is missing, fall back to
`git status` and recent history, and set the SHA on the way out.

### When the recorded SHA has gone

A rebase, a squash merge or a shallow clone can leave a SHA that no longer
exists, and `git diff <missing sha>..HEAD` then fails. Check first:

```bash
git cat-file -e <sha>^{commit} 2>/dev/null
```

If it is gone, do not error and do not silently skip the file. Re-verify that
document in full against the current code, say in the report that its history
anchor was lost, and write a fresh SHA.

### Uncommitted work

`last_verified_commit..HEAD` cannot see uncommitted changes, so also read
`git status` and include them in the analysis. A developer who has just edited
`.env.example` and asks for an update should get one.

But **do not advance a file's SHA on the strength of uncommitted work.** If you
did, the change would be recorded as verified at a commit that does not contain
it, and the next run would never look at it again. So:

- Documentation updated only from committed changes: advance the SHA to HEAD.
- Documentation updated using uncommitted changes: leave the SHA where it is and
  say so in the report.

```
Updated from uncommitted changes:
  docs/CONFIGURATION.md   (still marked unverified; re-run after committing)
```

## Categorising a change

For each changed path in the range, decide what kind of change it is:

UI, API, configuration, database, deployment, dependencies, authentication,
integrations, CMS or content, infrastructure, developer workflow, bug fix,
feature.

## Mapping changes to documentation

| Change | Affects |
| --- | --- |
| Dependency manifest (`composer.json`, `package.json`, lock files) | INSTALLATION, DEVELOPMENT |
| Environment variables (`.env.example`, CI env, code reading them) | CONFIGURATION |
| Docker, Lando, DDEV | DEVELOPMENT |
| Hosting config (`pantheon.yml`, deploy workflows) | DEPLOYMENT, CLIENT-HANDOFF |
| CI workflows | DEPLOYMENT, DEVELOPMENT |
| Routes, controllers, API endpoints | ARCHITECTURE, and API docs if they exist |
| A new third-party SDK or API client | INTEGRATIONS, CLIENT-HANDOFF |
| Drupal content type, field or view config | ARCHITECTURE, CLIENT-HANDOFF |
| Drupal module enabled or removed | ARCHITECTURE, INSTALLATION |
| Custom module or theme source | ARCHITECTURE, DEVELOPMENT |
| Build config (`vite.config`, `tailwind.config`) | DEVELOPMENT |
| Cron, queues, scheduled tasks | ARCHITECTURE, CLIENT-HANDOFF |
| Auth, roles, permissions | ARCHITECTURE, CLIENT-HANDOFF |
| Anything user-visible | CHANGELOG, if one exists |

The mapping is deliberately generous. A false positive costs one file read; a
false negative ships a wrong document.

**Use the same mapping in `/docs-status`, `/docs-update`, the hook and the CI
template.** Four implementations that disagree is worse than none.

## The update procedure

1. Read the manifest. For each documented file, take the range
   `last_verified_commit..HEAD`.
2. List changed files across that range.
3. Categorise them and map to affected documentation.
4. **Read only the affected documentation.**
5. Compare each against the implementation.
6. Update only what is stale, and only inside generated regions.
7. Where hand-written text contradicts the code, log a conflict and leave the
   text alone.
8. Advance `last_verified_commit` to HEAD for every file updated or confirmed.
9. Do not touch unaffected documentation at all.

## Follow-ups that happen automatically

The developer should not need to remember a second command.

- **`CHANGELOG.md`**, if it exists, gets entries for the changes just
  processed, using the `changelog` skill's rules. Do not create it if absent;
  that is `/docs-changelog`'s job.
- **`docs/site/`**, if it exists, is regenerated. It is cheap and it stops the
  site lagging the markdown.
- **Outstanding confirmations**: ask up to three of the most important at the
  end. Skipping is always fine. Say how many remain and that `/docs-confirm`
  handles the rest.
- **Open conflicts**: one line with the count and the path.

## Reporting

Short enough to read on a phone. Details belong in the files.

```
Documentation updated:
- docs/CONFIGURATION.md
- docs/INTEGRATIONS.md

Checked, no changes needed:
- docs/DEPLOYMENT.md

Potentially stale:
- docs/CLIENT-HANDOFF.md   (deployment changed; run /docs-handoff)

2 items need confirmation. Run /docs-confirm.
Next: /docs-handoff
```

End with **one** suggested next step, naming the exact command.

## Manifest migration

The manifest records `generated_by_version`. The same plugin runs across many
repositories written at different times.

- **Additive change** (a new optional key): migrate silently, update the
  version, carry on.
- **Breaking change** (a key renamed, a structure changed): migrate, and tell
  the developer plainly what changed and what to check.

**`branding` moved out of the manifest in 1.8.0.** If a manifest still has a
`branding` block, copy its values into `branding` in
`docs/client-docs.config.yml`, creating that file from the template if it does
not exist, then remove the block from the manifest. Tell the developer once,
in one line, where their settings now live. If both places have a value and
they differ, keep the settings file's and say so.

Never fail because a manifest is old. An old manifest still knows more than no
manifest.
