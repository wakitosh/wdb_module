<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of WDB POS Mapping entities.
 *
 * This list builder allows mappings to be reordered via drag-and-drop.
 */
class WdbPosMappingListBuilder extends DraggableListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Constructs a new WdbPosMappingListBuilder object.
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
  public function getFormId() {
    return 'wdb_core_pos_mapping_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['source_pos_string'] = $this->t('Source POS String');
    $header['target_lexical_category'] = $this->t('Target Lexical Category');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbPosMapping $entity */
    $row['label'] = $entity->label();
    $row['source_pos_string'] = [
      '#plain_text' => $entity->source_pos_string,
    ];

    // Load and display the name of the target taxonomy term.
    $target_term_id = $entity->target_lexical_category;
    if ($target_term_id) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($target_term_id);
      $term_name = $term ? $term->getName() : $this->t('Term not found');
    }
    else {
      $term_name = $this->t('Not set');
    }

    $row['target_lexical_category'] = [
      '#plain_text' => $term_name,
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Ensure that the operation links point to the correct form routes.
    if (isset($operations['edit'])) {
      $operations['edit']['url'] = $entity->toUrl('edit-form');
    }
    if (isset($operations['delete'])) {
      $operations['delete']['url'] = $entity->toUrl('delete-form');
    }
    return $operations;
  }

}
