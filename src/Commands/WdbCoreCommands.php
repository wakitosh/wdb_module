<?php

namespace Drupal\wdb_core\Commands;

use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Drush commands for WDB Core.
 */
class WdbCoreCommands extends DrushCommands {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Cleans up invalid list-display fields from config for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. wdb_source).
   *
   * @return int
   *   Exit code.
   */
  #[CLI\Command(name: 'wdb:list-display:cleanup', aliases: ['wdb-ld-clean'])]
  public function cleanup(string $entity_type_id): int {
    $config_name = "wdb_core.list_display.$entity_type_id";
    $editable = $this->configFactory->getEditable($config_name);
    $fields = $editable->get('fields') ?? [];
    if (empty($fields)) {
      $this->logger()->notice("No fields saved in $config_name");
      return self::EXIT_SUCCESS;
    }
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_type_id);
    $valid = array_merge(['id', 'langcode'], array_keys($definitions));
    $clean = array_values(array_intersect($valid, $fields));
    if ($clean !== $fields) {
      $editable->set('fields', $clean)->save();
      $this->logger()->success('Cleaned: ' . implode(', ', array_diff($fields, $clean)));
    }
    else {
      $this->logger()->success('Nothing to clean');
    }
    return self::EXIT_SUCCESS;
  }

  /**
   * Resets list-display fields to current ListBuilder defaults for an entity.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g. wdb_source).
   *
   * @return int
   *   Exit code.
   */
  #[CLI\Command(name: 'wdb:list-display:reset', aliases: ['wdb-ld-reset'])]
  public function reset(string $entity_type_id): int {
    /** @var \Drupal\Core\Entity\EntityListBuilder $list_builder */
    $list_builder = $this->entityTypeManager->getListBuilder($entity_type_id);
    $header = array_keys($list_builder->buildHeader());
    // Filter out non-field ops that may be appended by parent headers.
    $defaults = array_filter($header, fn ($k) => $k !== 'operations');
    $this->configFactory->getEditable("wdb_core.list_display.$entity_type_id")
      ->set('fields', array_values($defaults))
      ->save();
    $this->logger()->success('Reset to defaults: ' . implode(', ', $defaults));
    return self::EXIT_SUCCESS;
  }

}
