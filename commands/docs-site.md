---
description: Build a browsable static site from the documentation
argument-hint: "[--handoff] [--starlight] [--output <dir>]"
---

Turn the markdown into something you can open in a browser and send to someone.

## Dormant check

No `docs/.client-docs.yml` means not initialised. Say so, suggest `/docs-init`,
stop.

## What to build

Plain static HTML. **One self-contained file per page, no build step, no
dependencies, no network requests.** The output has to work when someone opens
`index.html` from a folder on their desktop, or from a zip, or over a corporate
proxy that blocks CDNs.

- Convert each markdown file to a page.
- A sidebar listing every page, and the current one marked.
- Inline CSS in each page. No external stylesheet, no font from a CDN, no
  JavaScript framework.
- Keep it readable: a measure of about 70 characters, a system font stack, real
  spacing, a light and dark palette from `prefers-color-scheme`.
- Code blocks scroll inside their own box rather than pushing the page sideways.
- A generated date in the footer.

Use `${CLAUDE_PLUGIN_ROOT}/templates/site-page.html` as the shell. It already carries the skip link,
the heading order, the focus outline and the light and dark palette.

## Accessibility, not optional

A handoff site is often the first thing a client opens, and some of them work
under an accessibility policy. Before reporting the build as finished:

- Exactly one `<h1>` per page, and no heading level skipped. The sidebar title
  is a paragraph, not a heading, for this reason.
- Every table wrapped in `<div class="table-wrap">` so it scrolls inside its own
  box instead of pushing the page sideways.
- Every image carries alt text. A decorative image gets `alt=""`.
- Every link says where it goes. No "click here".
- `lang` set on `<html>`.
- Text still readable at 200% zoom, which the relative units in the shell give
  you as long as you do not add fixed pixel heights.

If Chrome DevTools is available, run an accessibility audit on `index.html` and
fix what it reports. If it is not, check the list above by hand and say which
way you verified.

## Two modes

**Default** builds developer documentation into `docs/site/`: every markdown
file in `docs/` plus the README.

Never include the plugin's own working files in any build, in either mode:

```
docs/.client-docs.yml            the manifest
docs/.client-docs-conflicts.md   the conflict log
docs/site/, docs/handoff-site/   previous builds
```

The conflict log is internal. It records where someone's writing disagrees with
the code, which is a useful note to a developer and an alarming thing for a
client to read.

**`--handoff`** builds only `docs/CLIENT-HANDOFF.md` into `docs/handoff-site/`,
as a client-facing site. In this mode:

- Never include the conflict log, the manifest, or any internal file.
- Strip generated-region markers and provenance comments from the output.
- Keep CLIENT ACTION REQUIRED prominent. It is why they opened it.

`--output <dir>` overrides the destination.

## `--starlight`, for developer documentation that gets read for a year

The default build is a single self-contained file per page because a client
handoff has to survive being emailed. Developer documentation has the opposite
problem: it is read repeatedly over months, and it wants search, a real sidebar
and decent typography.

