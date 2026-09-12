<?php

/**
 * @file
 * Creates paragraph types, their fields, and their form and view displays.
 *
 * Run it with:
 *   drush php:script scripts/drupal/create-paragraph-types.php
 *
 * then export the result with `drush cex -y`. The exported YAML under config/
 * is what gets committed and what a deploy imports; this script is only the
 * thing that produced it, kept in the repo so the content model can be re-read
 * and re-run rather than reverse engineered from a directory of YAML files.
 *
 * It is idempotent. Anything that already exists is left alone and reported as
 * "exists", so it is safe to edit one entry below and run the whole thing
 * again. It never deletes anything.
 *
 * THIS IS A TEMPLATE. The two bundles below are examples, chosen because
 * between them they exercise every awkward case: plain text, rich text, a media
 * reference, a link, a select list, and a nested-paragraph field whose children
 * are themselves paragraphs. Replace them with the real content model. Leave
 * the $widgets, $formatters and build sections alone.
 *
 * WHY A SCRIPT AND NOT HAND WRITTEN YAML
 *
 * A paragraph type is a bundle, a set of field storages, a field instance per
 * bundle per field, plus a form display and a view display. Twenty-five bundles
 * comes to roughly 160 files, every one carrying a uuid, a dependency list and
 * a full set of defaults that Drupal is fussy about. Going through the entity
 * API means Drupal writes all of that itself.
 *
 * THE SHAPE OF THE DATA
 *
 * $storages declares each field once, per its storage settings: the field type,
 * and the cardinality. A storage is per entity type, so a field declared here
 * on `paragraph` is separate from a node field of the same name.
 *
 * $types declares each paragraph type and, for each, the fields it carries with
 * any per-bundle overrides: label, required, and handler settings such as which
 * media bundles or which child paragraph types are allowed. Labels live on the
 * instance rather than the storage, which is what lets one `field_heading` be
 * "Heading" on a panel and "Name" on a person.
 *
 * Form and view display entries are derived from the field list, in the order
 * the fields are declared, so FIELD ORDER IS FORM ORDER. Order the fields the
 * way the design reads: top to bottom, then left to right. Styling options such
 * as a background choice go last, because they are not part of the reading
 * order. The exception worth making is a field that decides which other fields
 * get drawn, which belongs first.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

// -----------------------------------------------------------------------------
// The text format rich text fields are restricted to.
//
// Checked once here rather than assumed in the build loop. `full_html` is the
// usual answer on a site where only editors write content; a site with untrusted
// authors wants `basic_html` instead.
// -----------------------------------------------------------------------------
$text_format = 'full_html';

// -----------------------------------------------------------------------------
// Field storages.  REPLACE THESE.
//
// A storage is per entity type, so all of these are new on `paragraph` even
// where a node field of the same name already exists.
//
// Every field type used here needs an entry in both $widgets and $formatters
// below. Those two ship with twelve types filled in, which is more than these
// examples use, so most fields you add are already covered.
// -----------------------------------------------------------------------------
$storages = [
  'field_heading' => ['type' => 'string'],
  'field_lead' => ['type' => 'string_long'],
  'field_body' => ['type' => 'text_long'],
  'field_media' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'media']],
  'field_link' => ['type' => 'link'],
  'field_items' => ['type' => 'entity_reference_revisions', 'cardinality' => -1, 'settings' => ['target_type' => 'paragraph']],
  // A list field takes its allowed values as a plain value => label map when it
  // is set through the API. Drupal converts that into the list of
  // {value, label} mappings the exported YAML shows. Passing the exported shape
  // here would get structured a second time and fail validation. See
  // ListItemBase::storageSettingsToConfigData().
  'field_background' => ['type' => 'list_string', 'settings' => ['allowed_values' => [
    'neutral' => 'Neutral',
    'white' => 'White',
  ]]],
];

// Shorthands used in the type definitions below, to keep them readable.
$image_only = ['handler_settings' => ['target_bundles' => ['image' => 'image']]];

/**
 * Builds a child-paragraph field definition.
 *
 * The paragraphs widget wants the allowed bundles keyed by bundle with a
 * weight and an enabled flag, and separately as a plain target_bundles list.
 */
$children = function (array $bundles, string $label = 'Items', bool $required = FALSE): array {
  $drag_drop = [];
  $weight = 0;
  foreach ($bundles as $bundle) {
    $drag_drop[$bundle] = ['weight' => $weight++, 'enabled' => TRUE];
  }
  return [
    'label' => $label,
    'required' => $required,
    'handler_settings' => [
      'target_bundles' => array_combine($bundles, $bundles),
      'negate' => 0,
      'target_bundles_drag_drop' => $drag_drop,
    ],
  ];
};

