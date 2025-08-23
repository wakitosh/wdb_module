<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;

/**
 * Defines the WDB Annotation Page entity.
 *
 * Represents a single page (canvas) in a source document, linking it to a
 * specific IIIF image and serving as a container for annotations on that
 * page.
 */
#[\Drupal\Core\Entity\Attribute\ContentEntityType(
  id: 'wdb_annotation_page',
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup('WDB Annotation Page'),
  handlers: [
    'view_builder' => 'Drupal\\Core\\Entity\\EntityViewBuilder',
    'list_builder' => 'Drupal\\wdb_core\\Entity\\WdbAnnotationPageListBuilder',
    'form' => [
      'default' => 'Drupal\\wdb_core\\Form\\WdbAnnotationPageForm',
      'add' => 'Drupal\\wdb_core\\Form\\WdbAnnotationPageForm',
      'edit' => 'Drupal\\wdb_core\\Form\\WdbAnnotationPageForm',
      'delete' => 'Drupal\\Core\\Entity\\ContentEntityDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\AdminHtmlRouteProvider',
    ],
    'translation' => 'Drupal\\content_translation\\ContentTranslationHandler',
  ],
  base_table: 'wdb_annotation_page',
  data_table: 'wdb_annotation_page_field_data',
  translatable: TRUE,
  admin_permission: 'administer wdb_annotation_page entities',
  entity_keys: [
    'id' => 'id',
    'label' => 'page_name_computed',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
  ],
  links: [
    'canonical' => '/wdb/annotation_page/{wdb_annotation_page}',
    'add-form' => '/admin/content/wdb_annotation_page/add',
    'edit-form' => '/admin/content/wdb_annotation_page/{wdb_annotation_page}/edit',
    'delete-form' => '/admin/content/wdb_annotation_page/{wdb_annotation_page}/delete',
    'collection' => '/admin/content/wdb_annotation_page',
  ],
  field_ui_base_route: 'entity.wdb_annotation_page.collection',
)]
class WdbAnnotationPage extends ContentEntityBase implements ContentEntityInterface {

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
      ->setDisplayConfigurable('form', TRUE);

