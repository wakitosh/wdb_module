<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of WDB Label entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Label entities at /admin/content/wdb_label.
 *
 * @see \Drupal\wdb_core\Entity\WdbLabel
 */
class WdbLabelListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Defines the table header for the entity list.
    $header['id'] = $this->t('ID');
    $header['label_name'] = $this->t('Label Name');
    $header['annotation_page_ref'] = $this->t('Annotation Page');
    $header['label_center_x'] = $this->t('Center X');
    $header['label_center_y'] = $this->t('Center Y');
    $header['annotation_uri'] = $this->t('Annotation URI');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbLabel $entity */
    // Defines the data for each row of the table.
    $row['id'] = $entity->id();
    $row['label_name'] = $entity->get('label_name')->value;

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

    $row['label_center_x'] = $entity->get('label_center_x')->value;
    $row['label_center_y'] = $entity->get('label_center_y')->value;
    $row['annotation_uri'] = $entity->get('annotation_uri')->value;

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // You can add custom operations for each entity here, such as a link
    // to view the label in the context of its page.
    return $operations;
  }

}
