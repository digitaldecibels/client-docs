<?php

/**
 * @file
 * Named styles in CKEditor, so an editor can apply a designed variant.
 *
 * Run it with:
 *   drush php:script scripts/drupal/configure-editor.php
 *
 * then export with `drush cex -y`.
 *
 * Idempotent, like the other scripts here: it reports what it changed and
 * skips anything already correct.
 *
 * WHAT THIS IS FOR
 *
 * A theme's stylesheet almost always draws more variants than an editor can
 * reach. A designed pull quote wants an attribution line, a callout wants a
 * lead paragraph, a table wants a compact mode. The CSS is there and the
 * editor has no control that produces it.
 *
 * This adds CKEditor 5's Style plugin, which is a dropdown that puts a named
 * class on a block element. The editor selects the line and picks the style
 * by name.
 *
 * The alternative that keeps getting reached for, and should not be, is a
 * structural convenience in the CSS: styling the last paragraph of a
 * blockquote as the attribution, say. That works until someone writes a quote
 * that genuinely runs to two paragraphs, and nothing ever told the editor the
 * rule existed. A named style is discoverable and says what it does.
 *
 * TWO THINGS HAVE TO LINE UP, AND THE SECOND ONE FAILS SILENTLY
 *
 *   1. The style has to be configured on the editor and `style` added to the
 *      toolbar, or there is no control to use.
 *   2. The text format's `allowed_html` has to permit that class on that tag,
 *      or `filter_html` strips it back off on the way to the page. The editor
 *      saves it, the markup looks right in the source view, and the page shows
 *      a plain paragraph with no error anywhere.
 *
 * The filter is changed first, because Drupal validates an editor's styles
 * against what the format allows.
 *
 * ORDER OF WORK, FOR EACH STYLE YOU ADD
 *
 * Add it to $styles below, add its class to the matching tag in
 * $allowed_classes, and re-run. The element has to be one some other CKEditor
 * plugin can already create: the Style plugin adds a class to a tag, it cannot
 * introduce the tag itself. So `<p class="lead">` works because the editor can
 * already make a paragraph, and `<aside class="callout">` does not, because
 * nothing in the toolbar produces an `<aside>`.
 *
 * The class also has to exist in the theme's CSS, or the dropdown offers a
 * style that does nothing visible. This script does not check that.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

use Drupal\filter\Entity\FilterFormat;

$log = function (string $action, string $what) {
  printf("%-9s %s\n", $action, $what);
};

$format_id = 'full_html';

// -----------------------------------------------------------------------------
// The styles themselves.
//
// `element` is the tag the class lands on, written the way `allowed_html`
// writes it. `label` is what an editor reads in the dropdown, so it says what
// the thing is rather than naming the class.
// -----------------------------------------------------------------------------
// FILL THIS IN. One entry per named style you want in the dropdown.
//
// A worked example, from a site whose stylesheet draws a pull quote with an
// attribution line under it:
//
//   $styles = [
//     ['label' => 'Quote attribution', 'element' => '<p class="attribution">'],
//   ];
$styles = [];

// Classes to add to a tag's entry in `allowed_html`, keyed by tag. Kept
// separate from $styles because a tag's entry may already list other classes,
// which have to survive.
// FILL THIS IN, to match $styles above. Every class named there needs its tag
// listed here, or filter_html strips it back off and the page shows nothing.
//
// Matching the worked example above:
//
//   $allowed_classes = [
//     'p' => ['attribution'],
//   ];
$allowed_classes = [];

// Nothing to do until $styles has an entry, and stopping here matters: writing
// an empty style list while adding the toolbar button gives CKEditor 5 a Style
// dropdown with nothing in it, which Drupal rejects as an invalid editor
// configuration. So the template is inert until it is filled in.
if (!$styles) {
  echo "\$styles is empty. Fill it in near the top of this file, then re-run.\n";
  return;
}

// -----------------------------------------------------------------------------
// 1. The text format.
// -----------------------------------------------------------------------------
$format = FilterFormat::load($format_id);
if (!$format) {
  echo "No $format_id format. Nothing to do.\n";
  return;
}

$filter = $format->filters('filter_html');
$config = $filter->getConfiguration();
$allowed = $config['settings']['allowed_html'];
$before = $allowed;

foreach ($allowed_classes as $tag => $classes) {
  // Match this tag's own entry only: `<p …>` and not `<pre …>`, so the word
  // boundary matters.
  if (!preg_match('/<' . $tag . '(\s[^>]*)?>/', $allowed, $match)) {
    $log('skipped', "$tag is not in allowed_html at all");
    continue;
  }

  $entry = $match[0];
  $attributes = $match[1] ?? '';

  // The existing class list for this tag, if it has one.
  $existing = [];
  if (preg_match('/class="([^"]*)"/', $attributes, $class_match)) {
    $existing = preg_split('/\s+/', trim($class_match[1]), -1, PREG_SPLIT_NO_EMPTY);
  }

  $missing = array_diff($classes, $existing);
  if (!$missing) {
    $log('ok', "allowed_html <$tag> classes");
    continue;
  }

  $merged = implode(' ', array_merge($existing, $missing));
  if ($existing) {
    $replacement = preg_replace('/class="[^"]*"/', 'class="' . $merged . '"', $entry);
  }
  else {
    // No class attribute yet, so add one before the closing bracket.
    $replacement = rtrim($entry, '>') . ' class="' . $merged . '">';
  }

  $allowed = str_replace($entry, $replacement, $allowed);
  $log('updated', "allowed_html <$tag> gains " . implode(', ', $missing));
}

if ($allowed !== $before) {
  $config['settings']['allowed_html'] = $allowed;
  $format->setFilterConfig('filter_html', $config);
  $format->save();
}

// -----------------------------------------------------------------------------
// 2. The editor: the Style plugin's settings, and its toolbar button.
// -----------------------------------------------------------------------------
$editor = \Drupal::entityTypeManager()->getStorage('editor')->load($format_id);
if (!$editor) {
  echo "No editor on $format_id. Nothing else to do.\n";
  return;
}

$settings = $editor->getSettings();
$changed = FALSE;

if (($settings['plugins']['ckeditor5_style']['styles'] ?? NULL) == $styles) {
  $log('ok', 'ckeditor5_style styles');
}
else {
  $settings['plugins']['ckeditor5_style']['styles'] = $styles;
  $changed = TRUE;
  $log('updated', 'ckeditor5_style styles');
}

$items = $settings['toolbar']['items'];
if (in_array('style', $items, TRUE)) {
  $log('ok', 'style button on the toolbar');
}
else {
  // Next to Block quote, because that is the button an editor reaches for
  // first and the style only makes sense inside what it produces.
  $at = array_search('blockQuote', $items, TRUE);
  if ($at === FALSE) {
    $items[] = 'style';
  }
  else {
    array_splice($items, $at + 1, 0, 'style');
  }
  $settings['toolbar']['items'] = $items;
  $changed = TRUE;
  $log('added', 'style button on the toolbar');
}

if ($changed) {
  $editor->setSettings($settings);
  $editor->save();
}

echo "\nDone. Now run: drush cex -y\n";
