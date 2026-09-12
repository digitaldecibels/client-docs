---
description: Copy the Drupal config scripts into this project and report what each still needs
argument-hint: "[script name, or nothing to be asked]"
---

Copy configuration scripts from this plugin into the project, so they can be
adapted and run. This command copies and reports. It never runs Drush.

Load the `drupal-config-scripts` skill before doing anything else, and follow it.

## 1. Check this is a Drupal project

Look for `web/core/lib/Drupal.php` or `docroot/core/lib/Drupal.php`, or a
`drupal/core` entry in `composer.json`. If none is there, say so and stop. Do not
create a `scripts/drupal/` directory in a project that is not Drupal.

Note which docroot the project uses, because it decides nothing here but is worth
having in the report.

## 2. Work out what runs Drush

Do not assume, and do not run anything to find out. Look for:

- `.lando.yml` at the root, which means `lando drush`
- a `.ddev/` directory, which means `ddev drush`
- `pantheon.yml`, which means `terminus drush <site>.<env> --` for remote
  environments, plus whatever runs locally
- neither, which probably means plain `drush`

Use whichever you find in every command you print. If two are present, or none
is, ask rather than picking one.

## 3. Ask which scripts are wanted

The eight available scripts, and what each does, are listed in the
`drupal-config-scripts` skill. Show that table, then ask.

If the user named a script in the arguments, take that as the answer and skip
the question.

Four of them are finished as they stand: `configure-image-styles.php`,
`configure-links.php`, `configure-media.php` and
`configure-paragraph-widgets.php`. The other four need content filled in. Say
which is which when asking, so the choice is informed.

Check what the site actually has before recommending one. `configure-links.php`
needs the `linkit` module, `configure-media.php` needs `focal_point`,
`configure-sitemap.php` needs `simple_sitemap`, and
`create-paragraph-types.php` needs `paragraphs`. Read `composer.json` to see
which are required. A script whose module is missing is still worth copying if
the user wants that module, but say that installing it comes first.

## 4. Copy them

Source is `${CLAUDE_PLUGIN_ROOT}/scripts/`. Destination is `scripts/drupal/` at
the project root, created if it is not there.

Copy the file unchanged. Do not adapt it during the copy: the header comment on
each one explains what to change, and rewriting it in transit means the user
reads your summary instead of the real thing.

If a file of that name already exists in the project, do not overwrite it. Report
it as already present and move on. If the user wants it replaced, that is a
separate instruction and worth confirming, because their version may carry
changes.

## 5. Report

For each script copied, one line: the path, and what it still needs. Then the
command to run it, using the Drush runner from step 2.

Where a script needs content filled in, say what and where, naming the variable:

- `configure-editor.php` needs `$styles` and `$allowed_classes`. It is inert
  until `$styles` has an entry, so running it first does nothing but print a
  message.
- `configure-shortcuts.php` needs `$route`, `$path` and `$title`.
- `configure-sitemap.php` runs as it stands. `$tuning` is optional per-type
  priority.
- `create-paragraph-types.php` needs the whole content model. Point at the
  `drupal-paragraph-types` skill.

End with the export step, because it is the half people forget:

```
<runner> cex -y
git diff config/
```

Then say the one thing that matters most: read
`git diff config/core.extension.yml` specifically, because that is the file that
ships a development module to production.

## What not to do

- Do not run any script. Running Drush against a site is the user's call, and a
  script whose data tables are still empty should not run at all.
- Do not run `drush cex`. Same reason, and an export at the wrong moment deletes
  hand-written YAML that has not been imported yet.
- Do not create `scripts/drupal/` unless at least one script is being copied
  into it.
