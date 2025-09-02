<?php

namespace Drupal\wdb_example\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to manage example data lifecycle (marking and purge).
 */
class WdbExampleManager {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $etm;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  public function __construct(Connection $database, EntityTypeManagerInterface $etm, TimeInterface $time, LoggerChannelFactoryInterface $loggerFactory) {
    $this->database = $database;
    $this->etm = $etm;
    $this->time = $time;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Ensure the tracking map table exists.
   */
  public function ensureMapTable(): void {
    if (!$this->database->schema()->tableExists('wdb_example_map')) {
      $this->database->schema()->createTable('wdb_example_map', $this->getMapTableSpec());
    }
  }

  /**
   * Truncate the map table (if present).
   */
  public function wipeMap(): void {
    if ($this->database->schema()->tableExists('wdb_example_map')) {
      $this->database->truncate('wdb_example_map')->execute();
    }
  }

  /**
   * Get counts of map entries.
   */
  public function getMapCounts(): array {
    $counts = [
      'total' => 0,
      'by_type' => [],
    ];
    if (!$this->database->schema()->tableExists('wdb_example_map')) {
      return $counts;
    }
    $query = $this->database->select('wdb_example_map', 'm')
      ->fields('m', ['entity_type']);
    $result = $query->execute();
    foreach ($result as $row) {
      $counts['total']++;
      $counts['by_type'][$row->entity_type] = ($counts['by_type'][$row->entity_type] ?? 0) + 1;
    }
    return $counts;
  }

  /**
   * Mark existing example data as purge targets.
   *
   * @param bool $wipeMap
   *   Whether to clear the map before marking.
   * @param bool $dryRun
   *   If TRUE, do not write changes, return counts only.
   *
   * @return array
   *   Counts per entity type.
   */
  public function markExisting(bool $wipeMap = FALSE, bool $dryRun = FALSE): array {
    $this->ensureMapTable();
    if ($wipeMap && !$dryRun) {
      $this->wipeMap();
    }

    $counts = [
      'wdb_source' => 0,
      'wdb_annotation_page' => 0,
      'wdb_label' => 0,
      'wdb_sign_interpretation' => 0,
      'wdb_word_unit' => 0,
      'wdb_word_meaning' => 0,
      'wdb_word' => 0,
      'wdb_sign_function' => 0,
      'wdb_sign' => 0,
      'wdb_word_map' => 0,
    ];

    $source_storage = $this->etm->getStorage('wdb_source');
    $sources = $source_storage->loadByProperties(['source_identifier' => 'nich-100412001']);
    $source = $sources ? reset($sources) : NULL;
    if (!$source) {
      return $counts;
    }

    $tag = function (string $type, string $uuid) use (&$counts, $dryRun) {
      if (!$uuid) {
        return;
      }
      if (!$dryRun) {
        $this->tagEntity($type, $uuid);
      }
      $counts[$type] = ($counts[$type] ?? 0) + 1;
    };

    // Source.
    $tag('wdb_source', $source->uuid());

    // Pages by source.
    $page_storage = $this->etm->getStorage('wdb_annotation_page');
    $page_ids = $page_storage->getQuery()->accessCheck(FALSE)->condition('source_ref', $source->id())->execute();
    $pages = $page_ids ? $page_storage->loadMultiple($page_ids) : [];
    foreach ($pages as $page) {
      $tag('wdb_annotation_page', $page->uuid());
    }

    // Labels and SIs by pages.
    $label_ids = $page_ids ? $this->etm->getStorage('wdb_label')->getQuery()->accessCheck(FALSE)->condition('annotation_page_ref', $page_ids, 'IN')->execute() : [];
    $labels = $label_ids ? $this->etm->getStorage('wdb_label')->loadMultiple($label_ids) : [];
    foreach ($labels as $label) {
      $tag('wdb_label', $label->uuid());
    }

    $si_ids = $page_ids ? $this->etm->getStorage('wdb_sign_interpretation')->getQuery()->accessCheck(FALSE)->condition('annotation_page_ref', $page_ids, 'IN')->execute() : [];
    $sis = $si_ids ? $this->etm->getStorage('wdb_sign_interpretation')->loadMultiple($si_ids) : [];
    foreach ($sis as $si) {
      $tag('wdb_sign_interpretation', $si->uuid());
    }

    // Word Units by source.
    $wu_storage = $this->etm->getStorage('wdb_word_unit');
    $wu_ids = $wu_storage->getQuery()->accessCheck(FALSE)->condition('source_ref', $source->id())->execute();
    $wus = $wu_ids ? $wu_storage->loadMultiple($wu_ids) : [];
    foreach ($wus as $wu) {
      $tag('wdb_word_unit', $wu->uuid());
    }

    // Word Maps linked to SIs or WUs.
    $wm_storage = $this->etm->getStorage('wdb_word_map');
    $wm_ids_a = $si_ids ? $wm_storage->getQuery()->accessCheck(FALSE)->condition('sign_interpretation_ref', $si_ids, 'IN')->execute() : [];
    $wm_ids_b = $wu_ids ? $wm_storage->getQuery()->accessCheck(FALSE)->condition('word_unit_ref', $wu_ids, 'IN')->execute() : [];
    $wm_ids = $wm_ids_a + $wm_ids_b;
    $wms = $wm_ids ? $wm_storage->loadMultiple($wm_ids) : [];
    foreach ($wms as $wm) {
      $tag('wdb_word_map', $wm->uuid());
    }

    // Meanings and Words via WUs.
    $meaning_id_map = [];
    foreach ($wus as $wu) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $wu */
      $mid = $wu->get('word_meaning_ref')->target_id ?? NULL;
      if ($mid) {
        $meaning_id_map[$mid] = TRUE;
      }
    }
    if ($meaning_id_map) {
      $meaning_storage = $this->etm->getStorage('wdb_word_meaning');
      $meanings = $meaning_storage->loadMultiple(array_keys($meaning_id_map));
      $word_ids = [];
      foreach ($meanings as $meaning) {
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $meaning */
        $tag('wdb_word_meaning', $meaning->uuid());
        $wid = $meaning->get('word_ref')->target_id ?? NULL;
        if ($wid) {
          $word_ids[$wid] = TRUE;
        }
      }
      if ($word_ids) {
        $words = $this->etm->getStorage('wdb_word')->loadMultiple(array_keys($word_ids));
        foreach ($words as $word) {
          $tag('wdb_word', $word->uuid());
        }
      }
    }

