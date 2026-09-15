# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.8.0] - 2026-09-15

### Added
- Per-project settings in `docs/client-docs.config.yml`, hand-edited and never
  written by the plugin: documents to skip, tab groups with their order and
  hidden tools, sidebar order, site exclusions, page descriptions, which
  components are used, the Open questions page, and branding. Every key is
  optional and defaults to the plugin's current behaviour, shown in
  `templates/client-docs.config.yml`. Unknown keys are reported.
- Per-project rules in `docs/client-docs.rules.md`, plain sentences every
  command reads before writing. A project's rule wins over the plugin's, apart
  from the floor: never invent a fact, never write a secret, never overwrite
  text outside generated markers.
- `scripts/build-starlight.mjs`, which generates the Starlight pages from
  `docs/*.md`, the settings and the manifest. Conversion had been done by hand
  from instructions, so two sessions could produce different pages; the script
  gives the same output every time. It needs no install, using the `js-yaml`
  Astro already provides, and `--out` writes elsewhere for comparison.
- `/docs-init` creates both files, filling only what the repository shows.
- `<!-- steps -->` markers for a numbered procedure, alongside the existing
  `## Steps` heading convention.
- An Open questions page in the Starlight site, generated from the manifest on
  every `/docs-site` run. Every `requires_confirmation` entry is a card grouped
  under the page it affects, with what is already known and a link to that
  page. Answered questions are not listed. It sits last in the sidebar with a
  badge showing the open count, so the number is visible from every page.
- A rule that every REQUIRES CONFIRMATION marker on a page has a manifest
  entry, because the Open questions page is built from the manifest alone.
- `custom.css` gives every card one amber colour, where Starlight would cycle
  four by position and make equal questions look like different severities.

### Changed
- `branding` moves from the manifest to `docs/client-docs.config.yml`. An older
  manifest is migrated on the next command, which says so once.
- Tab order, hidden tools and the Open questions page are settings rather than
  fixed rules. The defaults are unchanged.
- The default sidebar order includes CONTENT-MODEL, MODULES and CHANGELOG,
  which the plugin has generated since 1.6.0 but the order never listed.
- Page descriptions come only from a page's opening paragraph, before its
  first heading. A sentence from inside a section described the section.
- DDEV and Lando are always split into their own tabs, and DDEV is written
  first so it is the tab a new reader sees. Starlight has no default-tab
  setting; the first `<TabItem>` is selected until the reader picks another,
  so tab order is how the preference is expressed. A reader who has already
  chosen Lando keeps Lando.
- Every command that differs between local environments is now written in tab
  markers when the docs are generated: the README quick start, installation
  steps, verification, troubleshooting and the commands reference. No more
  `# or` between two tools' commands, and no command table with a column per
  tool. Such a table becomes one table per tab. Tables that explain how the
  tools differ stay tables.

## [1.7.0] - 2026-09-12

### Added
- Starlight components in the generated site, where they earn their place.
  `<Steps>` for a numbered procedure, `<Tabs syncKey>` for one task with two
  tool-specific paths, `<FileTree>` for where things live. A page using one is
  written as `.mdx` with its imports; a page using none stays `.md`.
- Three HTML-comment markers in the source markdown (`tabs`, `tab`, `filetree`)
  that the site build turns into components. They are invisible wherever the
  markdown is read as markdown, so `docs/*.md` stays plain and portable.
- `syncKey` guidance: a reader picks Lando once and every tab group on every
  page follows, and the choice persists between pages. One key per real choice.
- The stated non-candidate: a comparison table showing two tools side by side
  answers "what is the equivalent?", and tabs would hide half of it. Convert a
  procedure, leave a comparison alone.

### Fixed
- Wide tables escaped the content column and gave the page horizontal scroll.
  The Starlight Six theme ships `display:block; overflow:auto` for this but the
  rule loses in its own cascade: measured, a table computed to
  `overflow-x: visible` and drew 693px inside a 644px column. Restated in
  `custom.css`, which is unlayered and loads last. Page scroll width went from
  1303px to 1280px on a 1280px viewport.
