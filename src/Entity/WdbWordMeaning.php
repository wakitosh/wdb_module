<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the WDB Word Meaning entity.
 *
 * This entity represents a specific meaning associated with a WDB Word entity.
 * A single word can have multiple meanings, each defined by its own
 * Word Meaning entity.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_word_meaning',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Word Meaning'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbWordMeaningListBuilder',
    'form' => [
      'default' => 'Drupal\\wdb_core\\Form\\WdbWordMeaningForm',
      'add' => 'Drupal\\wdb_core\\Form\\WdbWordMeaningForm',
      'edit' => 'Drupal\\wdb_core\\Form\\WdbWordMeaningForm',
      'delete' => 'Drupal\\Core\\Entity\\ContentEntityDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
  ],
  base_table: 'wdb_word_meaning',
  data_table: 'wdb_word_meaning_field_data',
  translatable: TRUE,
  admin_permission: 'administer wdb_word_meaning entities',
  entity_keys: [
    'id' => 'id',
    'label' => 'word_meaning_code',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
  ],
  links: [
    'canonical' => '/wdb/word_meaning/{wdb_word_meaning}',
    'add-form' => '/admin/content/wdb_word_meaning/add',
    'edit-form' => '/admin/content/wdb_word_meaning/{wdb_word_meaning}/edit',
    'delete-form' => '/admin/content/wdb_word_meaning/{wdb_word_meaning}/delete',
    'collection' => '/admin/content/wdb_word_meaning',
  ],
  field_ui_base_route: 'entity.wdb_word_meaning.collection',
)]
class WdbWordMeaning extends ContentEntityBase implements ContentEntityInterface {

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

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', ['type' => 'language_select', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // A unique code for the word meaning, generated automatically in preSave().
    // This is used as the entity label.
    $fields['word_meaning_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Word Meaning Code'))
      ->setDescription(t('Identifier combining the word code and meaning identifier.'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 240)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => -5])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    // Reference to the parent WDB Word entity.
    $fields['word_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Word'))
      ->setDescription(t('Reference to the WDB Word entity.'))
      ->setSetting('target_type', 'wdb_word')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => -4])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => -4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // An identifier for this specific meaning, often a number (e.g., 1, 2).
    $fields['meaning_identifier'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Meaning Identifier'))
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_integer', 'weight' => -3])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => -3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('WdbCompositeUnique', [
        'fields' => ['word_ref', 'meaning_identifier'],
      ]);

    // The explanation or definition of the word's meaning.
    $fields['explanation'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Explanation'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'text_default', 'weight' => -2])
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => -2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Automatically generate the 'word_meaning_code' by concatenating the
    // parent word's code and the meaning identifier.
    $word_entity = $this->get('word_ref')->entity;
    $meaning_id_value = $this->get('meaning_identifier')->value;

    if ($word_entity instanceof WdbWord && $word_entity->hasField('word_code') && !empty($meaning_id_value)) {
      $word_code = $word_entity->get('word_code')->value;
      if (!empty($word_code)) {
        $this->set('word_meaning_code', $word_code . '_' . $meaning_id_value);
      }
      // Inherit langcode from the referenced word if different to prevent
      // cross-language mismatches.
      $parent_lang = $word_entity->language()->getId();
      if ($parent_lang && $this->language()->getId() !== $parent_lang) {
        $this->set('langcode', $parent_lang);
      }
    }
  }

}
