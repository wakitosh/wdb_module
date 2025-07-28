<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Sign entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Sign entities at /admin/content/wdb_sign.
 *
 * @see \Drupal\wdb_core\Entity\WdbSign
 */
class WdbSignListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['sign_code'] = $this->t('Sign Code');
    $header['langcode'] = $this->t('Language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSign $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['sign_code'] = $entity->get('sign_code')->value;
    $row['langcode'] = $entity->language()->getName();
    return $row + parent::buildRow($entity);
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