- Writing `.mdx` no longer fails on the generated-region markers. MDX has no
  HTML comments, so `<!-- ... -->` becomes `{/* ... */}` in an `.mdx` page.

## [1.6.0] - 2026-09-12

### Added
- `docs/MODULES.md` and `docs/CONTENT-MODEL.md` join the generated set, with
  templates for each. The modules page lists every contributed and custom
  package with a plain-language line saying what it does for this site, grouped
  by what a reader would recognise, flagging the development-only ones and
  those installed but switched off. The content model page lists each component
  an editor builds pages from and the fields they fill in.
- Concision rules in `documentation-rules`: one sentence per idea, no preamble,
  a table wherever the content has a repeating shape, and no section that only
  says a thing does not apply. Traps are the stated exception, because the cost
  of one being missed outweighs the words.
- REQUIRES CONFIRMATION renders as a Starlight caution aside rather than a bold
  run of text in a paragraph, so it is visible rather than skimmed past. Covers
  the two cases that cannot be an aside: a table cell, and a list item.

### Fixed
- The installed-modules and content-components sections were defined in
  `client-handoff`, so they only ever reached `CLIENT-HANDOFF.md`. A project
  serving developer documentation any other way never saw them. Both now live
  in `project-analysis`, which the documentation commands load, and the handoff
  skill points at them rather than holding a second copy.

## [1.5.0] - 2026-09-12

### Changed
- **`/docs-handoff` no longer builds `docs/handoff-site/` on every run.** It
  writes `docs/CLIENT-HANDOFF.md` and stops. Pass `--site` to also build the
  shareable site, which is the same thing `/docs-site --handoff` already did on
  its own.

  The old behaviour assumed every handoff gets emailed, so having a zippable
  folder ready was worth the extra artefact. On a project that serves its
  documentation another way, that assumption inverts: the site is a second copy
  of the same content that nothing links to, nobody rebuilds, and that goes
  stale the moment the markdown changes. A stale duplicate costs more than a
  missing convenience.

## [1.4.0] - 2026-09-12

### Added
- A `branding` block in the manifest, hand-edited, holding `primary`,
  `secondary`, `logo` and `title`. `/docs-site` reads it before it tries to
  detect anything, so a developer can pin the documentation site's look without
  editing generated files. Any key left null still falls back to detection, and
  the report says which values were given and which were guessed.
- A secondary colour is applied to the table header row and the blockquote bar,
  the two places a second accent reads as intent rather than decoration in a
  monochrome theme. Both get the same contrast treatment as the primary, and a
  secondary too close to the primary is reported and dropped rather than
  shipped as a difference nobody can see.
- A `logo` path that does not exist stops the run. The developer meant that
  file, so falling back silently hides a typo.

### Fixed
- Tables in the Starlight site had the same 1rem gap as a paragraph, which is
  too tight for a bordered block between two blocks of prose. Now 1.5rem, which
  collapses with the existing sibling margin rather than adding to it.

## [1.3.0] - 2026-09-12

### Added
- Two handoff sections. **What is installed** lists the contributed and custom
  modules or packages with a plain-language line each, grouped by what a client
  would recognise, flagging the development-only ones and any carrying a patch.
  **The building blocks of a page** lists each component an editor builds with
  and the fields they fill in.
- A narrow exception to the scan budget: the `description` key may be read from
  a contributed module's `.info.yml` or a package's `package.json`. That one key
  from that one file, so a module's own words can be rewritten for the reader
  rather than guessed at. Everything else under `contrib/` and `node_modules/`
  stays closed.
- Paragraph field order is taken from the form display's weights, not from the
  order the field config files happen to be in, so the list matches what an
  editor actually sees on the form.

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
