---
name: drupal-paragraph-types
description: Create Drupal paragraph types, their fields, and their form and view displays from a single declarative script. Use when building a component-based content model in Drupal, when adding a paragraph type or a field to one, or when a paragraph's edit form is in the wrong order or its widget is wrong. Covers the data shape, why field order is form order, and the three failures that abort a run without saying so.
---

# Paragraph types from one declarative script

Start from `${CLAUDE_PLUGIN_ROOT}/scripts/create-paragraph-types.php`. Copy it
into the project's `scripts/drupal/`, replace the two example bundles with the
real model, and leave everything below the `Build.` banner alone.

A paragraph type is more than it looks. Each one is a bundle, a field storage per
field, a field instance per bundle per field, a form display and a view display.
Twenty-five of them is roughly 160 YAML files, every one carrying a uuid, a
dependency list and a full set of defaults Drupal is fussy about. Declaring the
model once and letting the entity API write those files is the difference between
a readable content model and a directory nobody opens.

## The three tables

**`$storages`** declares each field once: its type and its cardinality. A storage
is per entity type, so a field declared here on `paragraph` is a separate thing
from a node field with the same name. Nothing about a label lives here.

**`$types`** declares each paragraph type and, for each, the fields it carries
with any per-instance overrides: label, description, required, and handler
settings such as which media bundles or which child paragraph types are allowed.

Labels living on the instance rather than the storage is what lets one
`field_heading` be "Heading" on a panel and "Name" on a person. Reuse a storage
across bundles freely. Do not create `field_panel_heading` and
`field_person_name`.

**`$widgets` and `$formatters`** map a field type to the control an editor gets
and the way it renders. Both ship filled in for twelve field types, which is more
than most models need. Keeping them here rather than per bundle means every
`string` field on the site behaves the same way without each entry repeating it.

## Field order is form order

The build loop derives both displays from the field list, in declaration order.
So the order fields are written in the script is the order they appear on the
edit form.

Order them the way the design reads: top to bottom, then left to right, on the
desktop frame. A full-bleed image above a heading comes before that heading. A
category pill above a title comes before the title. Styling options such as a
background choice go last, because they are not part of the reading order at all.

The exception worth making: a field that decides which of the other fields get
drawn belongs first. Asking a "Style" question last means an editor fills in a
subheading that the style they then choose never renders.

## Three things that fail without telling you

**A missing `$formatters` entry aborts the run partway through.** Every field
type used in `$storages` needs one. A missing entry leaves the formatter type as
NULL, and `setComponent()` then dies inside `FormatterPluginManager` with an
`array_intersect_key()` type error that names the field but not the cause. The
run stops there, so the bundles after it keep whatever they had, which usually
means a stale form order that looks like a different bug.

**A list field's allowed values must be a plain `value => label` map.** Drupal
converts it into the `{value, label}` mappings the exported YAML shows. Copying
the exported shape back into the script gets it structured a second time and
fails validation. See `ListItemBase::storageSettingsToConfigData()`.

**A hand-set widget setting is overwritten wholesale on the next run.** The build
loop writes the whole settings array. Anything set in the admin interface that
the script does not know about is gone the next time it runs, silently. The
template already handles the common case: where a nested field allows exactly one
child type, it derives `default_paragraph_type` rather than leaving it blank, so
a re-run produces the right value instead of destroying it. Follow that pattern
for anything else you find yourself setting by hand.

## Why `$widget_overrides` is keyed on field name

It looks like over-engineering until you hit the reason.

`field_media` is an `entity_reference`, and so is a field pointing at a taxonomy
term or a node. But a media reference wants the Media Library widget: a
thumbnail, add, remove and replace controls, and the upload form with the focal
point crosshair on it. A term or node reference wants the plain autocomplete,
which is still the right control there.

One field type, two correct answers. So the choice cannot live in `$widgets`,
which is keyed on type. `$widget_overrides` is keyed on name and wins over it.

## Fields the template must not render

`$hidden_in_view` lists fields removed from the view display rather than shown.
Use it for any field a Twig template reads off the entity itself.

`field_media` is the usual one. A template that dereferences the media entity to
a file and builds its own `<picture>` element will render the image twice if the
field is also left in the view display, once as its own output and once inside
the component.

## The node side

The last section of the template restricts a node's paragraph field to the types
marked `top_level`. Without it, the Add menu on a page offers every bundle on the
site, including the cards and stats that only ever exist inside a parent.

Change `$node_bundles` and `$node_field` to match the site. The script reports
`skipped` rather than failing if the field is not there.

## After a run

```bash
drush cex -y
git diff config/
```

Read the diff. `git diff config/core.extension.yml` in particular, every time.
The `drupal-config-scripts` skill covers why, along with the two other ways
export and import bite.
