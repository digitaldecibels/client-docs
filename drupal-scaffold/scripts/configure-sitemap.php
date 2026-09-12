<?php

/**
 * @file
 * XML sitemap covering every published node.
 *
 * Run it with:
 *   drush php:script scripts/drupal/configure-sitemap.php
 *   drush simple-sitemap:generate
 *
 * then export with `drush cex -y`. Idempotent, like the other scripts in
 * this directory.
 *
 * WHY simple_sitemap AND NOT xmlsitemap
 *
 * Both are in composer.json and neither was installed. They cannot both be
 * installed: they each want to own /sitemap.xml and would fight over the
 * route. simple_sitemap 4.2 is the one that is current for Drupal 11, is
 * actively maintained, and generates through the queue so a large site does
 * not time out. xmlsitemap is left uninstalled and should come out of
 * composer.json when someone is next in there.
 *
 * WHAT GOES IN
 *
 * Every published Page and every published Story. Unpublished content is
 * excluded by the module itself, which checks view access as the anonymous
 * user while it builds, so nothing private leaks into a public file.
 *
 * Priority and change frequency are hints, and search engines treat them as
 * such. Stories get a slightly higher change frequency because they are the
 * part of the site that grows; the numbers are not worth agonising over.
 *
 * REGENERATION
 *
 * The sitemap is a cached artefact, not a live query, so it has to be
 * regenerated when content changes. `cron_generate` does that on every cron
 * run, which on Pantheon is hourly by default. There is no need for anything
 * else unless the client wants same-minute freshness.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

$log = function (string $action, string $what) {
  printf("%-8s %s\n", $action, $what);
};

$generator = \Drupal::service('simple_sitemap.generator');
$entity_manager = \Drupal::service('simple_sitemap.entity_manager');
$settings = \Drupal::service('simple_sitemap.settings');

// The module ships a "default" sitemap. Confirm it is there and switched on
// before trying to put anything in it: setBundleSettings() silently does
// nothing when there are no sitemaps, which is a quiet way to waste an hour.
$sitemaps = $generator->entityManager()->getSitemaps();
if (empty($sitemaps)) {
  print "No sitemap variants exist. Create one at /admin/config/search/simplesitemap/variants first.\n";
  return;
}
$log('found', 'sitemap variants: ' . implode(', ', array_keys($sitemaps)));

// -----------------------------------------------------------------------------
// Which content goes in.
// -----------------------------------------------------------------------------
$entity_manager->enableEntityType('node');
$log('enabled', 'entity type node');

// Every content type is indexed, found by asking Drupal rather than by listing
// them, so a content type added later is picked up the next time this runs.
// A hook_node_type_insert() in the site's own module can index one the moment
// it is created, so nobody has to remember to re-run this.
//
// FILL THIS IN, or leave it empty.
//
// Per-type tuning, keyed by content type, for the few where it is worth having.
// Anything not named here gets the default below, which is a fine answer for
// most content. Priority and change frequency are hints and search engines
// treat them as such, so they are not worth agonising over.
//
// A worked example, from a site with a rarely changing Page and a growing
// Story, where each story is a landing page in its own right:
//
//   $tuning = [
//     'page'  => ['priority' => '0.5', 'changefreq' => 'monthly'],
//     'story' => ['priority' => '0.7', 'changefreq' => 'weekly'],
//   ];
$tuning = [];

$bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

foreach (array_keys($bundles) as $bundle) {
  $config = ($tuning[$bundle] ?? ['priority' => '0.5', 'changefreq' => 'monthly'])
    + ['index' => TRUE, 'include_images' => FALSE];
  $entity_manager->setBundleSettings('node', $bundle, $config);
  $log('indexed', "node.$bundle (priority {$config['priority']}, {$config['changefreq']})");
}

// Taxonomy terms and users are not content anyone should land on from a search
// result here, so they stay out. Stated rather than left to the default, so
// the intent is visible if someone wonders later.
foreach (['taxonomy_term', 'user', 'media'] as $entity_type) {
  $entity_manager->setBundleSettings($entity_type, NULL, ['index' => FALSE]);
}
$log('excluded', 'taxonomy_term, user, media');

// -----------------------------------------------------------------------------
// Generation settings.
// -----------------------------------------------------------------------------
$settings
  // Rebuild on cron, so a new story appears without anyone remembering to.
  ->save('cron_generate', TRUE)
  ->save('cron_generate_interval', 0)
  // 2000 links per file. The limit is 50,000, but smaller files are faster to
  // fetch and this site will not come close either way.
  ->save('max_links', 2000)
  // How many links each queue run processes. The default is fine for a site
  // this size and is set explicitly so it is not a mystery later.
  ->save('generate_duration', 10000)
  // Absolute URLs with the site's real base URL, which Pantheon needs because
  // the generation request has no browser host to infer from.
  ->save('base_url', '')
  // Do not advertise the sitemap in robots.txt automatically; Pantheon serves
  // a scaffolded robots.txt that is under version control, and a module
  // rewriting it would be a surprise.
  ->save('disable_language_hreflang', FALSE);
$log('saved', 'generation settings');

print "\nDone. Now run:\n  drush simple-sitemap:generate\n  drush cex -y\n";
