<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Word Unit entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word Unit entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbWordUnit
 */
class WdbWordUnitListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['original_word_unit_identifier'] = $this->t('Original ID');
    $header['source_ref'] = $this->t('Source');
    $header['realized_form'] = $this->t('Realized Form');
    $header['word_sequence'] = $this->t('Sequence');
    $header['annotation_page_refs'] = $this->t('Page Occurrences');
    $header['word_meaning_ref'] = $this->t('Word Meaning');
    $header['person_ref'] = $this->t('Person');
    $header['gender_ref'] = $this->t('Gender');
    $header['number_ref'] = $this->t('Number');
    $header['verbal_form_ref'] = $this->t('Verbal Form');
    $header['aspect_ref'] = $this->t('Aspect');
    $header['mood_ref'] = $this->t('Mood');
    $header['voice_ref'] = $this->t('Voice');
    $header['grammatical_case_ref'] = $this->t('Grammatical Case');
    $header['note'] = $this->t('Note');
    $header['langcode'] = $this->t('Language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWordUnit $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['original_word_unit_identifier'] = $entity->get('original_word_unit_identifier')->value;

    // Get the referenced WdbSource entity.
    $source_entity = $entity->get('source_ref')->entity;
    if ($source_entity instanceof WdbSource) {
      $row['source_ref'] = $source_entity->label();
    }
    else {
      $target_id = $entity->get('source_ref')->target_id;
      $row['source_ref'] = $this->t('Error: Source (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    $row['realized_form'] = $entity->get('realized_form')->value;
    $row['word_sequence'] = $entity->get('word_sequence')->value;

    // Get the labels of all referenced annotation pages.
    $page_entities = $entity->get('annotation_page_refs')->referencedEntities();
    $page_labels = [];
    foreach ($page_entities as $page_entity) {
      /** @var \Drupal\wdb_core\Entity\WdbAnnotationPage $page_entity */
      $page_labels[] = $page_entity->label();
    }
    $row['annotation_page_refs'] = !empty($page_labels) ? implode(', ', $page_labels) : $this->t('None');

    // Get the referenced WdbWordMeaning entity.
    $word_meaning_entity = $entity->get('word_meaning_ref')->entity;
    if ($word_meaning_entity instanceof WdbWordMeaning) {
      $row['word_meaning_ref'] = $word_meaning_entity->label();
    }
    else {
      $target_id = $entity->get('word_meaning_ref')->target_id;
      $row['word_meaning_ref'] = $this->t('Error: Meaning (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    // The following blocks retrieve and display names from various taxonomy term references.
    $person_terms = [];
    foreach ($entity->get('person_ref')->referencedEntities() as $term) {
      $person_terms[] = $term->getName();
    }
    $row['person_ref'] = !empty($person_terms) ? implode(', ', $person_terms) : $this->t('- None -');

    $gender_terms = [];
    foreach ($entity->get('gender_ref')->referencedEntities() as $term) {
      $gender_terms[] = $term->getName();
    }
    $row['gender_ref'] = !empty($gender_terms) ? implode(', ', $gender_terms) : $this->t('- None -');

    $number_terms = [];
    foreach ($entity->get('number_ref')->referencedEntities() as $term) {
      $number_terms[] = $term->getName();
    }
    $row['number_ref'] = !empty($number_terms) ? implode(', ', $number_terms) : $this->t('- None -');

    $verbal_form_terms = [];
    foreach ($entity->get('verbal_form_ref')->referencedEntities() as $term) {
      $verbal_form_terms[] = $term->getName();
    }
    $row['verbal_form_ref'] = !empty($verbal_form_terms) ? implode(', ', $verbal_form_terms) : $this->t('- None -');

    $aspect_terms = [];
    foreach ($entity->get('aspect_ref')->referencedEntities() as $term) {
      $aspect_terms[] = $term->getName();
    }
    $row['aspect_ref'] = !empty($aspect_terms) ? implode(', ', $aspect_terms) : $this->t('- None -');

    $mood_terms = [];
    foreach ($entity->get('mood_ref')->referencedEntities() as $term) {
      $mood_terms[] = $term->getName();
    }
    $row['mood_ref'] = !empty($mood_terms) ? implode(', ', $mood_terms) : $this->t('- None -');

    $voice_terms = [];
    foreach ($entity->get('voice_ref')->referencedEntities() as $term) {
      $voice_terms[] = $term->getName();
    }
    $row['voice_ref'] = !empty($voice_terms) ? implode(', ', $voice_terms) : $this->t('- None -');

    $grammatical_case_terms = [];
    foreach ($entity->get('grammatical_case_ref')->referencedEntities() as $term) {
      $grammatical_case_terms[] = $term->getName();
    }
    $row['grammatical_case_ref'] = !empty($grammatical_case_terms) ? implode(', ', $grammatical_case_terms) : $this->t('- None -');

    // Explicitly get the 'value' of the text_long field.
    $note_field = $entity->get('note');
    $row['note'] = $note_field ? $note_field->value : '';

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
