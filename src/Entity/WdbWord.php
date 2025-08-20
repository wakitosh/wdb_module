<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the WDB Word entity.
 *
 * This entity represents a dictionary entry for a word, defined by its basic
 * form and lexical category. It serves as a parent for different meanings a
 * word can have.
 *
 * @ContentEntityType(
 *   id = "wdb_word",
 *   label = @Translation("WDB Word"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wdb_core\Entity\WdbWordListBuilder",
 *     "form" = {
 *       "default" = "Drupal\wdb_core\Form\WdbWordForm",
 *       "add" = "Drupal\wdb_core\Form\WdbWordForm",
 *       "edit" = "Drupal\wdb_core\Form\WdbWordForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "wdb_word",
 *   data_table = "wdb_word_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer wdb_word entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "basic_form",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/wdb/word/{wdb_word}",
 *     "add-form" = "/admin/content/wdb_word/add",
 *     "edit-form" = "/admin/content/wdb_word/{wdb_word}/edit",
 *     "delete-form" = "/admin/content/wdb_word/{wdb_word}/delete",
 *     "collection" = "/admin/content/wdb_word",
 *   },
 *   field_ui_base_route = "entity.wdb_word.collection"
 * )
 */
class WdbWord extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Automatically generate the 'word_code' from the 'basic_form' and
    // 'lexical_category_ref' fields to ensure a unique identifier.
    $basic_form = $this->get('basic_form')->value;
    $lexical_category_entity = $this->get('lexical_category_ref')->entity;

    if (!empty($basic_form) && $lexical_category_entity) {
      // Get the term ID of the lexical category.
      $lexical_category_id = $lexical_category_entity->id();
      // Set the word_code in the format {basic_form}_{lexical_category_id}.
      $this->set('word_code', $basic_form . '_' . $lexical_category_id);
    }
  }

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

    // A unique code for the word, generated automatically in preSave().
    $fields['word_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Word Code'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 120)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => -5])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    // The basic or dictionary form of the word, used as the entity label.
    $fields['basic_form'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Basic Form'))
      ->setSetting('max_length', 120)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -4])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('WdbCompositeUnique', [
        'fields' => ['basic_form', 'lexical_category_ref'],
      ]);

    // Reference to the lexical category (e.g., "noun", "verb") taxonomy term.
    $fields['lexical_category_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Lexical Category'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'lexical_category' => 'lexical_category',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
