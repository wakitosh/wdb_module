<?php

namespace Drupal\wdb_example\Commands;

use Drush\Commands\DrushCommands;
use Drupal\wdb_example\Service\WdbExampleManager;

/**
 * Drush commands for wdb_example.
 */
class WdbExampleCommands extends DrushCommands {

  /**
   * The example manager service.
   *
   * @var \Drupal\wdb_example\Service\WdbExampleManager
   */
  protected $manager;

  /**
   * Constructs the command object.
   */
  public function __construct(WdbExampleManager $manager) {
    parent::__construct();
    $this->manager = $manager;
  }

  /**
   * Mark existing example data as purge targets.
   *
   * @command wdb-example:mark
   * @aliases wdbex:mark
   * @option wipe-map Drop and recreate the tracking table before marking.
   * @option dry-run Show what would be marked without writing.
   */
  public function mark(array $options = ['wipe-map' => FALSE, 'dry-run' => FALSE]): int {
    $dry_run = (bool) ($options['dry-run'] ?? FALSE);
    $wipe_map = (bool) ($options['wipe-map'] ?? FALSE);

    $counts = $this->manager->markExisting($wipe_map, $dry_run);

    $lines = [];
    foreach ($counts as $type => $n) {
      if ($n) {
        $lines[] = sprintf('%s: %d', $type, $n);
      }
    }

    if (!$lines) {
      $this->logger()->notice('No entities were marked.');
    }
    else {
      $prefix = $dry_run ? '[dry-run] would mark' : 'Marked';
      $this->logger()->success($prefix . ' the following entity counts: ' . implode(', ', $lines));
    }

    return 0;
  }

}