// -----------------------------------------------------------------------------
// Paragraph types.  REPLACE THESE.
//
// `top_level` marks the ones an editor can add straight to a page. Everything
// else only ever appears inside a parent. This script does not act on the flag
// itself: use it to build the allowed-bundle list on the node's own paragraph
// field, which is the last section of this file.
//
// The `description` on each type is the closest thing a site has to built-in
// documentation, because it is what an editor reads on the Add menu. It is
// maintained here rather than in the admin interface, so it survives a re-run.
// -----------------------------------------------------------------------------
$types = [
  'example_grid' => [
    'label' => 'Example Grid',
    'description' => 'A heading, an introduction, and a row of cards under it. Replace this with a real component.',
    'top_level' => TRUE,
    'fields' => [
      'field_heading' => ['label' => 'Heading', 'required' => TRUE],
      'field_body' => ['label' => 'Introduction', 'description' => 'Optional copy between the heading and the cards.'],
      // One allowed child type, which is what makes the build loop below set
      // default_paragraph_type and turn the Add button into one click.
      'field_items' => $children(['example_card'], 'Cards'),
      // Styling last, because it is not part of the reading order.
      'field_background' => ['label' => 'Background'],
    ],
  ],

  'example_card' => [
    'label' => 'Example Card',
    'description' => 'One card inside an Example Grid. Not available on its own.',
    'fields' => [
      // The image comes first because it sits above the text in the design,
      // and field order is form order.
      'field_media' => ['label' => 'Image'] + $image_only,
      'field_heading' => ['label' => 'Title', 'required' => TRUE],
      'field_lead' => ['label' => 'Summary'],
      'field_link' => ['label' => 'Link'],
    ],
  ],
];

// -----------------------------------------------------------------------------
// Widget and formatter choices, by field type.
//
// Kept in one place so every bundle gets the same treatment for the same field
// type, rather than each entry repeating it.
// -----------------------------------------------------------------------------
$widgets = [
  'string' => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => '']],
  'string_long' => ['type' => 'string_textarea', 'settings' => ['rows' => 3, 'placeholder' => '']],
  'text_long' => ['type' => 'text_textarea', 'settings' => ['rows' => 6, 'placeholder' => '']],
  'entity_reference' => ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '']],
  // Linkit, with the attributes panel and `target` as the only attribute on
  // it. This is the same widget configure-links.php sets, written here as well
  // because this script sets a widget on every field it touches: with
  // `link_default` here, running it undid Linkit on every link field on the
  // site and nothing said so. The two have to stay in step.
  'link' => ['type' => 'linkit_attributes', 'settings' => [
    'placeholder_url' => '',
    'placeholder_title' => '',
    'linkit_profile' => 'default',
    'linkit_auto_link_text' => FALSE,
    'enabled_attributes' => [
      'id' => FALSE,
      'name' => FALSE,
      'target' => TRUE,
      'rel' => FALSE,
      'class' => FALSE,
      'accesskey' => FALSE,
      'aria-label' => FALSE,
      'title' => FALSE,
    ],
    'widget_default_open' => 'expandIfValuesSet',
  ]],
  'list_string' => ['type' => 'options_select', 'settings' => []],
  'boolean' => ['type' => 'boolean_checkbox', 'settings' => ['display_label' => TRUE]],
  'integer' => ['type' => 'number', 'settings' => ['placeholder' => '']],
  'datetime' => ['type' => 'datetime_default', 'settings' => []],
  'email' => ['type' => 'email_default', 'settings' => ['placeholder' => '', 'size' => 60]],
  'telephone' => ['type' => 'telephone_default', 'settings' => ['placeholder' => '']],
  'entity_reference_revisions' => ['type' => 'paragraphs', 'settings' => [
    'title' => 'Item',
    'title_plural' => 'Items',
    // Collapsed children showing a rendered preview, re-collapsing after each
    // save, added from one dropdown button. These four are also swept across
    // every existing field by configure-paragraph-widgets.php, and the two
    // have to stay in step: whichever runs last wins, so a change here that is
    // not made there is undone by the next sweep, and the reverse.
    'edit_mode' => 'closed',
    'closed_mode' => 'preview',
    'autocollapse' => 'all',
    'closed_mode_threshold' => 0,
    'add_mode' => 'dropdown',
    'form_display_mode' => 'default',
    'default_paragraph_type' => '',
    'features' => ['collapse_edit_all' => 'collapse_edit_all', 'duplicate' => 'duplicate'],
  ]],
];

