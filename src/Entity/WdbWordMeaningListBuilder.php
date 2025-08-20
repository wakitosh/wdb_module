<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Word Meaning entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word Meaning entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbWordMeaning
 */
class WdbWordMeaningListBuilder extends EntityListBuilder {

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
    $header['word_meaning_code'] = $this->t('Word Meaning Code');
    $header['word_ref'] = $this->t('Word');
    $header['meaning_identifier'] = $this->t('Meaning Identifier');
    $header['explanation'] = $this->t('Explanation');
    $header['langcode'] = $this->t('Language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWordMeaning $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['word_meaning_code'] = $entity->get('word_meaning_code')->value;

    // Get the referenced WdbWord entity from the 'word_ref' field.
    $word_entity = $entity->get('word_ref')->entity;

    if ($word_entity instanceof WdbWord) {
      $basic = $word_entity->label();
      $lex_label = '';
      if ($word_entity->hasField('lexical_category_ref') && !$word_entity->get('lexical_category_ref')->isEmpty()) {
        $lex_entity = $word_entity->get('lexical_category_ref')->entity;
        if ($lex_entity) {
          $translated = $this->entityRepository->getTranslationFromContext($lex_entity);
          $lex_label = $translated->label();
        }
      }
      $lang = $word_entity->language()->getId();
      if ($lex_label) {
        $row['word_ref'] = $basic . ' (' . $lex_label . ' / ' . $lang . ')';
      }
      else {
        $row['word_ref'] = $basic . ' (' . $lang . ')';
      }
    }
    else {
      $target_id = $entity->get('word_ref')->target_id;
      $row['word_ref'] = $this->t('Word @id (missing)', ['@id' => $target_id ?? 'N/A']);
    }

    $row['meaning_identifier'] = $entity->get('meaning_identifier')->value;

    // Explicitly get the 'value' of the text_long field to avoid
    // rendering issues.
    $explanation_field = $entity->get('explanation');
    $row['explanation'] = $explanation_field ? $explanation_field->value : '';

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
