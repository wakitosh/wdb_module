<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Source entities.
 *
 * @category Drupal
 * @package WdbCore
 * @license GPL-2.0-or-later
 * @link https://www.drupal.org/project/drupal
 *
 * @see \Drupal\wdb_core\Entity\WdbSource
 */
class WdbSourceListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructs a new WdbSourceListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return static
   *   The list builder instance.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
          $entity_type,
          $container->get('entity_type.manager')->getStorage($entity_type->id()),
          $container->get('entity_type.manager'),
          $container->get('config.factory'),
          $container->get('entity_field.manager')
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function getListEntityTypeId(): string {
    return 'wdb_source';
  }

  /**
   * Renders a field value for listing purposes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being rendered.
   * @param string $field_name
   *   The field machine name.
   *
   * @return string
   *   The rendered value.
   */
  // Field rendering is provided by ConfigurableListDisplayTrait.
  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The header definitions.
   */
  public function buildHeader() {
    // Default columns for Source entity.
    $defaults = ['id', 'source_identifier', 'displayname', 'pages', 'title_statement', 'description', 'subsystem_tags'];
    return $this->buildConfigurableHeader($defaults) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The row render array.
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSource $entity */
    $defaults = ['id', 'source_identifier', 'displayname', 'pages', 'title_statement', 'description', 'subsystem_tags'];
    return $this->buildConfigurableRow($entity, $defaults) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The operations array.
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSource $entity */
    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('view')) {
      // Since subsystem_tags is a multi-value field,
      // safely retrieve the first reference entity.
      $first_subsystem = NULL;
      $subsys_field = $entity->get('subsystem_tags');
      if ($subsys_field instanceof EntityReferenceFieldItemList && !$subsys_field->isEmpty()) {
        $subs = $subsys_field->referencedEntities();
        if (!empty($subs)) {
          $first_subsystem = reset($subs);
        }
      }
      if ($first_subsystem) {
        // Find the minimum page number associated with this source.
        $page_storage = $this->entityTypeManager->getStorage('wdb_annotation_page');
        $query = $page_storage->getQuery()
          ->condition('source_ref', $entity->id())
          ->sort('page_number', 'ASC')
          ->range(0, 1)
          ->accessCheck(FALSE);

        $page_ids = $query->execute();
        // Default fallback.
        $first_page_number = 1;

        if (!empty($page_ids)) {
          $first_page_entity = $page_storage->load(reset($page_ids));
          if ($first_page_entity instanceof WdbAnnotationPage) {
            $first_page_number = $first_page_entity->get('page_number')->value;
          }
        }
        $operations['view_in_gallery'] = [
          'title' => $this->t('View in gallery'),
          'weight' => -10,
          'url' => Url::fromRoute(
              'wdb_core.gallery_page', [
                'subsysname' => strtolower($first_subsystem->getName()),
                'source' => $entity->get('source_identifier')->value,
                'page' => $first_page_number,
              ]
          ),
        ];
      }
    }

    return $operations;
  }

}
