---
name: changelog
description: Build and maintain CHANGELOG.md from git history in Keep a Changelog format. Covers commit classification, what to exclude, how to handle an existing hand-written changelog, and the Unreleased-to-version promotion. Load before /docs-changelog and when /docs-update needs to append entries.
---

# Changelog

Turn git history into something a person would want to read.

A changelog is not a commit log. Most commits do not belong in it.

## Format

Keep a Changelog, newest first, with semantic versioning. Categories in this
order, and only the ones that have entries:

```markdown
# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added
- CSV upload for campaign fundraising totals

### Changed
- Progress bars now animate when scrolled into view

### Fixed
- Paragraph descriptions were missing from the admin interface

## [1.2.0] - 2026-08-12

### Added
- ...
```

Categories: Added, Changed, Deprecated, Removed, Fixed, Security.

## Classifying a commit

Conventional prefixes when they are there:

| Prefix | Category |
| --- | --- |
| `feat:` | Added |
| `fix:` | Fixed |
| `perf:`, `refactor:` | Changed, if user-visible |
| `security:` | Security |
| `BREAKING CHANGE`, `!` | Changed, marked breaking |

Most repositories do not use them. Read the message and the diff, and ask what
a user of this project would notice.

## What to leave out

- Formatting, linting, whitespace
- Internal refactors with no visible effect
- Routine dependency bumps, unless one changes a requirement or fixes a
  vulnerability
- Merge commits
- Work-in-progress commits, checkpoint commits, and "wip"
- Changes to the documentation itself

Rick checkpoint-commits during a session. Those are savepoints, not releases.
A changelog full of them is worse than no changelog.

## Writing an entry

One line, present tense, from the reader's point of view. Say what changed for
them, not which file you touched.

Good:

> Progress bars now animate when scrolled into view

Bad:

> Refactored progress-bar.twig to use x-intersect

Group several commits into one entry when they were one piece of work. Five
commits building a CSV uploader is one line, not five.

## An existing changelog

If `CHANGELOG.md` already exists, **it is hand-written until proven otherwise**.

- Find the most recent version heading and only add entries newer than it.
- Match the existing style, even where it differs from Keep a Changelog. A
  consistent changelog beats a correct one.
- Never rewrite or reorder existing entries.
- If the file has generated markers, edit only inside them.

## Versions

New work goes under `## [Unreleased]`.

Promote it to a version when the developer says so, or when a tag appears in git
that has no heading yet. Take the version from the tag, or from
`composer.json` / `package.json` when there is no tag, and date it from the tag
or from today.

Never invent a version number.

## Called from /docs-update

`/docs-update` appends entries for the commits it just processed, if
`CHANGELOG.md` exists. It does not create the file; that is `/docs-changelog`.
Reuse the same classification rules so the two produce identical entries for
identical history.
