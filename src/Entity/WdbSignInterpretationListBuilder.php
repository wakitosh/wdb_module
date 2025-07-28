<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Sign Interpretation entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Sign Interpretation entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbSignInterpretation
 */
class WdbSignInterpretationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['sign_interpretation_code'] = $this->t('Sign Interpretation Code');
    $header['annotation_page_ref'] = $this->t('Annotation Page');
    $header['label_ref'] = $this->t('Label');
    $header['sign_function_ref'] = $this->t('Sign Function');
    $header['line_number'] = $this->t('Line');
    $header['phone'] = $this->t('Phonetic');
    $header['priority'] = $this->t('Priority');
    $header['note'] = $this->t('Note');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSignInterpretation $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['sign_interpretation_code'] = $entity->get('sign_interpretation_code')->value;

    // Get the referenced WdbAnnotationPage entity from the 'annotation_page_ref' field.
    $annotation_page_entity = $entity->get('annotation_page_ref')->entity;
    if ($annotation_page_entity instanceof WdbAnnotationPage) {
      // Display the label of the referenced entity.
      $row['annotation_page_ref'] = $annotation_page_entity->label();
    }
    else {
      // Handle cases where the referenced entity is not found or is of an unexpected type.
      $target_id = $entity->get('annotation_page_ref')->target_id;
      $row['annotation_page_ref'] = $this->t('Error: Page (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    // Get the referenced WdbLabel entity from the 'label_ref' field.
    if (!$entity->get('label_ref')->isEmpty()) {
      /** @var \Drupal\wdb_core\Entity\WdbLabel $label_entity */
      $label_entity = $entity->get('label_ref')->entity;
      if ($label_entity instanceof WdbLabel) {
        $row['label_ref'] = $label_entity->label();
      }
      else {
        $target_id = $entity->get('label_ref')->target_id;
        $row['label_ref'] = $this->t('Invalid ref: @id', ['@id' => $target_id ?? 'N/A']);
      }
    }
    else {
      // Handle case where label_ref is empty (NULL).
      $row['label_ref'] = $this->t('(No Associated Label)');
    }

    // Get the referenced WdbSignFunction entity from the 'sign_function_ref' field.
    $sign_function_entity = $entity->get('sign_function_ref')->entity;
    if ($sign_function_entity instanceof WdbSignFunction) {
      // Display the label of the referenced entity.
      $row['sign_function_ref'] = $sign_function_entity->label();
    }
    else {
      // Handle cases where the referenced entity is not found or is of an unexpected type.
      $target_id = $entity->get('sign_function_ref')->target_id;
      $row['sign_function_ref'] = $this->t('Error: Function (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    $row['line_number'] = $entity->get('line_number')->value;
    $row['phone'] = $entity->get('phone')->value;
    $row['priority'] = $entity->get('priority')->value;

    // Explicitly get the 'value' of the text_long field to avoid rendering issues.
    $note_field = $entity->get('note');
    $row['note'] = $note_field ? $note_field->value : '';

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
