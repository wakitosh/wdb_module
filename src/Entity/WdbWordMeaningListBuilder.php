<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

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
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['word_meaning_code'] = $this->t('Word Meaning Code');
    $header['word_ref'] = $this->t('Word (Referenced)');
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
      // Display the label of the referenced entity.
      $row['word_ref'] = $word_entity->label();
    }
    else {
      // Handle cases where the referenced entity is not found or is of an unexpected type.
      $target_id = $entity->get('word_ref')->target_id;
      $row['word_ref'] = $this->t('Error: Word (ID: @id) not found or invalid.', ['@id' => $target_id ?? 'N/A']);
    }

    $row['meaning_identifier'] = $entity->get('meaning_identifier')->value;

    // Explicitly get the 'value' of the text_long field to avoid rendering issues.
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
