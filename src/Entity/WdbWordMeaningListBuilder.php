<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;

/**
 * Defines a class to build a listing of WDB Word Meaning entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word Meaning entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbWordMeaning
 */
class WdbWordMeaningListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * Entity repository for contextual translations.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type) {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->configFactory = $container->get('config.factory');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return $this->buildConfigurableHeader([
      'id',
      'word_meaning_code',
      'word_ref',
      'meaning_identifier',
      'explanation',
      'langcode',
    ]) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWordMeaning $entity */
    return $this->buildConfigurableRow($entity, [
      'id',
      'word_meaning_code',
      'word_ref',
      'meaning_identifier',
      'explanation',
      'langcode',
    ]) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getListEntityTypeId(): string {
    return 'wdb_word_meaning';
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
