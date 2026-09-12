<?php

/**
 * @file
 * Media entry forms: the Media Library modal, and the focal point widget.
 *
 * Run with: drush php:script scripts/drupal/configure-media.php
 *
 * Idempotent, like every other script in this directory: it checks the current
 * widget before writing, so re-running after a tweak is safe.
 *
 * WHY THIS EXISTS
 *
 * An image media entity has two entry forms, not one, and they are separate
 * pieces of config that do not inherit from each other:
 *
 *   media.image.default         /media/add/image and /media/N/edit
 *   media.image.media_library   the panel inside the Media Library modal
 *
 * The second one is what an editor actually sees when they add an image from a
 * paragraph, because the paragraph's field uses the Media Library widget. It
 * shipped from core with the plain `image_image` widget, so a newly uploaded
 * image had no focal point crosshair and the editor had to leave the node,
 * find the media item, and set the focal point there. Both forms now use
 * focal_point's `image_focal_point` widget.
 *
 * ONE DIFFERENCE BETWEEN THE TWO FORMS
 *
 * `preview_link` adds a "Preview" button that opens the crops in a dialog. On
 * the standalone media form that is useful. Inside the Media Library it would
 * be a dialog opened from inside a dialog, so it is off there.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;

$log = function (string $verb, string $what): void {
  print str_pad($verb, 10) . $what . "\n";
};

$widget = [
  'type' => 'image_focal_point',
  'weight' => -50,
  'region' => 'content',
  'settings' => [
    'progress_indicator' => 'throbber',
    'preview_image_style' => 'large',
    'preview_link' => FALSE,
    'offsets' => '50,50',
  ],
  'third_party_settings' => [],
];

$display = EntityFormDisplay::load('media.image.media_library');

if (!$display) {
  $log('missing', 'media.image.media_library form display');
  print "\nThe media_library form mode is core config. Is the media_library\n"
    . "module enabled? Nothing was changed.\n";
  return;
}

$current = $display->getComponent('field_media_image');

if (($current['type'] ?? NULL) === $widget['type']) {
  $log('exists', 'focal point widget on media.image.media_library');
}
else {
  // Keep whatever weight the form already had, so this does not reorder a
  // display somebody has since customised.
  if (isset($current['weight'])) {
    $widget['weight'] = $current['weight'];
  }
  $display->setComponent('field_media_image', $widget)->save();
  $log('set', 'focal point widget on media.image.media_library');
}

print "\nDone. Now run: drush cex -y\n";
