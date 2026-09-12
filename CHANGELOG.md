# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-09-09

### Added
- `/docs-site --starlight` uses the [Starlight Six](https://github.com/six-tech/Six.StarlightTheme)
  theme, puts the project's own icon beside the title, and derives the accent
  colour from the project's primary rather than leaving Starlight's purple.
- The accent is generated as a ramp and each step is checked for contrast
  against the background it sits on. A brand primary is usually a poor link
  colour in dark mode: Bucknell navy is 11.98:1 on white and 1.48:1 on
  Starlight's dark background.
- `src/styles/brand.css`, regenerated every run, separate from `custom.css`,
  which is never overwritten.

### Fixed
- The icon search rejects Drupal's scaffolded `logo.svg` placeholder, which is
  a grey box containing the words "<theme> logo".
- `starlightThemeSix()` is called with an object. With no argument it throws
  `expected object, received undefined`, though every option in it is optional.
- Six's `footerText` is overridden so a client's documentation does not ship
  Six's own credit line.
- CSS minification is switched to esbuild. Six's generated CSS fails Astro 7's
  lightningcss minifier and takes the whole build down with it.
- Six's two unlabelled mobile drawer buttons are given accessible names, which
  restores Lighthouse accessibility from 94 to 100.

## [1.1.0] - 2026-09-09

### Added
- `/docs-site --starlight` builds an Astro Starlight site into `docs/starlight/`
  for developer documentation, with search, a generated sidebar and a real
  theme. `docs/*.md` stays the single source; the Starlight content directory is
  generated on every run because Starlight requires a `title` in frontmatter and
  has no fallback to a top-level heading.
- A Starlight project template that installs its dependencies by name rather
  than pinning them, so a project scaffolded later does not start out of date.
  It carries the `src/content.config.ts` Starlight needs, without which a build
  produces a 404 page and nothing else while reporting only that the collection
  is empty.
- `/docs-site --starlight` checks the Node version before installing. Installing
  Astro under too old a Node skips its native binaries, and the resulting
  failure names a missing rolldown module rather than the Node version. It uses
  a newer Node already on the machine for the build only, and never changes the
  developer's default.

### Changed
- `/docs-handoff` states explicitly that the client site stays self-contained
  even on a project using Starlight. A handoff gets emailed; a Starlight build
  needs a web root.

## [1.0.0] - 2026-09-09

### Added
- `/docs-init` detects a project's stack and generates the first documentation set
- `/docs-status` reports which documentation is current and which has drifted
- `/docs-update` updates only the documents affected by what changed
- `/docs-audit` verifies documentation against the code through a read-only agent
- `/docs-handoff` writes a client-facing handoff document and a shareable site
- `/docs-confirm` works through the questions a repository cannot answer
- `/docs-changelog` builds a changelog from git history
- `/docs-site` builds a self-contained static site from the markdown
- Six skills carrying the rules: documentation-rules, project-analysis,
  documentation-maintenance, documentation-audit, client-handoff, changelog
- A drift reminder hook that stays quiet until a project opts in
- Templates for eight documents, the manifest, the site shell and a CI check
