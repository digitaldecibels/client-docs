<?php

/**
 * @file
 * The shortcut bar, and one admin link on it.
 *
 * TWO HALVES, AND ONLY ONE IS PORTABLE
 *
 * Turning the bar on and granting every signed-in role the two permissions it
 * needs is the same on any site, and is the reusable half. The link itself is
 * whatever this site's editors reach for often, so the one at the bottom is a
 * placeholder to replace.
 *
 * Run it with:
 *   drush php:script scripts/drupal/configure-shortcuts.php
 *
 * WHY THIS IS A SCRIPT AND NOT CONFIG
 *
 * A shortcut *set* is configuration and exports normally. A shortcut *link* is
 * a content entity and does not. So the set arrives on Live with the rest of a
 * deploy and the links do not, which is a good way to wonder why the bar is
 * empty there. This script creates the link, and has to be run once per
 * environment like create-demo-content.php and build-pages.php.
 *
 * The permissions it grants are config and do export.
 *
 * Idempotent: it checks for the link before creating it, so re-running is
 * harmless and is the normal way to work.
 *
 * WHAT RUNS DRUSH
 *
 * `drush` above means whatever runs Drush in your project: `lando drush`,
 * `ddev drush`, `terminus drush` on Pantheon, or plain `drush` on a host where
 * it is on the path.
 */

declare(strict_types=1);

use Drupal\shortcut\Entity\Shortcut;

$log = function (string $action, string $what): void {
  printf("%-9s %s\n", $action, $what);
};

if (!\Drupal::moduleHandler()->moduleExists('shortcut')) {
  \Drupal::service('module_installer')->install(['shortcut']);
  $log('enabled', 'shortcut module');
}

// -----------------------------------------------------------------------------
// Permissions.
//
// Every signed-in role gets the bar and the ability to add their own links to
// it. Neither permission grants access to anything a user could not already
// reach: the bar only ever shows links to pages they can already open, and
// customising it changes nothing but their own list.
// -----------------------------------------------------------------------------
foreach (\Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple() as $role) {
  if ($role->id() === 'anonymous') {
    continue;
  }

  $changed = FALSE;
  foreach (['access shortcuts', 'customize shortcut links'] as $permission) {
    if (!$role->hasPermission($permission)) {
      $role->grantPermission($permission);
      $changed = TRUE;
    }
  }

  if ($changed) {
    $role->save();
    $log('granted', "shortcut permissions to {$role->id()}");
  }
}

// -----------------------------------------------------------------------------
// The link itself.
// -----------------------------------------------------------------------------
// REPLACE THIS. Point it at the admin page this site's editors open most.
// Both values describe the same destination: $route is what the idempotency
// check below matches on, and $path is what actually gets saved on the link.
$route = 'my_module.some_admin_page';
$path = '/admin/config/content/some-admin-page';
$title = 'Some admin page';
$existing = \Drupal::entityTypeManager()->getStorage('shortcut')
  ->loadByProperties(['shortcut_set' => 'default']);

foreach ($existing as $shortcut) {
  if ($shortcut->getUrl()->isRouted() && $shortcut->getUrl()->getRouteName() === $route) {
    $log('found', "$title shortcut already exists");
    return;
  }
}

Shortcut::create([
  'shortcut_set' => 'default',
  'title' => $title,
  'weight' => -10,
  'link' => ['uri' => 'internal:' . $path],
])->save();

$log('created', "$title shortcut");

print "\nDone. The shortcut bar appears at the top of admin pages for signed-in\n";
print "users. Toggle it with the link in the toolbar.\n";
