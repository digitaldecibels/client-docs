---
description: Analyse the project and generate the initial documentation set
argument-hint: "[--minimal]"
---

Set this project up for client-docs. Run once per repository.

Load the `project-analysis` and `documentation-rules` skills before doing
anything else, and follow them.

## 1. Check whether it is already initialised

If `docs/.client-docs.yml` exists, do not re-run the analysis. Report what is
already documented, when it was last verified, and suggest `/docs-status`. Stop.

## 2. Readiness check

Some projects are too early to document. A repository still finding its shape
will churn faster than the documentation can keep up, and the result is a set of
files nobody trusts.

Look for these signals, cheaply:

| Signal | How to check |
| --- | --- |
| Very few commits, or created days ago | `git rev-list --count HEAD`, `git log -1 --format=%cr $(git rev-list --max-parents=0 HEAD)` |
| No hosting or deployment configuration of any kind | none of `pantheon.yml`, `.github/workflows`, `netlify.toml`, `vercel.json`, `Dockerfile`, `fly.toml` |
| Very high recent churn | `git diff --stat HEAD~10..HEAD` touching a large share of the tree |
| No dependency manifest | no `composer.json`, `package.json` or equivalent |

If two or more fire, say so plainly, say why it matters, and ask whether to
continue:

```
This project looks early: 6 commits, no deployment configuration, and the
last 5 commits changed most of the tree. Documentation written now will
probably be wrong within a week.

Initialise anyway? (yes / not yet)
```

**The developer always decides.** If they say yes, proceed normally and do not
mention it again.

This check runs only here. Never run it on its own, and never nag about it from
anywhere else.

## 3. Analyse

Follow `project-analysis`: stay inside the scan budget, respect `.gitignore`,
never read vendored or core code, and identify contributed dependencies by name
and version from the dependency manifest rather than by reading their source.

Assess exported configuration honestly. If a CMS keeps its content model in a
database and nothing is exported, the content model is not documentable from
this repository. Say so rather than guessing.

## 4. Ask before generating

Show what you found, briefly:

```
Detected: Drupal 11, PHP 8.4, Tailwind CSS 4, Vite, Alpine.js
Local: Lando       Hosting: Pantheon      CI: none found
Config: config/, exported 2 days ago, looks current

Proposed documentation:
  docs/ARCHITECTURE.md      docs/INSTALLATION.md
  docs/DEVELOPMENT.md       docs/DEPLOYMENT.md
  docs/CONFIGURATION.md     docs/INTEGRATIONS.md
  README.md                 (generated section only, existing content kept)

Generate these? (all / choose / cancel)
```

With `--minimal`, propose only README, INSTALLATION and DEVELOPMENT.

## 5. Generate

Use the files in `${CLAUDE_PLUGIN_ROOT}/templates/` as the starting shape. Fill only what the
repository proves. Mark everything else REQUIRES CONFIRMATION and add it to the
manifest's `requires_confirmation`.

Every generated block is wrapped:

```
<!-- client-docs:generated:start -->
<!-- client-docs:generated:end -->
```

**An existing README is never replaced.** Add a generated section to it, or
leave it alone and put the detail in `docs/`. Ask if it is not obvious.

## 6. Write the manifest

`docs/.client-docs.yml`, using the schema in `documentation-maintenance`. Set
`last_verified_commit` on every file to the current HEAD, and record
`generated_by_version`. This file is what activates the plugin, so it is written
last, after the documentation exists.

## 7. Report

What was created, how many items need confirmation, and one next step:

```
Created 6 files in docs/ and a generated section in README.md.
4 items need confirmation before this is client-ready.
Next: /docs-confirm
```
