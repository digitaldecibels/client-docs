# drupal-scaffold

Drupal configuration built by idempotent PHP scripts instead of by clicking, so
the next site gets it too.

Everything structural on a site (image styles, link widgets, media forms,
paragraph types) is created by a script in `scripts/drupal/`, run through Drush,
and checked into the repo. The exported YAML under `config/` is what deploys. The
script is the thing that produced it, kept so the model can be re-read rather
than reverse engineered from a directory of a hundred and sixty YAML files.

Each script checks before it writes, so re-running after a tweak is safe and is
the normal way to work. Nothing deletes.

## One command

```
/drupal-scaffold
```

It checks the project is Drupal, works out what runs Drush there (Lando, DDEV,
Pantheon or plain), asks which scripts you want, copies them into
`scripts/drupal/`, and reports what each one still needs filled in. It never runs
anything against the site.

## What ships

Four scripts are finished as they stand and can be run straight away:

| Script | What it does |
| --- | --- |
| `configure-image-styles.php` | Makes every image style output WebP, including the two-convert-plugin case that quietly doubles the effect |
| `configure-links.php` | Linkit on every link field on the site, with `target` allowed and `rel` deliberately not |
| `configure-media.php` | The focal point widget on both of the image media type's entry forms, which are separate config and easy to half-configure |
| `configure-paragraph-widgets.php` | Nested paragraphs collapsed, previewed, re-collapsing after each save, added from one dropdown |

Four are templates with the content emptied out:

| Script | What you fill in |
| --- | --- |
| `configure-sitemap.php` | Nothing required. Optional per-type priority |
| `configure-editor.php` | The named styles for the CKEditor Styles dropdown |
| `configure-shortcuts.php` | The one admin link that goes on the shortcut bar |
| `create-paragraph-types.php` | The content model: fields, bundles, and their order |

## Three skills

They load on their own when the work calls for them.

- **`drupal-config-scripts`** is the method, and the three ways `drush cex` and
  `drush cim` bite. Worth reading once even if you never use a script from here:
  the export deleting hand-written YAML, and `core.extension.yml` shipping a
  development module to production, are both things you find out the hard way.
- **`drupal-paragraph-types`** is the paragraph model: the shape of the data, why
  field order is form order, and the three failures that abort a run without
  saying so.
- **`seo-buildout`** is the SEO layer end to end: metatag defaults, Open Graph,
  Twitter, Facebook, Schema.org JSON-LD, the sitemap, robots.txt and llms.txt. It
  starts by asking Drush what content types exist rather than assuming them.

## Where the header comments come from

Every script carries a long header explaining why it exists and what went wrong
the first time. Those are the reason this plugin is worth more than rewriting the
same code on the next project, and they are copied from a real Drupal 11 build
rather than written for documentation. Read one before running it.

## Installing

The repository carries its own marketplace manifest, so it is two commands in
Claude Code:

```
/plugin marketplace add ~/Herd/client-docs
/plugin install drupal-scaffold@digital-decibels
```

If the marketplace is already added for `client-docs`, only the second command is
needed.
