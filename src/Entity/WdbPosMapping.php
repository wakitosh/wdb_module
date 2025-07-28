<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the WDB POS Mapping entity.
 *
 * This configuration entity stores mappings from a source Part-of-Speech (POS)
 * string (e.g., from an import file) to a target lexical category taxonomy
 * term within the system.
 *
 * @ConfigEntityType(
 *   id = "wdb_pos_mapping",
 *   label = @Translation("WDB POS Mapping"),
 *   handlers = {
 *     "list_builder" = "Drupal\wdb_core\Entity\WdbPosMappingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wdb_core\Form\WdbPosMappingForm",
 *       "edit" = "Drupal\wdb_core\Form\WdbPosMappingForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wdb_pos_mapping",
 *   admin_permission = "administer wdb pos mappings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/wdb_pos_mapping/{wdb_pos_mapping}",
 *     "add-form" = "/admin/structure/wdb_pos_mapping/add",
 *     "edit-form" = "/admin/structure/wdb_pos_mapping/{wdb_pos_mapping}/edit",
 *     "delete-form" = "/admin/structure/wdb_pos_mapping/{wdb_pos_mapping}/delete",
 *     "collection" = "/admin/structure/wdb_pos_mapping"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "source_pos_string",
 *     "target_lexical_category",
 *     "weight"
 *   }
 * )
 */
class WdbPosMapping extends ConfigEntityBase {

  /**
   * The mapping ID.
   *
   * @var string
   */
  public $id;

  /**
   * The mapping label.
   *
   * @var string
   */
  public $label;

  /**
   * The source Part-of-Speech string to match (e.g., "verb", "V").
   *
   * @var string
   */
  public $source_pos_string;

  /**
   * The target lexical category taxonomy term ID.
   *
   * @var string
   */
  public $target_lexical_category;

  /**
   * The weight of this mapping, used for ordering.
   *
   * @var int
   */
  public $weight = 0;

}
