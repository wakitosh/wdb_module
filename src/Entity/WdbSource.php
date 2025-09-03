<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the WDB Source entity.
 *
 * This entity represents a source document, such as a manuscript or a book,
 * which serves as a container for annotation pages.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_source',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Source'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbSourceListBuilder',
    'form' => [
      'default' => 'Drupal\\wdb_core\\Form\\WdbSourceForm',
      'add' => 'Drupal\\wdb_core\\Form\\WdbSourceForm',
      'edit' => 'Drupal\\wdb_core\\Form\\WdbSourceForm',
      'delete' => 'Drupal\\wdb_core\\Form\\WdbProtectedDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
  ],
  base_table: 'wdb_source',
  translatable: FALSE,
  admin_permission: 'administer wdb_source entities',
  entity_keys: [
    'id' => 'id',
    'label' => 'displayname',
    'uuid' => 'uuid',
  ],
  links: [
    'canonical' => '/wdb/source/{wdb_source}',
    'add-form' => '/admin/content/wdb_source/add',
    'edit-form' => '/admin/content/wdb_source/{wdb_source}/edit',
    'delete-form' => '/admin/content/wdb_source/{wdb_source}/delete',
    'collection' => '/admin/content/wdb_source',
  ],
  field_ui_base_route: 'entity.wdb_source.collection',
)]
class WdbSource extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // Standard Drupal entity fields.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the WDB Source entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the WDB Source entity.'))
      ->setReadOnly(TRUE);

    // A unique, human-readable identifier for the source document.
    // This is used for data import mapping and URL generation.
    $fields['source_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source Identifier'))
      ->setDescription(t('A unique, human-readable identifier for the source (e.g., "bm10221").'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100)
      ->addConstraint('UniqueField')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The human-readable display name of the source, used as the entity label.
    $fields['displayname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Display Name'))
      ->setDescription(t('The human-readable display name of the source.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The total number of pages in the source document.
    $fields['pages'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Number of Pages'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_integer', 'weight' => -4])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => -4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The title statement, typically used in IIIF manifests.
    $fields['title_statement'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title Statement'))
      ->setSetting('max_length', 250)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -3])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // A detailed description of the source document.
    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'text_default', 'weight' => -2])
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => -2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Reference to the subsystem(s) this source belongs to.
    // This assumes a taxonomy vocabulary with the machine name
    // 'subsystem' exists.
    $fields['subsystem_tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subsystems'))
      ->setDescription(t('The subsystems this source document belongs to.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'subsystem' => 'subsystem',
        ],
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    $etm = \Drupal::entityTypeManager();

    foreach ($entities as $entity) {
      if (!$entity instanceof self) {
        continue;
      }

      $sid = (int) $entity->id();

      // Block deletion if any Annotation Pages reference this Source.
      $page_ids = $etm->getStorage('wdb_annotation_page')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('source_ref', $sid)
        ->range(0, 1)
        ->execute();
      if (!empty($page_ids)) {
        throw new EntityStorageException('Cannot delete WdbSource ' . $sid . ': referenced by WdbAnnotationPage.');
      }

      // Block deletion if any Word Units reference this Source.
      $wu_ids = $etm->getStorage('wdb_word_unit')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('source_ref', $sid)
        ->range(0, 1)
        ->execute();
      if (!empty($wu_ids)) {
        throw new EntityStorageException('Cannot delete WdbSource ' . $sid . ': referenced by WdbWordUnit.');
      }
    }
  }

}
