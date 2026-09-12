<?php

/**
 * @file
 * Makes every image style output WebP.
 *
 * Run it with:
 *   drush php:script scripts/drupal/configure-image-styles.php
 *
 * then export with `drush cex -y`.
 *
 * Idempotent, like the other scripts here: it reports what it changed and
 * skips anything already correct.
 *
 * WHAT IT DOES
 *
 * Adds a convert effect set to WebP to any style that has none, and changes
 * the extension on any style that converts to something else. The effect is
 * added last, after the crop and any quality setting, which is where the
 * styles that already had it put it.
 *
 * TWO PLUGINS DO THIS JOB
 *
 * Core ships two convert effects and this site uses both. `image_convert` is
 * the long standing one; `image_convert_avif` is newer, subclasses it, and
 * offers AVIF as well. Five styles that came with core use the second one,
 * already set to WebP. Matching only on the exact id `image_convert` therefore
 * reads those five as having no convert effect at all and adds a second one,
 * which is a real bug this script hit on its first run. Anything whose plugin
 * id begins `image_convert` counts, and a style carrying more than one keeps
 * the first and drops the rest.
 *
 * WHAT YOU WILL TYPICALLY FIND
 *
 * Most of a Drupal 11 site's styles are already WebP, because the ones core
 * ships were changed over. The two kinds that are not:
 *
 *   og_image                  often produces JPEG, because social share
 *                             previews historically needed one. See below.
 *   linkit_result_thumbnail   ships from Linkit with no convert effect at all,
 *                             and any other contributed module's style may do
 *                             the same. It is a 50x50 admin thumbnail in an
 *                             autocomplete dropdown, so the saving is tiny,
 *                             but leaving one style out makes the rule harder
 *                             to state than it is worth.
 *
 * WEBP FOR SHARE IMAGES
 *
 * `og_image` is what Facebook, X, LinkedIn and Slack fetch for a link preview,
 * and it converts to JPEG on purpose on older sites. That is no longer
 * necessary: LinkedIn was the last platform to add WebP support, in December
 * 2024, and the rest accept it. Facebook's published documentation still lists
 * only JPG, PNG and GIF, which is a documentation lag rather than a real
 * limit, but it is the reason this is called out here rather than left silent.
 *
 * If share previews ever break on a specific platform, this is the first thing
 * to suspect. Set `$extension` below to 'jpg', re-run, and export.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

use Drupal\image\Entity\ImageStyle;

$log = function (string $action, string $what) {
  printf("%-9s %s\n", $action, $what);
};

$extension = 'webp';

foreach (ImageStyle::loadMultiple() as $style) {
  // Both convert plugins count. See the note above.
  $converts = [];
  foreach ($style->getEffects() as $effect) {
    if (str_starts_with($effect->getPluginId(), 'image_convert')) {
      $converts[] = $effect;
    }
  }

  // More than one convert effect is always wrong: the last to run wins and the
  // others are wasted work. Keep the first, drop the rest.
  $convert = array_shift($converts);
  if ($converts) {
    foreach ($converts as $extra) {
      $style->deleteImageEffect($extra);
    }
    $style->save();
    $log('deduped', $style->id() . ' (' . count($converts) . ' extra convert effect removed)');
  }

  if ($convert) {
    $config = $convert->getConfiguration();
    if (($config['data']['extension'] ?? NULL) === $extension) {
      $log('ok', $style->id());
      continue;
    }
    $was = $config['data']['extension'] ?? '?';
    $config['data']['extension'] = $extension;
    $convert->setConfiguration($config);
    $style->save();
    $log('changed', $style->id() . " ($was -> $extension)");
    continue;
  }

  // No convert effect at all. Add one after everything else, so it runs on the
  // finished derivative rather than before the crop.
  $weight = 0;
  foreach ($style->getEffects() as $effect) {
    $weight = max($weight, $effect->getWeight());
  }
  $style->addImageEffect([
    'id' => 'image_convert',
    'weight' => $weight + 1,
    'data' => ['extension' => $extension],
  ]);
  $style->save();
  $log('added', $style->id() . " (-> $extension)");
}

print "\nDone. Now run: drush cex -y\n";
