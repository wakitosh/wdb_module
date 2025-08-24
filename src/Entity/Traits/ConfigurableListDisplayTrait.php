<?php

namespace Drupal\wdb_core\Entity\Traits;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 * Provides configurable list display (columns) for EntityListBuilder.
 */
trait ConfigurableListDisplayTrait {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Return the entity type ID used for list display.
   */
  abstract protected function getListEntityTypeId(): string;

  /**
   * Get selected fields for the entity's list display.
   */
  protected function getSelectedFields(array $defaults = ['id']): array {
    $entity_type_id = $this->getListEntityTypeId();
    $config = $this->configFactory->get('wdb_core.list_display.' . $entity_type_id);
    $fields = $config ? $config->get('fields') : NULL;
    // Build an allow-list of valid field names: pseudo columns + real fields.
    $allowed = ['id', 'langcode'];
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_type_id);
    $allowed = array_merge($allowed, array_keys($definitions));
    if (is_array($fields) && !empty($fields)) {
      // Keep order as saved but drop any entries that are no longer valid.
      $filtered = [];
      foreach ($fields as $name) {
        if ($name && in_array($name, $allowed, TRUE)) {
          $filtered[] = $name;
        }
      }
      if (!empty($filtered)) {
        return array_values(array_unique($filtered));
      }
    }
    // If nothing valid from config, use provided defaults (already valid).
    return $defaults;
  }

  /**
   * Render a field value generically.
   */
  protected function renderFieldValue(EntityInterface $entity, string $field_name): string {
    if (!$entity instanceof ContentEntityInterface) {
      return '';
    }
    if ($field_name === 'id') {
      return (string) $entity->id();
    }
    if ($field_name === 'langcode') {
      return $entity->language()->getName();
    }
    if (!$entity->hasField($field_name)) {
      return '';
    }
    $item_list = $entity->get($field_name);
    if ($item_list->isEmpty()) {
      return '';
    }
    $definition = $item_list->getFieldDefinition();
    $type = $definition->getType();

    if ($type === 'entity_reference' && $item_list instanceof EntityReferenceFieldItemList) {
      $labels = [];
      foreach ($item_list->referencedEntities() as $ref) {
        $labels[] = $ref->label();
      }
      return implode(', ', $labels);
    }
    if ($type === 'boolean') {
      return $item_list->value ? (string) t('Yes') : (string) t('No');
    }
    $value = (string) $item_list->value;
    if (in_array($type, ['text_long', 'string_long'], TRUE)) {
      $value = strip_tags($value);
      if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 117) . '…';
      }
    }
    return $value;
  }

  /**
   * Build configurable headers.
   */
  protected function buildConfigurableHeader(array $defaults = ['id']): array {
    $headers = [];
    $entity_type_id = $this->getListEntityTypeId();
    $selected = $this->getSelectedFields($defaults);
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_type_id);
    foreach ($selected as $field_name) {
      if ($field_name === 'id') {
        $headers[$field_name] = t('ID');
        continue;
      }
      if ($field_name === 'langcode') {
        $headers[$field_name] = t('Language');
        continue;
      }
      // Only include headers for existing fields. If the field was deleted,
      // skip it entirely so we don't show leftover machine names.
      if (isset($definitions[$field_name])) {
        $headers[$field_name] = $definitions[$field_name]->getLabel();
      }
    }
    return $headers;
  }

  /**
   * Build configurable row.
   */
  protected function buildConfigurableRow(EntityInterface $entity, array $defaults = ['id']): array {
    $row = [];
    foreach ($this->getSelectedFields($defaults) as $field_name) {
      $row[$field_name] = $this->renderFieldValue($entity, $field_name);
    }
    return $row;
  }

}
