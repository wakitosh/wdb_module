<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the WDB Import Log entity.
 *
 * This entity stores a log of each data import job, including its status,
 * summary, and a list of created entities, enabling traceability and
 * potential rollback functionality.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_import_log',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Import Log'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbImportLogListBuilder',
    'form' => [
      'delete' => 'Drupal\\Core\\Entity\\ContentEntityDeleteForm',
    ],
    'access' => 'Drupal\\wdb_core\\Access\\WdbImportLogAccessControlHandler',
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
  ],
  base_table: 'wdb_import_log',
  data_table: 'wdb_import_log_field_data',
  translatable: FALSE,
  admin_permission: 'administer wdb import logs',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  links: [
    'canonical' => '/wdb/import_log/{wdb_import_log}',
    'collection' => '/admin/content/wdb/import_log',
    'rollback-form' => '/admin/content/wdb/import_log/{wdb_import_log}/rollback',
    'delete-form' => '/admin/content/wdb/import_log/{wdb_import_log}/delete',
  ],
)]
class WdbImportLog extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    // The label for this import job, used as the entity label.
    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('A label for this import job, e.g., "Import on YYYY-MM-DD for egy".'))
      ->setRequired(TRUE);

    // The user who ran the import job.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ]);

    // The timestamp when the import job was run.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the import job was run.'));

    // The status of the import job (succeeded or failed).
    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the import job (succeeded or failed).'))
      ->setDefaultValue(TRUE);

    // A summary of the import results (e.g., number of created/failed rows).
    $fields['summary'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Summary'))
      ->setDescription(t('A summary of the import results.'));

    // A log of entities created during this import, for potential rollback.
    $fields['created_entities'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Created Entities Log'))
      ->setDescription(t('A JSON-encoded list of entities created during this import job.'));

    // The name of the source file that was imported.
    $fields['source_filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source Filename'))
      ->setDescription(t('The name of the file that was imported.'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -3,
      ]);

    // The language of the data that was imported.
    $fields['language'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Target Language'))
      ->setDescription(t('The language of the imported data.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'language',
        'weight' => -2,
      ]);

    return $fields;
  }

}