// -----------------------------------------------------------------------------
// Widget overrides, by field NAME rather than field type.
//
// This is keyed on the field name and not the field type on purpose, and the
// reason is worth knowing before you decide it is over-engineered.
//
// `field_media` is an `entity_reference`, and so is any field pointing at a
// taxonomy term or a node. But a media reference wants the Media Library
// widget: a thumbnail, add/remove/replace controls, and the upload form with
// the focal point crosshair on it. A term or node reference wants the plain
// autocomplete, which is still the right control there. One field type, two
// correct answers, so the choice cannot live in $widgets above.
//
// `media_types` is left empty on purpose. It is a narrowing filter applied on
// top of the field's own `target_bundles`, and every field_media instance
// already restricts itself there (image only, or image and video), so setting
// it a second time here would be a duplicate that could drift.
// -----------------------------------------------------------------------------
$widget_overrides = [
  'field_media' => ['type' => 'media_library_widget', 'settings' => ['media_types' => []]],
];

$formatters = [
  'string' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
  // Every field type used above needs an entry here. A missing one leaves the
  // formatter type as NULL, and `setComponent()` then dies inside
  // FormatterPluginManager with an array_intersect_key() type error that names
  // the field but not the cause. That silently aborted this script partway
  // through for a while, so the last few bundles kept a stale form order.
  'entity_reference' => ['type' => 'entity_reference_label', 'settings' => ['link' => FALSE]],
  'string_long' => ['type' => 'basic_string', 'settings' => []],
  'text_long' => ['type' => 'text_default', 'settings' => []],
  'link' => ['type' => 'link', 'settings' => ['trim_length' => NULL, 'url_only' => FALSE, 'url_plain' => FALSE, 'rel' => '', 'target' => '']],
  'list_string' => ['type' => 'list_default', 'settings' => []],
  'boolean' => ['type' => 'boolean', 'settings' => ['format' => 'default', 'format_custom_false' => '', 'format_custom_true' => '']],
  'integer' => ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => TRUE]],
  'datetime' => ['type' => 'datetime_default', 'settings' => ['timezone_override' => '', 'format_type' => 'medium']],
  'email' => ['type' => 'basic_string', 'settings' => []],
  'telephone' => ['type' => 'basic_string', 'settings' => []],
  'entity_reference_revisions' => ['type' => 'entity_reference_revisions_entity_view', 'settings' => ['view_mode' => 'default', 'link' => '']],
];

// Fields a Twig template reads off the entity rather than out of the render
// array, so they must not print themselves as well. `field_media` is the usual
// one: the template dereferences it to a file and builds its own <picture>, so
// leaving it in the view display renders the image twice.
$hidden_in_view = ['field_media'];

// -----------------------------------------------------------------------------
// Build. Nothing below here is site specific.
// -----------------------------------------------------------------------------

$log = function (string $action, string $what) {
  printf("%-8s %s\n", $action, $what);
};

// Field storages.
foreach ($storages as $name => $spec) {
  if (FieldStorageConfig::loadByName('paragraph', $name)) {
    $log('exists', "storage paragraph.$name");
    continue;
  }
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'type' => $spec['type'],
    'cardinality' => $spec['cardinality'] ?? 1,
    'settings' => $spec['settings'] ?? [],
  ])->save();
  $log('created', "storage paragraph.$name");
}

