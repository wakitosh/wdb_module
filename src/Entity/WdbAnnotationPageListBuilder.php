<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Annotation Page entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Annotation Page entities at /admin/content/wdb_annotation_page.
 *
 * @see \Drupal\wdb_core\Entity\WdbAnnotationPage
 */
class WdbAnnotationPageListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['annotation_code'] = $this->t('Annotation Code');
    $header['source_ref'] = $this->t('Source Document');
    $header['page_number'] = $this->t('Page Number');
    $header['page_name'] = $this->t('Page Name');
    $header['page_name_computed'] = $this->t('Computed Page Label');
    $header['image_identifier'] = $this->t('IIIF Image Identifier');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbAnnotationPage $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['annotation_code'] = $entity->get('annotation_code')->value;

    // Get the referenced WdbSource entity from the 'source_ref' field.
    $source_entity = $entity->get('source_ref')->entity;

    if ($source_entity instanceof WdbSource) {
      // Display the label of the referenced entity.
      $row['source_ref'] = $source_entity->label();
    }
    else {
      // Handle cases where the referenced entity is not found
      // or is of an unexpected type.
      $target_id = $entity->get('source_ref')->target_id;
      $row['source_ref'] = $this->t('Error: Source (ID: @id) not found or invalid.', ['@id' => $target_id ?? 'N/A']);
    }

    $row['page_number'] = $entity->get('page_number')->value;
    $row['page_name'] = $entity->get('page_name')->value;
    $row['page_name_computed'] = $entity->get('page_name_computed')->value;
    $row['image_identifier'] = $entity->get('image_identifier')->value;

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // You can add custom operations here if needed. For example, a link
    // to the gallery view page for this specific annotation page.
    return $operations;
  }

}
