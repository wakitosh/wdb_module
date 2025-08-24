<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Label entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Label entities at /admin/content/wdb_label.
 *
 * @see \Drupal\wdb_core\Entity\WdbLabel
 */
class WdbLabelListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return $this->buildConfigurableHeader([
      'id',
      'label_name',
      'annotation_page_ref',
      'label_center_x',
      'label_center_y',
      'annotation_uri',
    ]) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbLabel $entity */
    return $this->buildConfigurableRow($entity, [
      'id',
      'label_name',
      'annotation_page_ref',
      'label_center_x',
      'label_center_y',
      'annotation_uri',
    ]) + parent::buildRow($entity);
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
    return 'wdb_label';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // You can add custom operations for each entity here, such as a link
    // to view the label in the context of its page.
    return $operations;
  }

}