    // SignFunctions and Signs via SIs.
    $sf_ids = [];
    foreach ($sis as $si) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $si */
      $sfid = $si->get('sign_function_ref')->target_id ?? NULL;
      if ($sfid) {
        $sf_ids[$sfid] = TRUE;
      }
    }
    if ($sf_ids) {
      $sfs = $this->etm->getStorage('wdb_sign_function')->loadMultiple(array_keys($sf_ids));
      foreach ($sfs as $sf) {
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $sf */
        $tag('wdb_sign_function', $sf->uuid());
        $sid = $sf->get('sign_ref')->target_id ?? NULL;
        if ($sid) {
          $sign = $this->etm->getStorage('wdb_sign')->load($sid);
          if ($sign) {
            /** @var \Drupal\Core\Entity\FieldableEntityInterface $sign */
            $tag('wdb_sign', $sign->uuid());
          }
        }
      }
    }

    return $counts;
  }

  /**
   * Purge tracked entities. Returns a summary of results.
   *
   * @param bool $dropTable
   *   If TRUE, drop the map table after purge.
   */
  public function purgeTracked(bool $dropTable = TRUE): array {
    $summary = [
      'deleted' => [],
      'not_found' => [],
      'failed' => [],
      'total_deleted' => 0,
    ];

    if (!$this->database->schema()->tableExists('wdb_example_map')) {
      return $summary;
    }

    $result = $this->database->select('wdb_example_map', 'm')
      ->fields('m', ['entity_type', 'entity_uuid'])
      ->execute()
      ->fetchAll();

    if (!$result) {
      if ($dropTable) {
        $this->database->schema()->dropTable('wdb_example_map');
      }
      return $summary;
    }

    $by_type = [];
    foreach ($result as $row) {
      $by_type[$row->entity_type][] = $row->entity_uuid;
    }

    $order = $this->getDeletionOrder();

    foreach ($order as $type) {
      if (empty($by_type[$type])) {
        continue;
      }
      $storage = $this->etm->getStorage($type);
      foreach ($by_type[$type] as $uuid) {
        try {
          $entities = $storage->loadByProperties(['uuid' => $uuid]);
          if ($entity = reset($entities)) {
            $entity->delete();
            $summary['deleted'][$type] = ($summary['deleted'][$type] ?? 0) + 1;
            $summary['total_deleted']++;
          }
          else {
            $summary['not_found'][$type] = ($summary['not_found'][$type] ?? 0) + 1;
          }
        }
        catch (\Exception $e) {
          $summary['failed'][$type] = ($summary['failed'][$type] ?? 0) + 1;
          $this->loggerFactory->get('wdb_example')->warning('Failed to delete @type UUID @uuid: @msg', [
            '@type' => $type,
            '@uuid' => $uuid,
            '@msg' => $e->getMessage(),
          ]);
        }
      }
    }

    if ($dropTable) {
      try {
        $this->database->schema()->dropTable('wdb_example_map');
      }
      catch (\Exception $e) {
        // Ignore.
      }
    }

    return $summary;
  }

  /**
   * Log a concise purge summary.
   */
  public function logPurgeSummary(array $summary): void {
    $logger = $this->loggerFactory->get('wdb_example');
    $parts = [];
    if (!empty($summary['deleted'])) {
      $chunks = [];
      foreach ($summary['deleted'] as $type => $n) {
        $chunks[] = "$type=$n";
      }
      $parts[] = 'deleted: ' . implode(', ', $chunks);
    }
    if (!empty($summary['not_found'])) {
      $chunks = [];
      foreach ($summary['not_found'] as $type => $n) {
        $chunks[] = "$type=$n";
      }
      $parts[] = 'not_found: ' . implode(', ', $chunks);
    }
    if (!empty($summary['failed'])) {
      $chunks = [];
      foreach ($summary['failed'] as $type => $n) {
        $chunks[] = "$type=$n";
      }
      $parts[] = 'failed: ' . implode(', ', $chunks);
    }
    $logger->notice('Example purge summary (total_deleted=@total): @detail', [
      '@total' => $summary['total_deleted'] ?? 0,
      '@detail' => $parts ? implode(' | ', $parts) : 'no tracked entities',
    ]);
  }

  /**
   * Create or update a row in the tracking map (idempotent).
   */
  public function tagEntity(string $entity_type, string $uuid): void {
    if (!$this->database->schema()->tableExists('wdb_example_map')) {
      return;
    }
    $updated = $this->database->update('wdb_example_map')
      ->fields(['created' => $this->time->getRequestTime()])
      ->condition('entity_type', $entity_type)
      ->condition('entity_uuid', $uuid)
      ->execute();
    if (!$updated) {
      try {
        $this->database->insert('wdb_example_map')
          ->fields([
            'entity_type' => $entity_type,
            'entity_uuid' => $uuid,
            'created' => $this->time->getRequestTime(),
          ])
          ->execute();
      }
      catch (\Exception $e) {
        // Ignore race conditions.
      }
    }
  }

  /**
   * Spec for the map table.
   */
  private function getMapTableSpec(): array {
    return [
      'description' => 'Tracks entities created by the WDB Example module to enable selective purge.',
      'fields' => [
        'id' => [
          'type' => 'serial',
          'not null' => TRUE,
        ],
        'entity_type' => [
          'type' => 'varchar',
          'length' => 64,
          'not null' => TRUE,
        ],
        'entity_uuid' => [
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'created' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'wdb_example_entity' => ['entity_type', 'entity_uuid'],
      ],
      'indexes' => [
        'entity_type' => ['entity_type'],
      ],
    ];
  }

  /**
   * Deletion order (children first).
   */
  private function getDeletionOrder(): array {
    return [
      'wdb_word_map',
      'wdb_sign_interpretation',
      'wdb_label',
      'wdb_sign_function',
      'wdb_word_unit',
      'wdb_word_meaning',
      'wdb_word',
      'wdb_sign',
      'wdb_annotation_page',
      'wdb_source',
      'taxonomy_term',
    ];
  }

}
