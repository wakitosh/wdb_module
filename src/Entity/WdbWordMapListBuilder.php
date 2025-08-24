<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Word Map entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word Map entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbWordMap
 */
class WdbWordMapListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return $this->buildConfigurableHeader(['id', 'sign_interpretation_ref', 'word_unit_ref', 'sign_sequence']) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWordMap $entity */
    return $this->buildConfigurableRow($entity, ['id', 'sign_interpretation_ref', 'word_unit_ref', 'sign_sequence']) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type) {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    $instance->configFactory = $container->get('config.factory');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getListEntityTypeId(): string {
    return 'wdb_word_map';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // You can add custom operations for each entity here if needed.
    return $operations;
  }

}
