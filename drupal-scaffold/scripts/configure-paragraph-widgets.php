<?php

/**
 * @file
 * Sets how every nested-paragraph field behaves on the editing form.
 *
 * Run it with:
 *   drush php:script scripts/drupal/configure-paragraph-widgets.php
 *
 * then export with `drush cex -y`.
 *
 * Idempotent, like the other scripts here: it reports what it changed and
 * skips anything already correct.
 *
 * WHAT IT SETS, AND WHY THESE THREE
 *
 *   edit_mode    closed    Children arrive collapsed rather than with every
 *                          form open at once. A page assembly is a dozen
 *                          paragraphs deep and every one of them opened was an
 *                          unscrollable wall of form.
 *   closed_mode  summary   A collapsed child shows a one-line text summary
 *                          of its fields. Always summary, on every paragraph
 *                          type. See below for why not preview.
 *   autocollapse all       Saving or reordering one child re-collapses the
 *                          rest, so the form does not creep back open over a
 *                          long editing session.
 *   add_mode     dropdown  One "Add" dropdown button rather than a row of
 *                          buttons per type. Already the value everywhere;
 *                          asserted here so it stays that way.
 *
 * WHY A SWEEP AND NOT JUST A DEFAULT WHERE THE FIELDS ARE CREATED
 *
 * Two places end up setting this widget and they have to stay in step: the
 * script that creates the paragraph types sets a widget on every field it
 * touches, and this script sweeps whatever exists. If only one of them is
 * changed, a re-run of the other silently undoes the change. Linkit has the
 * same problem on link fields, for the same reason.
 *
 * The sweep exists as well as the default because some fields get configured
 * by hand in the UI and carry settings no script knows about, such as a
 * default child type or a different edit mode. This script changes only the
 * four settings listed below and leaves every other setting on the widget
 * alone, so hand-set ones survive.
 *
 * SUMMARY, NOT PREVIEW
 *
 * Decided on 2026-09-15: every paragraph type uses `closed_mode: summary`.
 *
 * `preview` renders the child through the `preview` view mode, falling back to
 * `default`, which is the component's own markup drawn inside the admin theme
 * without the front end's CSS. It is not what the page looks like, and a form
 * a dozen paragraphs deep fills with unstyled components. The one-line summary
 * is shorter and reads the same on every type.
 *
 * On the site this came from, both values had crept into the exported config
 * because this script said one thing and hand edits in the UI said the other.
 * Change it here and in the paragraph creation script together, or the next
 * run of either puts the mix back.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;

$log = function (string $action, string $what) {
  printf("%-9s %s\n", $action, $what);
};

// Only these keys are touched. Everything else on the widget is left as it is.
$settings = [
  'edit_mode' => 'closed',
  'closed_mode' => 'summary',
  'autocollapse' => 'all',
  'add_mode' => 'dropdown',
];

// Paragraph bundles only. A node's own paragraph field, the one that holds the
// top level of a page, is deliberately left alone: it is the page itself
// rather than a paragraph nested inside another paragraph, and it usually
// wants different settings. Set that one by hand, or in the script that
// creates it.
$entity_type = 'paragraph';

$field_map = \Drupal::service('entity_field.manager')
  ->getFieldMapByFieldType('entity_reference_revisions');

foreach ($field_map[$entity_type] ?? [] as $field_name => $info) {
  foreach ($info['bundles'] as $bundle) {
    $display = EntityFormDisplay::load("$entity_type.$bundle.default");
    if (!$display) {
      continue;
    }
    $component = $display->getComponent($field_name);
    if (!$component) {
      // Hidden on the form on purpose; leave it that way.
      continue;
    }
    if ($component['type'] !== 'paragraphs') {
      $log('skipped', "$bundle.$field_name is a {$component['type']} widget");
      continue;
    }

    $wanted = $component['settings'] + [];
    foreach ($settings as $key => $value) {
      $wanted[$key] = $value;
    }

    if ($component['settings'] == $wanted) {
      $log('ok', "$bundle.$field_name");
      continue;
    }

    $component['settings'] = $wanted;
    $display->setComponent($field_name, $component);
    $display->save();
    $log('updated', "$bundle.$field_name");
  }
}

print "\nDone. Now run: drush cex -y\n";
