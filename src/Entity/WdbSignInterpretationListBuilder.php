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
    $row['id'] = $entity->id();

    // Annotation page.
    $annotation_page_entity = $entity->get('annotation_page_ref')->entity;
    if ($annotation_page_entity instanceof WdbAnnotationPage) {
      $row['annotation_page_ref'] = $annotation_page_entity->label();
    }
    else {
      $target_id = $entity->get('annotation_page_ref')->target_id;
      $row['annotation_page_ref'] = $this->t('Error: Page (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    // Label reference.
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
      $row['label_ref'] = $this->t('(No Associated Label)');
    }

    // Sign function reference with fallbacks.
    if ($entity->get('sign_function_ref')->isEmpty()) {
      $row['sign_function_ref'] = $this->t('(None)');
    }
    else {
      $sign_function_entity = $entity->get('sign_function_ref')->entity;
      if ($sign_function_entity instanceof WdbSignFunction) {
        $code = $sign_function_entity->get('sign_function_code')->value;
        if ($code === '' || $code === NULL) {
          /** @var \Drupal\wdb_core\Entity\WdbSign $sign_entity_for_fn */
          $sign_entity_for_fn = $sign_function_entity->get('sign_ref')->entity;
          if ($sign_entity_for_fn instanceof WdbSign && $sign_entity_for_fn->hasField('sign_code')) {
            $reconstructed = $sign_entity_for_fn->get('sign_code')->value . '_';
            $row['sign_function_ref'] = $reconstructed ?: $this->t('(Unnamed Function)');
          }
          else {
            $row['sign_function_ref'] = $this->t('(Unnamed Function)');
          }
        }
        else {
          $row['sign_function_ref'] = $code;
        }
      }
      else {
        $target_id = $entity->get('sign_function_ref')->target_id;
        $row['sign_function_ref'] = $this->t('Error: Function (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
      }
    }

    $row['line_number'] = $entity->get('line_number')->value;
    $row['phone'] = $entity->get('phone')->value;
    $row['priority'] = $entity->get('priority')->value;
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
