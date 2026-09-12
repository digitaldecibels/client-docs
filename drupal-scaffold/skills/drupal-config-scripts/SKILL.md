---
name: drupal-config-scripts
description: Build Drupal configuration with idempotent PHP scripts run through Drush, instead of by clicking in the admin interface. Use when setting up a Drupal site's structure, when adding image styles, Linkit, media widgets, CKEditor styles or a sitemap, or when a config export or import has behaved in a way that makes no sense. Carries the traps in drush cex and cim that cost real time.
---

# Configuration is created by scripts, not by clicking

The rule: anything structural on the site is created by a PHP script in
`scripts/drupal/`, run through Drush, and checked into the repo. The exported
YAML under `config/` is what deploys. The script is the thing that produced it,
kept so the model can be re-read rather than reverse engineered from a directory
of a hundred and sixty YAML files.

```bash
drush php:script scripts/drupal/configure-media.php
drush cex -y
git diff config/
```

`drush` there means whatever runs Drush on this project. Check before assuming:
Lando wants `lando drush`, DDEV wants `ddev drush`, Pantheon wants
`terminus drush <site>.<env> --`, and some setups have it plain on the path.
Ask, or look for a `.lando.yml` or `.ddev/` directory, rather than guessing.

## Every script is idempotent, and that is the whole point

A script checks whether a thing exists before creating it, and reports what it
did:

```
created  storage paragraph.field_heading
exists   type example_card
updated  node.page.field_components allowed bundles
```

Re-running after a tweak is safe and is the normal way to work. It is not a
migration that runs once. Nothing deletes.

Two habits follow from that:

- **Prefer adding a case to an existing script over doing the thing by hand.**
  A change made in the admin interface exists on one site, in one database, and
  the next person does not get it.
- **Assume the script will be re-run.** If a setting can be derived, derive it.
  A value set by hand in the UI that the script later overwrites wholesale is
  destroyed on the next run, silently, and that is very hard to spot.

## The traps in export and import

These are the ones that cost hours. All three are about `drush cex` and
`drush cim` rather than about any particular site.

**1. `drush cex` deletes any file in `config/` that is not in the database.**

A YAML file written by hand and not yet imported is destroyed by the next
export. The order that works:

```bash
drush cex -y                  # export first
# now write or edit the YAML by hand
drush cim --partial -y        # import it back
```

Getting this backwards loses the file with no warning and no obvious cause.

**2. `config_exclude_modules` stops modules being exported, not uninstalled.**

`settings.local.php` often lists development modules (`devel`,
`devel_generate`, `webprofiler`, a demo module) under `config_exclude_modules`,
so `cex` will not write them into `config/core.extension.yml`. But `cim` still
syncs `core.extension`, so any import uninstalls them again.

The symptom: a development route that worked five minutes ago suddenly 404s
after an import. Re-enable the module and carry on. This is working as designed
and it will happen again.

**3. Always read `git diff config/core.extension.yml` after an export.**

That is the file that quietly ships a development module to production. It is
worth a separate look every single time, not a scan of the whole `config/`
diff.

## When two scripts set the same thing

This is the failure mode most likely to waste a day, because nothing reports it.

If two scripts both write a widget on the same field, they have to stay in step.
Whichever runs last wins, so a change made in one and not the other is undone
the next time the other runs, silently and completely.

Two real pairs where this happens:

- The script that creates paragraph types sets a widget on every field it
  touches. `configure-links.php` sweeps every link field on the site and sets
  Linkit on it. While the first said `link_default`, a re-run of it removed
  Linkit from every link field on the site.
- The same script sets the paragraphs widget settings on nested fields.
  `configure-paragraph-widgets.php` sweeps those same fields.

The rule: before changing a widget in one script, grep the others for the same
field name or the same widget type. If two places write it, change both in the
same commit.

## The scripts in this plugin

They live in `${CLAUDE_PLUGIN_ROOT}/scripts/`. Copy the ones a project needs
into its own `scripts/drupal/`, then adapt. `/drupal-scaffold` does the copying
and says what each one still needs.

| Script | What it does | Needs filling in |
| --- | --- | --- |
| `configure-image-styles.php` | Makes every image style output WebP | No |
| `configure-links.php` | Linkit on every link field, `target` allowed | No |
| `configure-media.php` | Focal point on both of the image media type's forms | No |
| `configure-paragraph-widgets.php` | How nested paragraphs behave on the edit form | No |
| `configure-sitemap.php` | simple_sitemap over every content type | Optional per-type tuning |
| `configure-editor.php` | Named styles in the CKEditor Styles dropdown | Yes, the styles themselves |
| `configure-shortcuts.php` | The shortcut bar, and one admin link on it | Yes, the link |
| `create-paragraph-types.php` | Paragraph types, fields, form and view displays | Yes, the whole model |

Four of them are finished as they stand and can be run immediately. Read the
header comment on any of them before running it: each one records why it exists
and what went wrong the first time, which is the part that does not fit in a
table.

For the paragraph type template specifically, see the `drupal-paragraph-types`
skill, which covers the shape of the data and why the field order matters.

## Documenting the model

The `description` on each paragraph type and content type is the closest thing a
Drupal site has to built-in documentation. It is what an editor reads on the Add
menu when choosing between "Page" and "Story", or between nine components with
similar names.

Keep those descriptions in the script that creates the types, not in the admin
interface. One edited in the UI is lost the next time the script runs.
