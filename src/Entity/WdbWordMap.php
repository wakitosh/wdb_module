<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the WDB Word Map entity.
 *
 * This entity acts as a mapping table, creating a many-to-many relationship
 * between a WDB Sign Interpretation (a character on a page) and a WDB Word
 * Unit (an instance of a word). It also stores the sequence of the sign
 * within the word.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_word_map',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Word Map Link'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbWordMapListBuilder',
    'form' => [
      'default' => 'Drupal\\wdb_core\\Form\\WdbWordMapEditForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
  ],
  base_table: 'wdb_word_map',
  data_table: 'wdb_word_map_field_data',
  translatable: FALSE,
  admin_permission: 'administer wdb_word_map entities',
  entity_keys: [
    'id' => 'id',
    'label' => 'id',
    'uuid' => 'uuid',
  ],
  links: [
    'canonical' => '/wdb/word_map/{wdb_word_map}',
    'edit-form' => '/admin/content/wdb_word_map/{wdb_word_map}/edit',
    'collection' => '/admin/content/wdb_word_map',
  ],
  field_ui_base_route: 'entity.wdb_word_map.collection',
)]
class WdbWordMap extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')->setLabel(t('ID'))->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')->setLabel(t('UUID'))->setReadOnly(TRUE);

    // Reference to the WDB Sign Interpretation entity.
    $fields['sign_interpretation_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sign Interpretation'))
      ->setSetting('target_type', 'wdb_sign_interpretation')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Reference to the WDB Word Unit entity.
    $fields['word_unit_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Word Unit'))
      ->setSetting('target_type', 'wdb_word_unit')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // The sequence of this sign within the context of the word unit.
    $fields['sign_sequence'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Sign Sequence'))
      ->setDescription(t('The order of the sign interpretation within the word unit.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $original = $this->getOriginal();
    if (!$this->isNew() && $original) {
      $changed = [];

      $newSign = $this->get('sign_interpretation_ref')->target_id ?? NULL;
      $oldSign = $original->get('sign_interpretation_ref')->target_id ?? NULL;
      if ((string) $newSign !== (string) $oldSign) {
        $changed[] = 'sign_interpretation_ref';
      }

      $newWord = $this->get('word_unit_ref')->target_id ?? NULL;
      $oldWord = $original->get('word_unit_ref')->target_id ?? NULL;
      if ((string) $newWord !== (string) $oldWord) {
        $changed[] = 'word_unit_ref';
      }

      if (!empty($changed)) {
        throw new EntityStorageException('Protected fields cannot be changed on WdbWordMap: ' . implode(', ', $changed));
      }
    }
  }

}
