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
 * Defines the WDB Word Unit entity.
 *
 * This entity represents a specific instance of a word as it appears in the
 * text (a "token"). It links together the word's meaning, its grammatical
 * properties, its location on annotation pages, and its sequence in the
 * document.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_word_unit',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Word Unit'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbWordUnitListBuilder',
    'form' => [
      'default' => 'Drupal\\wdb_core\\Form\\WdbWordUnitEditForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
    'translation' => 'Drupal\\content_translation\\ContentTranslationHandler',
  ],
  base_table: 'wdb_word_unit',
  data_table: 'wdb_word_unit_field_data',
  translatable: TRUE,
  admin_permission: 'administer wdb_word_unit entities',
  entity_keys: [
    'id' => 'id',
    'label' => 'realized_form',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
  ],
  links: [
    'canonical' => '/wdb/word_unit/{wdb_word_unit}',
    'edit-form' => '/admin/content/wdb_word_unit/{wdb_word_unit}/edit',
    'collection' => '/admin/content/wdb_word_unit',
  ],
  field_ui_base_route: 'entity.wdb_word_unit.collection',
)]
class WdbWordUnit extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')->setLabel(t('ID'))->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')->setLabel(t('UUID'))->setReadOnly(TRUE);
    $fields['langcode'] = BaseFieldDefinition::create('language')->setLabel(t('Language'))->setTranslatable(TRUE);

    // Stores the composite primary key from the original 'word_units' table.
    $fields['original_word_unit_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Original Word Unit Identifier'))
      ->setRequired(TRUE)->addConstraint('UniqueField');

    // Reference to the WdbSource entity.
    $fields['source_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source Document'))
      ->setSetting('target_type', 'wdb_source')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // The actual text of the word as it appears in the source.
    // Used as the entity label.
    $fields['realized_form'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Realized Form'))
      ->setSetting('max_length', 255)->setTranslatable(TRUE);

    // The sequential order of the word within the entire source document.
    $fields['word_sequence'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Word Sequence'))
      ->setDescription(t('The order of the word within the source document.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // A multi-value reference to all pages where this word unit appears.
    $fields['annotation_page_refs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Page Occurrences'))
      ->setDescription(t('A list of pages where this word unit appears.'))
      ->setSetting('target_type', 'wdb_annotation_page')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(TRUE);

    // Reference to the WdbWordMeaning entity.
    $fields['word_meaning_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Word Meaning'))
      ->setSetting('target_type', 'wdb_word_meaning')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // A set of entity reference fields for various grammatical categories.
    // This loop creates a field for each category, linking to a corresponding
    // taxonomy vocabulary.
    $grammar_categories = [
      'person' => t('Person'),
      'gender' => t('Gender'),
      'number' => t('Number'),
      'verbal_form' => t('Verbal Form'),
      'aspect' => t('Aspect'),
      'mood' => t('Mood'),
      'voice' => t('Voice'),
      'grammatical_case' => t('Grammatical Case'),
    ];
    foreach ($grammar_categories as $field_name => $label) {
      $fields[$field_name . '_ref'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($label)
        ->setSetting('target_type', 'taxonomy_term')
        ->setSetting('handler_settings', ['target_bundles' => [$field_name => $field_name]])
        ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    }

    // A general-purpose note field for this specific word unit.
    $fields['note'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Note (Word Unit)'))->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!$this->isNew() && ($this->getOriginal())) {
      $original = $this->getOriginal();
      $changed = [];

      $compareScalar = function ($field) use ($original) {
        $new = $this->get($field)->value ?? NULL;
        $old = $original->get($field)->value ?? NULL;
        // Normalize float comparison to avoid false positives due to string
        // formatting differences (e.g., '1' vs '1.0').
        if ($field === 'word_sequence') {
          if ($new === NULL && $old === NULL) {
            return FALSE;
          }
          return (float) $new !== (float) $old;
        }
        return $new !== $old;
      };
      $compareRef = function ($field) use ($original) {
        $new = $this->get($field)->target_id ?? NULL;
        $old = $original->get($field)->target_id ?? NULL;
        return (string) $new !== (string) $old;
      };

      foreach ([
        'source_ref', 'word_meaning_ref',
      ] as $ref) {
        if ($compareRef($ref)) {
          $changed[] = $ref;
        }
      }

      foreach (['word_sequence', 'langcode', 'original_word_unit_identifier'] as $sc) {
        if ($compareScalar($sc)) {
          $changed[] = $sc;
        }
      }

      if (!empty($changed)) {
        throw new EntityStorageException('Protected fields cannot be changed on WdbWordUnit: ' . implode(', ', $changed));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    parent::postLoad($storage, $entities);

    // Populate annotation_page_refs dynamically from WordMap relations so that
    // consumers (including tests) can see page occurrences immediately after
    // load, even if earlier appends were skipped or not yet persisted.
    $etm = \Drupal::entityTypeManager();
    $wm_storage = $etm->getStorage('wdb_word_map');

    foreach ($entities as $entity) {
      if (!$entity instanceof self) {
        continue;
      }

      $maps = $wm_storage->loadByProperties(['word_unit_ref' => $entity->id()]);
      if (!$maps) {
        continue;
      }

      $page_ids = [];
      foreach ($maps as $map) {
        /** @var \Drupal\wdb_core\Entity\WdbWordMap $map */
        $si = $map->get('sign_interpretation_ref')->entity;
        if ($si instanceof WdbSignInterpretation) {
          $pid = (int) ($si->get('annotation_page_ref')->target_id ?? 0);
          if ($pid) {
            $page_ids[$pid] = TRUE;
          }
        }
      }

      if ($page_ids) {
        $items = array_map(static fn($id) => ['target_id' => (int) $id], array_keys($page_ids));
        // Set on the entity instance (no save) so callers see the values.
        $entity->set('annotation_page_refs', $items);
      }
    }
  }

}
