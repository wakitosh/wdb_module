<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Word entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word entities at /admin/content/wdb_word.
 *
 * @see \Drupal\wdb_core\Entity\WdbWord
 */
class WdbWordListBuilder extends EntityListBuilder {

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
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['word_code'] = $this->t('Word Code');
    $header['basic_form'] = $this->t('Basic Form');
    $header['lexical_category_ref'] = $this->t('Lexical Category');
    $header['langcode'] = $this->t('Language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWord $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['word_code'] = $entity->get('word_code')->value;
    $row['basic_form'] = $entity->get('basic_form')->value;

    // Retrieves and formats the names of the referenced lexical category terms.
    $lexical_category_terms = [];
    if ($entity->hasField('lexical_category_ref') && !$entity->get('lexical_category_ref')->isEmpty()) {
      $term = $entity->get('lexical_category_ref')->entity;
      if ($term) {
        $translated = $this->entityRepository->getTranslationFromContext($term);
        $lexical_category_terms[] = $translated->label();
      }
    }
    $row['lexical_category_ref'] = !empty($lexical_category_terms) ? implode(', ', $lexical_category_terms) : $this->t('- None -');

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
