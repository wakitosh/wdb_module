<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the WDB Label entity.
 *
 * This entity represents a single polygon annotation on an image, typically
 * corresponding to a character or a ligature. It stores the geometric data
 * (polygon points) and a textual label.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_label',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Label'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbLabelListBuilder',
    'form' => [
      'default' => 'Drupal\\Core\\Entity\\ContentEntityForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
    'translation' => 'Drupal\\content_translation\\ContentTranslationHandler',
  ],
  base_table: 'wdb_label',
  data_table: 'wdb_label_field_data',
  translatable: FALSE,
  admin_permission: 'administer wdb_label entities',
  entity_keys: [
    'id' => 'id',
    'label' => 'label_name',
    'uuid' => 'uuid',
  ],
  links: [
    'canonical' => '/wdb/label/{wdb_label}',
    'collection' => '/admin/content/wdb_label',
  ],
  field_ui_base_route: 'entity.wdb_label.collection',
)]
class WdbLabel extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')->setLabel(t('ID'))->setReadOnly(TRUE)->setSetting('unsigned', TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')->setLabel(t('UUID'))->setReadOnly(TRUE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    // The textual content of the label, used as the entity label.
    $fields['label_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label Name'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -5])
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Reference to the WDB Annotation Page this label is on.
    $fields['annotation_page_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Annotation Page'))
      ->setSetting('target_type', 'wdb_annotation_page')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => -4])
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Stores the polygon coordinates as a multi-value list of "X,Y" strings.
    $fields['polygon_points'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Polygon Points'))
      ->setDescription(t('Coordinates for the polygon, stored as "X,Y" strings.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('max_length', 50)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -2])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -2])
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // The center coordinates of the label's bounding box.
    $fields['label_center_x'] = BaseFieldDefinition::create('integer')->setLabel(t('Label Center X'));
    $fields['label_center_y'] = BaseFieldDefinition::create('integer')->setLabel(t('Label Center Y'));

    // The unique URI for this annotation, typically corresponding to the @id
    // from an annotation server like Annotorious.
    $fields['annotation_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Annotation URI'))
      ->setDescription(t('The unique URI for this annotation, typically corresponding to the @id from an annotation server.'))
      ->setSetting('max_length', 255)
      ->addConstraint('UniqueField')
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