// Paragraph types, their fields and their displays.
foreach ($types as $bundle => $type) {
  if (!ParagraphsType::load($bundle)) {
    ParagraphsType::create([
      'id' => $bundle,
      'label' => $type['label'],
      'description' => $type['description'] ?? '',
    ])->save();
    $log('created', "type $bundle ({$type['label']})");
  }
  else {
    $existing = ParagraphsType::load($bundle);
    $wanted = $type['description'] ?? '';
    if ($existing->label() !== $type['label'] || $existing->getDescription() !== $wanted) {
      $existing->set('label', $type['label']);
      $existing->set('description', $wanted);
      $existing->save();
      $log('updated', "type $bundle description");
    }
    else {
      $log('exists', "type $bundle");
    }
  }

  $form_display = EntityFormDisplay::load("paragraph.$bundle.default")
    ?: EntityFormDisplay::create(['targetEntityType' => 'paragraph', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $view_display = EntityViewDisplay::load("paragraph.$bundle.default")
    ?: EntityViewDisplay::create(['targetEntityType' => 'paragraph', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);

  $weight = 0;
  foreach ($type['fields'] as $name => $field) {
    $field_type = $storages[$name]['type'];

    if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
      $settings = [];
      // Reference fields carry their allowed-bundle rules in handler_settings.
      if (isset($field['handler_settings'])) {
        $target = $storages[$name]['settings']['target_type'];
        $settings['handler'] = "default:$target";
        $settings['handler_settings'] = $field['handler_settings'];
      }
      // Rich text is restricted to the one format the site has.
      if ($field_type === 'text_long') {
        $settings['allowed_formats'] = [$text_format];
      }

      $config = [
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'bundle' => $bundle,
        'label' => $field['label'],
        'description' => $field['description'] ?? '',
        'required' => $field['required'] ?? FALSE,
        'settings' => $settings,
      ];
      if (isset($field['default'])) {
        $config['default_value'] = [['value' => $field['default']]];
      }
      FieldConfig::create($config)->save();
      $log('created', "field paragraph.$bundle.$name");
    }
    else {
      // Keep the label and the description in step on a field that already
      // exists, the same way the paragraph type above does. Setting them only
      // on creation meant an edit here never reached a site where the field
      // was already there, so the wording had to be corrected by hand in the
      // UI and was lost on the next environment.
      $existing_field = FieldConfig::loadByName('paragraph', $bundle, $name);
      $wanted_description = $field['description'] ?? '';
      if ($existing_field->label() !== $field['label']
        || $existing_field->getDescription() !== $wanted_description) {
        $existing_field->set('label', $field['label']);
        $existing_field->set('description', $wanted_description);
        $existing_field->save();
        $log('updated', "field paragraph.$bundle.$name");
      }
      else {
        $log('exists', "field paragraph.$bundle.$name");
      }
    }

    // Form display. The paragraphs widget gets the child label from the field.
    $widget = $widget_overrides[$name] ?? $widgets[$field_type];
    if ($field_type === 'entity_reference_revisions') {
      $widget['settings']['title'] = rtrim($field['label'], 's');
      $widget['settings']['title_plural'] = $field['label'];

      // When a field allows exactly one kind of child there is nothing for an
      // editor to choose, so name it as the default and the Add button adds it
      // in one click instead of opening a menu of one.
      //
      // This is also what stops a re-run of this script undoing the setting.
      // The widget's settings are written wholesale below, so a
      // `default_paragraph_type` set by hand in the UI gets wiped every time
      // the script runs, silently. Deriving it here means the script produces
      // the right value rather than destroying it.
      $allowed = array_keys($field['handler_settings']['target_bundles'] ?? []);
      if (count($allowed) === 1) {
        $widget['settings']['default_paragraph_type'] = reset($allowed);
      }
    }
    $form_display->setComponent($name, [
      'type' => $widget['type'],
      'weight' => $weight,
      'region' => 'content',
      'settings' => $widget['settings'],
      'third_party_settings' => [],
    ]);

    // View display. Labels are always hidden: the components draw their own.
    if (in_array($name, $hidden_in_view, TRUE)) {
      $view_display->removeComponent($name);
    }
    else {
      $view_display->setComponent($name, [
        'type' => $formatters[$field_type]['type'],
        'label' => 'hidden',
        'weight' => $weight,
        'region' => 'content',
        'settings' => $formatters[$field_type]['settings'],
        'third_party_settings' => [],
      ]);
    }

    $weight += 5;
  }

  // Paragraphs shows these on the form by default and no editor needs them.
  foreach (['created', 'status'] as $base) {
    $form_display->removeComponent($base);
  }

  $form_display->save();
  $view_display->save();
  $log('saved', "displays paragraph.$bundle.default");
}

// -----------------------------------------------------------------------------
// The node side.  REPLACE THE BUNDLE NAMES.
//
// A node's paragraph field allows every paragraph bundle by default, so the Add
// menu on a page would offer Example Card alongside the real sections. Restrict
// it to the types marked `top_level` above, in the order they are declared, and
// stop the field's own label printing.
// -----------------------------------------------------------------------------
$node_bundles = ['page'];
$node_field = 'field_components';

$top_level = [];
foreach ($types as $bundle => $type) {
  if (!empty($type['top_level'])) {
    $top_level[] = $bundle;
  }
}

$drag_drop = [];
$weight = 0;
foreach ($top_level as $bundle) {
  $drag_drop[$bundle] = ['weight' => $weight++, 'enabled' => TRUE];
}

foreach ($node_bundles as $node_bundle) {
  $field = FieldConfig::loadByName('node', $node_bundle, $node_field);
  if (!$field) {
    $log('skipped', "node.$node_bundle.$node_field does not exist");
    continue;
  }

  $field->setSetting('handler_settings', [
    'target_bundles' => array_combine($top_level, $top_level),
    'negate' => 0,
    'target_bundles_drag_drop' => $drag_drop,
  ]);
  $field->save();
  $log('updated', "node.$node_bundle.$node_field allowed bundles");

  $display = EntityViewDisplay::load("node.$node_bundle.default");
  if ($display && ($component = $display->getComponent($node_field)) && $component['label'] !== 'hidden') {
    $component['label'] = 'hidden';
    $display->setComponent($node_field, $component)->save();
    $log('fixed', "node.$node_bundle $node_field label hidden");
  }
}

print "\nDone. Now run: drush cex -y\n";
