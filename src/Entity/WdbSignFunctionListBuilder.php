<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Sign Function entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Sign Function entities at /admin/content/wdb_sign_function.
 *
 * @see \Drupal\wdb_core\Entity\WdbSignFunction
 */
class WdbSignFunctionListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return $this->buildConfigurableHeader([
      'id',
      'sign_function_code',
      'sign_ref',
      'function_name',
      'description',
      'langcode',
    ]) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSignFunction $entity */
    return $this->buildConfigurableRow($entity, [
      'id',
      'sign_function_code',
      'sign_ref',
      'function_name',
      'description',
      'langcode',
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
    return 'wdb_sign_function';
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
