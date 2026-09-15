# client-docs

Documentation for client projects, written from what the code actually does.

Three commands cover most weeks:

| Command | Use it when |
| --- | --- |
| `/docs-status` | You want to know what has drifted. Read-only, safe any time. |
| `/docs-update` | After a chunk of work, to bring the docs back in step. |
| `/docs-handoff` | You are handing the project to a client, or the client asked what changed. |

Everything else is occasional: `/docs-init` once per project, then
`/docs-audit`, `/docs-confirm`, `/docs-changelog` and `/docs-site` as needed.

## Five minute start

```
1. Install the plugin (below).
2. Open a project and run  /docs-init
   It reports what it detected and asks before writing anything.
3. Answer its questions with  /docs-confirm
   Skip anything you do not know. Skipping is fine.
4. Later, after some work:  /docs-update
5. Before a client sees it:  /docs-handoff
```

That is the whole workflow. Step 2 is the only one that takes real time.

## Installing

Clone it somewhere permanent, then add it as a marketplace and install from
there. The repository carries its own marketplace manifest, so it is two
commands.

```bash
git clone <this repo> ~/Herd/client-docs
```

In Claude Code:

```
/plugin marketplace add ~/Herd/client-docs
/plugin install client-docs@digital-decibels
```

Restart the session. `/docs-init` will be available in every project, and will
do nothing in any project that has not opted in.

To check the manifests before installing:

```bash
claude plugin validate --strict ~/Herd/client-docs
```

## It does nothing until you ask it to

The plugin is dormant in every repository that has no `docs/.client-docs.yml`.
No hooks fire, no commands do work, nothing is created. Only `/docs-init` acts,
and it asks first.

That file is the opt-in switch. Delete it and the plugin goes quiet again.

The point is that installing this globally must not mean every repository you
open starts growing documentation you did not ask for.

## The commands

| Command | Does | Writes |
| --- | --- | --- |
| `/docs-init` | Detects the stack, generates the first documentation set | yes, after asking |
| `/docs-status` | Reports what is current and what is stale | no |
| `/docs-update` | Updates only the docs affected by what changed | yes |
| `/docs-audit` | Verifies docs against the code and reports discrepancies | no, unless `--fix` |
| `/docs-handoff` | Client-facing handoff document plus a shareable site | yes |
| `/docs-confirm` | Asks the questions the repository cannot answer | yes |
| `/docs-changelog` | Builds CHANGELOG.md from git history | yes |
| `/docs-site` | Static browsable site from the markdown. `--starlight` builds an Astro Starlight site instead | yes |

## Making it fit a project

The plugin's behaviour is a set of defaults. Each project can change them with
two optional files next to the manifest, which the plugin reads and never
writes. `/docs-init` creates both.

**`docs/client-docs.config.yml`** holds the structured choices:

| Setting | What it changes |
| --- | --- |
| `documents.skip` | Documents never generated, maintained, audited or published here |
| `tabs.local-environment` | Tab order (the first is what a new reader sees), tools to `hide`, or no tabs at all |
| `site.order`, `site.exclude` | Sidebar order, and documents kept off the site |
| `site.descriptions` | Whether pages carry a description under the title |
| `site.components` | Tabs, steps and file trees on or off |
| `site.open_questions` | The Open questions page: on or off, title, position, count badge, icon |
| `branding` | Primary and secondary colour, logo, site title |

Every key is optional. The template in `templates/client-docs.config.yml`
shows each default, and a misspelt key is reported rather than silently
ignored. Tab groups are not limited to local environments: add
`tabs.package-manager` and use that name in the markers.

**`docs/client-docs.rules.md`** holds anything a setting cannot say, in plain
sentences: what the client calls the site, what not to document, which pages
the client reads. Where a rule there disagrees with the plugin's own rules, the
project wins. What it cannot change: the plugin still never invents a fact,
never writes a secret, and never touches text outside its generated markers.

Styling beyond the brand colours goes in `docs/starlight/src/styles/custom.css`,
which is also the project's and never overwritten.

## How it stays cheap to run

Each documented file records the commit it was last verified at. Staleness is
then a plain `git diff` from that commit to now, mapped to the documents those
changes affect.

So `/docs-update` reads two files instead of forty, and it catches drift from
sessions where nobody was thinking about documentation, including the commit
three weeks ago that renamed an environment variable.

## What it will not do

- **Invent things.** No credentials, URLs, procedures, infrastructure or
  features that are not in the repository. Anything it cannot verify is marked
  REQUIRES CONFIRMATION and queued for `/docs-confirm`.
- **Print a secret.** It records that a variable exists and what it is for, even
  when the value is sitting in a file it can read.
- **Overwrite your writing.** Generated content lives between
  `<!-- client-docs:generated:start -->` markers. Everything else is yours. If
  your prose contradicts the code it logs a conflict and leaves the prose alone.
- **Guess at a database.** When a CMS keeps its content model outside version
  control and nothing is exported, it says the content model cannot be
  determined from the repository rather than inferring one.
- **Publish anything.** `/docs-site` builds locally and tells you where. Where it
  goes next is your call.

## Files it creates

| Path | What it is |
| --- | --- |
| `docs/.client-docs.yml` | The manifest, and the opt-in switch. Written by the plugin. Commit it. |
| `docs/client-docs.config.yml` | This project's settings. Yours; the plugin only reads it. Commit it. |
| `docs/client-docs.rules.md` | This project's own documentation rules. Yours. Commit it. |
| `docs/.client-docs-conflicts.md` | Internal conflict log. Never shown to a client. |
| `docs/*.md` | The documentation |
| `docs/site/` | Developer site, from `/docs-site` |
| `docs/starlight/` | Astro Starlight site, from `/docs-site --starlight`. Its `src/content/docs/` is generated; `docs/*.md` stays the source. |
| `docs/handoff-site/` | Client site, from `/docs-handoff` |

## The drift reminder

One hook, on file edits. If you change something documentation depends on, it
adds a quiet note suggesting `/docs-update` at the end of the work. Once per
session per category, never mid-task, and it cannot edit or block anything.

It does not fire at all without a manifest.

## Optional CI check

The plugin's `templates/ci-docs-check.yml` warns on a pull request when a change touches
something documented. It never fails the build, because a docs job that blocks
merges gets deleted within a month.

## Limitations

- It reads a repository. Anything that lives only in a hosting dashboard, a DNS
  registrar or someone's head has to come through `/docs-confirm`.
- A CMS content model is only as accurate as the last configuration export.
- It does not read a live database or call a hosting API.
- Screenshots go stale and it cannot tell.
- Large repositories hit a scan cap. It says so rather than pretending the
  analysis was complete.
