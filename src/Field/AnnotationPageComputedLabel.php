<?php

namespace Drupal\wdb_core\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes the value of the 'page_name_computed' field.
 *
 * This class generates a human-readable label for a WdbAnnotationPage entity,
 * combining information from the parent source, the page number, and the
 * page name.
 */
class AnnotationPageComputedLabel extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    /** @var \Drupal\wdb_core\Entity\WdbAnnotationPage $entity */
    $entity = $this->getEntity();
    $source_entity = $entity->get('source_ref')->entity;
    $page_number = $entity->get('page_number')->value;
    $annotation_code = $entity->get('annotation_code')->value;

    // Default to the annotation_code or entity ID.
    $label = $annotation_code ?? (string) $entity->id();

    if ($source_entity && $page_number) {
      $label = $source_entity->label() . ' - Page ' . $page_number;
      // If a meaningful page name exists (and is not just the page number),
      // append it in parentheses.
      if ($entity->get('page_name')->value && $entity->get('page_name')->value !== (string) $page_number) {
        $label .= ' (' . $entity->get('page_name')->value . ')';
      }
    }
    elseif ($entity->get('page_name')->value) {
      $label = $entity->get('page_name')->value;
      if ($annotation_code) {
        $label .= ' (' . $annotation_code . ')';
      }
    }
    $this->list[0] = $this->createItem(0, $label);
  }

}
