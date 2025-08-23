<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Sign Function entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Sign Function entities at /admin/content/wdb_sign_function.
 *
 * @see \Drupal\wdb_core\Entity\WdbSignFunction
 */
class WdbSignFunctionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['sign_function_code'] = $this->t('Sign Function Code');
    $header['sign_ref'] = $this->t('Sign (Referenced)');
    $header['function_name'] = $this->t('Function Name');
    $header['description'] = $this->t('Description');
    $header['langcode'] = $this->t('Language');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSignFunction $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['sign_function_code'] = $entity->get('sign_function_code')->value;

    // Get the referenced WdbSign entity from the 'sign_ref' field.
    $sign_entity = $entity->get('sign_ref')->entity;

    if ($sign_entity instanceof WdbSign) {
      // Display the label of the referenced entity.
      $row['sign_ref'] = $sign_entity->label();
    }
    else {
      // Handle cases where the referenced entity is not found
      // or is of an unexpected type.
      $target_id = $entity->get('sign_ref')->target_id;
      $row['sign_ref'] = $this->t('Error: Sign (ID: @id) not found or invalid.', ['@id' => $target_id ?? 'N/A']);
    }

    $row['function_name'] = $entity->get('function_name')->value;

    // Explicitly get the 'value' of the text_long field
    // to avoid rendering issues.
    $description_field = $entity->get('description');
    $row['description'] = $description_field ? $description_field->value : '';

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
