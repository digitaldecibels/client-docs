<?php

/**
 * @file
 * Puts every link on the site through Linkit, with attributes and a target.
 *
 * Run it with:
 *   drush php:script scripts/drupal/configure-links.php
 *
 * then export with `drush cex -y`.
 *
 * Idempotent, like the other scripts here: it reports what it changed and skips
 * anything already correct.
 *
 * FOUR THINGS HAVE TO LINE UP
 *
 * Making a link field offer a target is not one setting. All four of these are
 * needed, and missing any one of them fails quietly:
 *
 *   1. The field widget has to be `linkit_attributes`, which is the Linkit
 *      autocomplete plus the attributes panel, and its `enabled_attributes`
 *      has to include `target`.
 *   2. CKEditor's own link dialog needs `editor_advanced_link` for the same
 *      thing in body text, and the Linkit plugin turned on so body links get
 *      the same autocomplete as field links.
 *   3. The text format's `allowed_html` has to permit `target` on `<a>`, or
 *      the filter strips the attribute back out on the way to the page. This
 *      is the one that catches people: the editor saves it, the page does not
 *      show it, and nothing reports an error.
 *   4. The Twig that renders a link has to pass the attribute through. Field
 *      formatters do this for free; this theme's components take props, so
 *      the button and card components gained `target` and `rel`.
 *
 * Steps 1 to 3 are here. Step 4 is in the theme.
 *
 * TARGET ONLY
 *
 * `target` is the one attribute turned on, in the field widget, in CKEditor
 * and in the text format. `rel` is deliberately not offered: every browser
 * this site supports implies `noopener` for `target="_blank"` on its own, so
 * the attribute would be noise in the editing UI for no gain.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\linkit\Entity\Profile;

$log = function (string $action, string $what) {
  printf("%-9s %s\n", $action, $what);
};

// -----------------------------------------------------------------------------
// 1. Every link field, on every bundle of every entity type, gets the Linkit
//    widget with attributes.
//
//    Found by asking the field map rather than by listing them, so a link field
//    added later is picked up by re-running this.
// -----------------------------------------------------------------------------
$attributes = [
  'id' => FALSE,
  'name' => FALSE,
  'target' => TRUE,
  'rel' => FALSE,
  'class' => FALSE,
  'accesskey' => FALSE,
  'aria-label' => FALSE,
  'title' => FALSE,
];

$widget = [
  'type' => 'linkit_attributes',
  'settings' => [
    'placeholder_url' => '',
    'placeholder_title' => '',
    'linkit_profile' => 'default',
    'linkit_auto_link_text' => FALSE,
    'enabled_attributes' => $attributes,
    // Show the attributes panel already open when any of them is set, so an
    // editor can see a target that is in force without hunting for it.
    'widget_default_open' => 'expandIfValuesSet',
  ],
];

$field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('link');

foreach ($field_map as $entity_type => $fields) {
  foreach ($fields as $field_name => $info) {
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
      if ($component['type'] === $widget['type'] && $component['settings'] == $widget['settings']) {
        $log('ok', "$entity_type.$bundle.$field_name");
        continue;
      }
      $display->setComponent($field_name, [
        'type' => $widget['type'],
        'weight' => $component['weight'],
        'region' => $component['region'],
        'settings' => $widget['settings'],
        'third_party_settings' => [],
      ]);
      $display->save();
      $log('updated', "$entity_type.$bundle.$field_name");
    }
  }
}

// -----------------------------------------------------------------------------
// 2. The Linkit profile.
//
//    It only matched nodes. Media and taxonomy terms are linkable things on
//    this site too, and an editor typing a story title should find it whichever
//    of the three it happens to be.
// -----------------------------------------------------------------------------
$profile = Profile::load('default');
if ($profile) {
  $existing = [];
  foreach ($profile->getMatchers() as $matcher) {
    $existing[] = $matcher->getPluginId();
  }

  $wanted = [
    'entity:media' => '[media:name]',
    'entity:taxonomy_term' => '[term:name]',
  ];

  foreach ($wanted as $plugin_id => $metadata) {
    if (in_array($plugin_id, $existing, TRUE)) {
      $log('ok', "linkit matcher $plugin_id");
      continue;
    }
    $profile->addMatcher([
      'id' => $plugin_id,
      'settings' => [
        'metadata' => $metadata,
        'bundles' => [],
        'group_by_bundle' => FALSE,
        'substitution_type' => 'canonical',
        'limit' => 100,
      ],
    ]);
    $log('added', "linkit matcher $plugin_id");
  }
  $profile->save();
}

// -----------------------------------------------------------------------------
// 3. The text format.
//
//    `<a href>` alone means the filter strips a target, a rel or a class off
//    every body link on the way to the page, however the editor set it.
// -----------------------------------------------------------------------------
$format = FilterFormat::load('full_html');
if ($format) {
  $filter = $format->filters('filter_html');
  $config = $filter->getConfiguration();
  $allowed = $config['settings']['allowed_html'];

  if (str_contains($allowed, '<a href hreflang target>')) {
    $log('ok', 'filter_html allows target on <a>');
  }
  else {
    // `hreflang` comes with Linkit's own link handling; `target` is the point.
    $config['settings']['allowed_html'] = preg_replace(
      '/<a href[^>]*>/',
      '<a href hreflang target>',
      $allowed
    );
    $format->setFilterConfig('filter_html', $config);
    $format->save();
    $log('updated', 'filter_html allowed_html on <a>');
  }
}

// -----------------------------------------------------------------------------
// 4. CKEditor.
//
//    Two plugins, both configured on the editor rather than the format:
//    `linkit_extension` gives the link dialog the same entity autocomplete the
//    fields have, and `editor_advanced_link_link` adds the attribute controls,
//    including target. Without the second one an editor has nowhere to set a
//    target on a body link at all.
// -----------------------------------------------------------------------------
$editor = \Drupal::entityTypeManager()->getStorage('editor')->load('full_html');
if ($editor) {
  $settings = $editor->getSettings();
  $plugins = $editor->getSettings()['plugins'] ?? [];
  $before = $plugins;

  $plugins['linkit_extension'] = [
    'linkit_enabled' => TRUE,
    'linkit_profile' => 'default',
  ];

  // Target only, to match the fields. Anything else offered here would be
  // stripped by the text format anyway.
  $plugins['editor_advanced_link_link'] = [
    'enabled_attributes' => ['target'],
  ];

  if ($plugins === $before) {
    $log('ok', 'ckeditor link plugins');
  }
  else {
    $settings['plugins'] = $plugins;
    $editor->setSettings($settings);
    $editor->save();
    $log('updated', 'ckeditor linkit and advanced link');
  }
}

print "\nDone. Now run: drush cex -y\n";