`--starlight` builds an [Astro Starlight](https://starlight.astro.build) project
into `docs/starlight/` instead. **It never applies to `--handoff`.** If both
flags are given, say that the handoff stays self-contained and why, then build
the handoff the default way.

### Where the content lives

`docs/*.md` stays the single source. Starlight requires a `title` in frontmatter
on every page and has no fallback to a top-level heading, so the build writes a
copy into `docs/starlight/src/content/docs/` with frontmatter added. That
directory is generated output, is gitignored by the template, and is rewritten
on every run.

Say this in the report. Someone will otherwise edit the copy and lose it.

### Building it

1. **If `docs/starlight/` does not exist**, copy
   `${CLAUDE_PLUGIN_ROOT}/templates/starlight/` into it and fill the
   placeholders from the manifest: `{{PROJECT_NAME}}`,
   `{{PROJECT_DESCRIPTION}}`, `{{PROJECT_SLUG}}`.

   Then install. **Do not pin versions**; install by name so the resolved
   version is whatever is current:

   ```bash
   cd docs/starlight && npm install astro @astrojs/starlight sharp \
     @six-tech/starlight-theme-six @fontsource/inter @fontsource/jetbrains-mono
   ```

   The last three are the [Starlight Six](https://github.com/six-tech/Six.StarlightTheme)
   theme and the two fonts it declares as peer dependencies. Six takes
   `navLinks`, `footerText` and `customCss` and has **no accent colour option**,
   which is why the brand colour is applied as CSS below rather than passed to
   it.

   Three things about Six that each cost a build:

   - **`starlightThemeSix()` with no argument throws.** Every option inside is
     optional but the object itself is required, and the error says
     `expected object, received undefined`. Pass `{}` at minimum.
   - **Its `footerText` defaults to Six's own credit line.** Override it, or a
     client's documentation ships someone else's marketing in the footer.
   - **Its generated CSS fails Astro 7's lightningcss minifier**
     (`Pseudo-elements like '::before' can't be followed by selectors like
     'Colon'`), which fails the whole build. Set
     `vite: { build: { cssMinify: 'esbuild' } }`.

   Six also ships two unlabelled buttons in its mobile drawer, which costs six
   points of Lighthouse accessibility and malforms the accessibility tree. The
   template carries a `head` script that labels them. Check whether it is still
   needed before keeping it.

   **Check Node first, and check it before installing, not after.** Astro
   refuses to run on a Node older than it supports, and the failure is worse
   than a clean refusal: installing under an old Node silently skips the
   platform-native build binaries, so a later run on a newer Node dies with
   `Cannot find module '@rolldown/binding-...'` rather than a version message.
   Recovering means reinstalling, not just switching Node.

   ```bash
   node -v                                    # what the shell will use
   node -p "require('astro/package.json').engines.node"   # what Astro needs
   ```

   If the default Node is too old, look for a newer one already on the machine
   (`/opt/homebrew/opt/node/bin/node`, nvm, fnm, volta) and put it first on
   `PATH` **for this build only**. Never change the developer's default Node:
   on a project like a Drupal site with a Vite theme build, the default is
   pinned to something the rest of the project depends on.

   ```bash
   PATH="/opt/homebrew/opt/node/bin:$PATH" npm install
   PATH="/opt/homebrew/opt/node/bin:$PATH" npm run build
   ```

   If no new enough Node exists, stop and say so. Installing one is the
   developer's call, not yours.

2. **If it already exists**, leave `astro.config.mjs`, `package.json` and
   `src/styles/custom.css` alone. They are the developer's now. Regenerate the
   content and `src/styles/brand.css`, and nothing else. If the project's
   primary colour has changed since last time, say so in the report rather than
   changing it silently.

3. **Read `branding` in the manifest first.** Any key set there is the answer,
   and detection is skipped for that key. It exists so a developer can stop the
   guessing without editing generated files:

   ```yaml
   branding:
     primary: '#003865'
     secondary: '#E87722'
     logo: web/themes/be_the_ray/images/logo-square.png
     title: Bucknell Be The Ray
   ```

   Missing, `null` or empty means detect it as described below. Report which
   values came from the manifest and which were detected, so it is obvious
   which ones are a guess.

   A `logo` path that does not exist is an error worth stopping for, not
   something to silently fall back from. The developer meant that file.

4. **Take the project's icon, if it has a real one.** Starlight shows it beside
   the title. Look in the project's own theme or app assets, never in a
   dependency:

   | Stack | Where to look |
   | --- | --- |
   | Drupal | `web/themes/<custom theme>/images/`, then the theme root |
   | Node or a static app | `public/`, `src/assets/`, `static/` |
   | Any | a file named `logo*`, `icon*`, `mark*`, `favicon*` |

   **Prefer a square image.** Starlight draws the logo at roughly 24px tall
   beside the title, so a wide wordmark becomes an illegible smear. A 600x600
   square logo is ideal; a 519x170 lockup is not.

   **Reject the framework's placeholder.** Drupal scaffolds a `logo.svg` into
   every theme that is a grey rounded rectangle containing the words
   "<theme> logo". It is not a logo, it is a stand-in, and shipping it looks
   worse than shipping nothing. Open the file: if an SVG is under about 1KB and
   contains a `<text>` element saying "logo", it is the placeholder. Check the
   project's own templates for which image the site actually renders in its
   header, which is the reliable answer.

   Copy the chosen file to `docs/starlight/src/assets/` and fill
   `{{LOGO_CONFIG}}` in the config with:

   ```js
         logo: {
           src: './src/assets/<filename>',
           alt: '<project name>',
         },
   ```

   If nothing suitable exists, replace `{{LOGO_CONFIG}}` with nothing and say so
   in the report. Never generate an icon, and never use a stock one.

5. **Take the project's primary colour**, and check it before using it.

   Find it in the project's own tokens, not in a framework default: a Tailwind
   v4 `@theme` block, `theme.extend.colors` in a Tailwind config, or CSS custom
   properties named `--color-primary`, `--color-brand` or similar. Prefer a
   token whose name says primary or brand. If several are equally plausible,
   ask rather than guess.

   **Then check contrast, because a brand colour is usually not a link colour.**
   Starlight uses the accent for link text in both light and dark themes, and
   most brand primaries are dark enough to vanish on a dark background. Bucknell
   navy `#003865` is 11.98:1 on white and **1.48:1** on Starlight's dark
   background, which is invisible.

   So derive a ramp rather than setting one value, and verify each against the
   background it sits on:

   | Variable | Used for | Target |
   | --- | --- | --- |
   | `--sl-color-accent-low` | subtle backgrounds | no contrast requirement |
   | `--sl-color-accent` | borders and UI | at least 3:1 |
   | `--sl-color-accent-high` | accent **text** | at least 4.5:1 |

   Light mode: the primary itself usually works, with a darker shade for
   `-high`. Dark mode: lighten the primary until `-high` clears 4.5:1 against
   `#17181c`, keeping the hue so it still reads as the brand.

   Write the result to `docs/starlight/src/styles/brand.css`, regenerating it
   every run:

   ```css
   /* Generated by /docs-site --starlight from <where the colour came from>.
      Do not hand-edit; put overrides in custom.css, which loads after this. */
   :root {
     --sl-color-accent-low: #002442;
     --sl-color-accent: #7392aa;
     --sl-color-accent-high: #9eb3c4;
   }
   :root[data-theme='light'] {
     --sl-color-accent-low: #e0e7ed;
     --sl-color-accent: #003865;
     --sl-color-accent-high: #002747;
   }
   ```

   Starlight's default is dark, so bare `:root` is the dark theme and
   `[data-theme='light']` is the light one. Getting that backwards is silent:
   the site looks fine in one theme and wrong in the other.

   **Under Six, those three variables are not enough, and on their own they do
   almost nothing.** Six is shadcn-shaped: it sets
   `--sl-color-text-accent: var(--primary)` and uses `--sl-color-accent*` only
   inside its search box. So set its tokens too:

   ```css
   :root[data-theme='dark']  { --primary: <light tint>; --primary-foreground: <dark>; --ring: <light tint>; }
   :root[data-theme='light'] { --primary: <brand>;      --primary-foreground: #fff;   --ring: <brand>; }
   ```

   Six declares its own tokens inside `@layer six`. This file is unlayered, and
   unlayered CSS beats layered CSS whatever the specificity, so it wins without
   needing `!important`.

   **Say plainly in the report that Six is a monochrome theme.** It colours
   prose links and the active sidebar item with the foreground colour, by
   design, so after all of the above the brand shows in the logo, the search
   box, the focus ring and asides, and **not** in body links. There is no purple
   to replace, because Six already replaced Starlight's purple with greyscale.
   If the developer wanted visible brand colour on links, that is a deliberate
   override of the theme's design and their call to make, not yours:

   ```css
   /* Optional, and against Six's intent. */
   .sl-markdown-content a:not(.sl-anchor-link) { color: var(--primary); }
   ```

   Report the two contrast figures you measured. "Uses the brand colour" is not
   a check.

   **The secondary colour, if `branding.secondary` is set.** It has no slot in
   either Starlight or Six, so it is only worth applying where it reads as
   deliberate rather than decorative. Use it for the quiet surfaces and the
   marks that separate one thing from another, never for body text:

   | Where | Token or selector | Why |
   | --- | --- | --- |
   | Table header row | `.sl-markdown-content thead` background | Separates the header from the rows without a second border |
   | Blockquote bar | `.sl-markdown-content blockquote` left border | The one place a second colour reads as intent |
   | Active sidebar background | `--sidebar-accent` | The primary already colours the text; this is behind it |
   | Focus ring on a dark surface | `--ring` fallback | Only where the primary fails contrast |

   **Both colours get the same contrast treatment as the primary.** A secondary
   used as a background needs the text on it to clear 4.5:1, so compute the
   text colour rather than assuming white, and light and dark mode need
   separate values. Report the figures.

   **If the two colours are close in hue and lightness, say so and use one.**
   Two near-identical accents read as a mistake rather than a system. Better to
   report "the secondary is within 1.2:1 of the primary, so it is not applied"
   than to ship a difference nobody can see.

   Write the secondary into the same generated `brand.css`, in its own clearly
   commented block, so all generated colour stays in one file.

6. **Use Starlight's components where they earn their place.** They only work
   in `.mdx` and `.mdoc`, never in `.md`, so a page that uses one is written as
   `.mdx` with the imports it needs at the top. A page that uses none stays
   `.md`. Do not convert every page.

   MDX has no HTML comments. Any `<!-- ... -->` left in the body fails the build
   with ``Unexpected character `!` ``, so convert them to `{/* ... */}` when
   writing `.mdx`.

   `docs/*.md` stays plain markdown. Mark the three cases in the source with
   HTML comments, which are invisible wherever the markdown is read as markdown
   and degrade to ordinary prose:

   ```markdown
   <!-- tabs:local-environment -->
   <!-- tab:DDEV -->
   ...
   <!-- tab:Lando -->
   ...
   <!-- /tabs -->

   <!-- filetree -->
   - src/
     - **index.js** the entry point
   <!-- /filetree -->
   ```

   | Component | Use it for | Do not use it for |
   | --- | --- | --- |
   | `<Steps>` | A numbered procedure someone follows in order, such as the install steps | A numbered list that is really an enumeration |
   | `<Tabs syncKey>` | One task with two tool-specific paths: DDEV or Lando, npm or yarn | A comparison, where seeing both at once is the point |
   | `<FileTree>` | Where things live in the repository, with the parts worth knowing bolded | A complete listing. Show what someone works in |

   **`syncKey` is the reason tabs are worth it.** Every `<Tabs>` sharing a key
   switches together, across the whole site, and the choice persists between
   pages. A reader picks DDEV once and never sees Lando again. Use one key per
   real choice, not one per page.

   **DDEV and Lando always get their own tabs, and DDEV comes first.** When a
   procedure has a DDEV path and a Lando path, split them into a
   `local-environment` group rather than writing both into one block. Starlight
   has no prop for the default tab: the first `<TabItem>` in the markup is the
   one selected, until a reader picks another and their browser remembers it
   under the `syncKey`. So order is the whole mechanism. Write
   `<TabItem label="DDEV">` first and `<TabItem label="Lando">` second, in every
   group on every page, and keep the labels spelled exactly that way, because
   syncing matches on the label text. A project configured for only one of the
   two gets no tab group: document the tool it has, as plain steps.

   **A command table with a column per tool is split, not kept.** A reader
   runs one tool, so a table with a DDEV column beside a Lando column becomes
   one table per tab, same rows in the same order. The source markdown should
   already be written that way (see "One tab per local environment" in the
   documentation-rules skill); if it is not, fix the source rather than only
   the site. What stays a table is an explanation of how the tools differ,
   such as where each gets its database credentials, because comparing is its
   whole purpose.

   `<Steps>` wraps a standard ordered list and needs no other change. Inside a
   tab or a step, indent the nested content to that item's continuation indent.

7. **The content config is not optional.** `src/content.config.ts` in the
   template defines the `docs` collection with Starlight's `docsLoader()` and
   `docsSchema()`. Without it the build succeeds, reports
   `The collection "docs" does not exist or is empty`, and produces a 404 page
   and nothing else. It looks like a content problem and is not.

8. **Generate the content.** For each file included by the rules below, write
   `docs/starlight/src/content/docs/<name>.md` containing:

   ```
   ---
   title: <the file's h1, verbatim>
   description: <the first sentence of the first paragraph, if there is one>
   sidebar:
     order: <see ordering below>
   ---
   ```

   Then the body, with two changes: **remove the h1**, because Starlight renders
   the title itself and leaving it gives the page two, and **remove the
   provenance comment**, because Starlight shows a last-updated date of its own.
   Leave generated-region markers in place; they are invisible in the output and
   removing them would tempt someone to edit the copy.

9. **Order the sidebar** so it reads in the order a person needs it, not
   alphabetically. Use `README` 1, `INSTALLATION` 2, `DEVELOPMENT` 3,
   `ARCHITECTURE` 4, `CONFIGURATION` 5, `DEPLOYMENT` 6, `INTEGRATIONS` 7, then
   anything else from 8. `README.md` becomes `index.md` so it is the home page.

10. **Build it** with `npm run build` and report the result. If the build fails,
   report the error rather than deleting anything.

### What is excluded, in every mode

The exclusions in the default build apply here too, and one more. Never copy
into Starlight:

```
docs/.client-docs.yml            the manifest
docs/.client-docs-conflicts.md   the conflict log
docs/CLIENT-HANDOFF.md           client-facing, and it stays self-contained
docs/site/, docs/handoff-site/   previous builds
docs/starlight/                  itself
```

### The trade it makes

State this plainly when reporting, once, so nobody is surprised later:

> The Starlight site needs `npm run build` and has to be served from a web
> root. It cannot be opened from a folder or a zip the way the default build
> can, so it is the wrong format for anything you plan to email.

## Never publish

Do not deploy, push, or upload the site anywhere. Build it locally and say where
it is. Where it goes next is the developer's call.

## Report

```
Built docs/site/ from 7 files.
Open docs/site/index.html
```

For `--starlight`:

```
Built docs/starlight/ from 7 files. Sidebar order set, README is the home page.
Preview:  cd docs/starlight && npm run dev
Static:   docs/starlight/dist/ (needs a web root, not a folder)

docs/starlight/src/content/docs/ is generated. Edit docs/*.md and rebuild.
```
