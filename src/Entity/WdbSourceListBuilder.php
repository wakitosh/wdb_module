<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Source entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbSource
 */
class WdbSourceListBuilder extends EntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WdbSourceListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['source_identifier'] = $this->t('Source Identifier');
    $header['displayname'] = $this->t('Display Name');
    $header['pages'] = $this->t('Pages');
    $header['title_statement'] = $this->t('Title Statement');
    $header['description'] = $this->t('Description');
    $header['subsystem_tags'] = $this->t('Subsystem');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSource $entity */
    $row['id'] = $entity->id();
    $row['source_identifier'] = $entity->get('source_identifier')->value;
    $row['displayname'] = $entity->label();
    $row['pages'] = $entity->get('pages')->value;
    $row['title_statement'] = $entity->get('title_statement')->value;
    $row['description'] = $entity->get('description')->value;
    $subsystem_terms = [];
    foreach ($entity->get('subsystem_tags')->referencedEntities() as $term) {
      $subsystem_terms[] = $term->getName();
    }
    $row['subsystem_tags'] = !empty($subsystem_terms) ? implode(', ', $subsystem_terms) : $this->t('- None -');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    /** @var \Drupal\wdb_core\Entity\WdbSource $entity */
    if ($entity->access('view')) {
      $first_subsystem = $entity->get('subsystem_tags')->entity;
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
          if ($first_page_entity) {
            $first_page_number = $first_page_entity->get('page_number')->value;
          }
        }

        $operations['view_in_gallery'] = [
          'title' => $this->t('View in gallery'),
          'weight' => -10,
          'url' => Url::fromRoute('wdb_core.gallery_page', [
            'subsysname' => strtolower($first_subsystem->getName()),
            'source' => $entity->get('source_identifier')->value,
            'page' => $first_page_number,
          ]),
        ];
      }
    }

    return $operations;
  }

}
