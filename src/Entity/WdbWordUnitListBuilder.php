<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Word Unit entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word Unit entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbWordUnit
 */
class WdbWordUnitListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return $this->buildConfigurableHeader([
      'id',
      'annotation_page_refs',
      'realized_form',
      'word_sequence',
      'word_meaning_ref',
      'person_ref',
      'gender_ref',
      'number_ref',
      'verbal_form_ref',
      'aspect_ref',
      'mood_ref',
      'voice_ref',
      'grammatical_case_ref',
      'note',
      'langcode',
    ]) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWordUnit $entity */
    return $this->buildConfigurableRow($entity, [
      'id',
      'annotation_page_refs',
      'realized_form',
      'word_sequence',
      'word_meaning_ref',
      'person_ref',
      'gender_ref',
      'number_ref',
      'verbal_form_ref',
      'aspect_ref',
      'mood_ref',
      'voice_ref',
      'grammatical_case_ref',
      'note',
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
    return 'wdb_word_unit';
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
