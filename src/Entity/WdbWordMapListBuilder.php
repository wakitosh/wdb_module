<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Word Map entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Word Map entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbWordMap
 */
class WdbWordMapListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['sign_interpretation_ref'] = $this->t('Sign Interpretation');
    $header['word_unit_ref'] = $this->t('Word Unit');
    $header['sign_sequence'] = $this->t('Sign Sequence');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbWordMap $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();

    // Get the referenced WdbSignInterpretation entity.
    $sign_interpretation_entity = $entity->get('sign_interpretation_ref')->entity;
    if ($sign_interpretation_entity instanceof WdbSignInterpretation) {
      // Show the related sign_function_code instead of interpretation label.
      $sign_function_entity = $sign_interpretation_entity->get('sign_function_ref')->entity;
      if ($sign_function_entity instanceof WdbSignFunction) {
        $code = $sign_function_entity->get('sign_function_code')->value;
        if ($code === '' || $code === NULL) {
          // Reconstruct code if blank (sign_code + underscore).
          /** @var \Drupal\wdb_core\Entity\WdbSign $sign_entity_for_fn */
          $sign_entity_for_fn = $sign_function_entity->get('sign_ref')->entity;
          if ($sign_entity_for_fn instanceof WdbSign && $sign_entity_for_fn->hasField('sign_code')) {
            $row['sign_interpretation_ref'] = ($sign_entity_for_fn->get('sign_code')->value ?: '?') . '_';
          }
          else {
            $row['sign_interpretation_ref'] = $this->t('(Unnamed Function)');
          }
        }
        else {
          $row['sign_interpretation_ref'] = $code;
        }
      }
      else {
        $row['sign_interpretation_ref'] = $this->t('(No Function)');
      }
    }
    else {
      // Handle cases where the referenced entity is not found.
      $target_id = $entity->get('sign_interpretation_ref')->target_id;
      $row['sign_interpretation_ref'] = $this->t('Error: SignInterpretation (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    // Get the referenced WdbWordUnit entity.
    $word_unit_entity = $entity->get('word_unit_ref')->entity;
    if ($word_unit_entity instanceof WdbWordUnit) {
      // Display the label of the referenced entity.
      $row['word_unit_ref'] = $word_unit_entity->label();
    }
    else {
      // Handle cases where the referenced entity is not found.
      $target_id = $entity->get('word_unit_ref')->target_id;
      $row['word_unit_ref'] = $this->t('Error: WordUnit (ID: @id) not found.', ['@id' => $target_id ?? 'N/A']);
    }

    $row['sign_sequence'] = $entity->get('sign_sequence')->value;

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