    // Corresponds to the original 'annotation' (varchar(100), PK) column.
    $fields['annotation_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Annotation Code'))
      ->setDescription(t('The original unique identifier (e.g., bm10221_3).'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 100)
      ->addConstraint('UniqueField')
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => -5])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    // Source reference (FK to WdbSource).
    $fields['source_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source Document'))
      ->setDescription(t('Reference to the WDB Source entity.'))
      ->setSetting('target_type', 'wdb_source')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => -4])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => -4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Corresponds to the 'page' (int) column.
    $fields['page_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Page Number'))
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_integer', 'weight' => -3])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => -3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Corresponds to the 'pagename' (varchar(100)) column.
    $fields['page_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Page Name'))
      ->setSetting('max_length', 100)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -2])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Computed label (entity_keys.label).
    $fields['page_name_computed'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Computed Page Label'))
      ->setDescription(t('A computed label for the page (e.g., Source Title - Page X).'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\wdb_core\Field\AnnotationPageComputedLabel')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['image_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('IIIF Image Identifier'))
      ->setDescription(t('The unique identifier for the image on the IIIF server (e.g., "da/F-5-7_001r").'))
      ->setSetting('max_length', 100)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -1])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['canvas_identifier_fragment'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Canvas Identifier Fragment'))
      ->setDescription(t('A unique fragment of the Canvas URI (e.g., /wdb/hdb/gallery/source_id/canvas/page_num) used for lookup.'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255)
      ->addConstraint('UniqueField')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    /** @var \Drupal\wdb_core\Entity\WdbSource $source_entity */
    $source_entity = $this->get('source_ref')->entity;
    $page_number = $this->get('page_number')->value;

    // Generate annotation_code & canvas_identifier_fragment before save.
    if ($source_entity instanceof WdbSource && $source_entity->hasField('source_identifier') && $page_number !== NULL) {
      $source_identifier = $source_entity->get('source_identifier')->value;
      if (!empty($source_identifier)) {
        $this->set('annotation_code', $source_identifier . '_' . $page_number);

        // Build canvas URI fragment (uses subsystem name if available).
        $subsys_name = '';
        // Safely obtain referenced subsystem tags.
        $subsystem_field = $source_entity->get('subsystem_tags');
        $subsystem_tags = [];
        if (method_exists($subsystem_field, 'referencedEntities')) {
          $subsystem_tags = $subsystem_field->referencedEntities();
        }
        if (!empty($subsystem_tags)) {
          $first_tag = reset($subsystem_tags);
          $subsys_name = strtolower($first_tag->getName());
        }

        if ($subsys_name) {
          // Store the absolute path from the site root.
          $this->set('canvas_identifier_fragment', '/wdb/' . $subsys_name . '/gallery/' . $source_identifier . '/canvas/' . $page_number);
        }
        else {
          // Fallback or error handling if the subsystem name is not found.
          $this->set('canvas_identifier_fragment', '/wdb/default/gallery/' . $source_identifier . '/canvas/' . $page_number);
          \Drupal::logger('wdb_core')->warning('Subsystem not found for WdbSource ID @id when generating canvas_identifier_fragment for WdbAnnotationPage.', ['@id' => $source_entity->id()]);
        }
      }
    }
  }

  /**
   * Gets the IIIF image identifier.
   *
   * If the 'image_identifier' field is set and force is FALSE, it returns
   * its value. Otherwise, it attempts to generate it based on the subsystem's
   * configured pattern.
   *
   * @param bool $force_regeneration
   *   If TRUE, it will always try to generate the identifier from the pattern,
   *   ignoring any existing value.
   *
   * @return string|null
   *   The image identifier, or NULL if it cannot be determined.
   */
  public function getImageIdentifier(bool $force_regeneration = FALSE): ?string {
    // Early return if already set and not forcing regeneration.
    if (!$force_regeneration && $this->hasField('image_identifier') && !$this->get('image_identifier')->isEmpty()) {
      return $this->get('image_identifier')->value;
    }

    // Otherwise, try to generate it from the pattern.
    /** @var \Drupal\wdb_core\Entity\WdbSource $source */
    $source = $this->get('source_ref')->entity;
    if (!$source) {
      return NULL;
    }

    /** @var \Drupal\taxonomy\TermInterface $subsystem */
    $subsystem = $source->get('subsystem_tags')->entity;
    if (!$subsystem) {
      return NULL;
    }

    $config_name = 'wdb_core.subsystem.' . $subsystem->id();
    $subsystem_config = \Drupal::config($config_name);
    $pattern = $subsystem_config->get('iiif_identifier_pattern');

    if (empty($pattern)) {
      // No pattern: return existing value unless forced regeneration.
      return $force_regeneration ? NULL : ($this->get('image_identifier')->value ?? NULL);
    }

    // Replace placeholders with actual values.
    $replacements = [
      '{source_identifier}' => $source->get('source_identifier')->value,
      '{page_number}' => $this->get('page_number')->value,
      '{page_name}' => $this->get('page_name')->value,
      '{subsystem_name}' => $subsystem->getName(),
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $pattern);
  }

  /**
   * Generates and returns the canonical Canvas URI for this page entity.
   *
   * @return string
   *   The absolute Canvas URI.
   */
  public function getCanvasUri(): string {
    // Primarily, generate the URI from the pre-saved fragment field.
    if ($this->hasField('canvas_identifier_fragment') && !$this->get('canvas_identifier_fragment')->isEmpty()) {
      $fragment = $this->get('canvas_identifier_fragment')->value;
      return Url::fromUserInput($fragment, ['absolute' => TRUE])->toString();
    }

    // Fallback logic to construct the URI from route parameters.
    // Fallback for entities predating preSave logic.
    /** @var \Drupal\wdb_core\Entity\WdbSource $source */
    $source = $this->get('source_ref')->entity;
    if ($source) {
      $subsys_tag = $source->get('subsystem_tags')->entity;
      if ($subsys_tag) {
        return Url::fromRoute('wdb_core.iiif_canvas', [
          'subsysname' => strtolower($subsys_tag->getName()),
          'source' => $source->get('source_identifier')->value,
          'page' => $this->get('page_number')->value,
        ], ['absolute' => TRUE])->toString();
      }
    }

    // Final fallback.
    return '';
  }

}
