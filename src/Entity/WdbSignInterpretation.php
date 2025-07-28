<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the WDB Sign Interpretation entity.
 *
 * This entity represents the interpretation of a specific sign (via WdbLabel)
 * on a specific page (WdbAnnotationPage) as having a particular function
 * (WdbSignFunction). It is the core link between the visual annotation and
 * its linguistic meaning.
 *
 * @ContentEntityType(
 *   id = "wdb_sign_interpretation",
 *   label = @Translation("WDB Sign Interpretation"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wdb_core\Entity\WdbSignInterpretationListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler"
 *   },
 *   base_table = "wdb_sign_interpretation",
 *   data_table = "wdb_sign_interpretation_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer wdb_sign_interpretation entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "sign_interpretation_code",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   },
 *   links = {
 *     "canonical" = "/wdb/sign_interpretation/{wdb_sign_interpretation}",
 *     "collection" = "/admin/content/wdb_sign_interpretation"
 *   },
 *   field_ui_base_route = "entity.wdb_sign_interpretation.collection"
 * )
 */
class WdbSignInterpretation extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')->setLabel(t('ID'))->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')->setLabel(t('UUID'))->setReadOnly(TRUE);
    $fields['langcode'] = BaseFieldDefinition::create('language')->setLabel(t('Language'))->setTranslatable(TRUE);

    // A unique code for the interpretation, used as the entity label.
    $fields['sign_interpretation_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sign Interpretation Code'))
      ->setRequired(TRUE)->setSetting('max_length', 20)->addConstraint('UniqueField')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Reference to the WdbAnnotationPage where this interpretation occurs.
    $fields['annotation_page_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Annotation Page'))
      ->setSetting('target_type', 'wdb_annotation_page')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Reference to the WdbLabel (the drawn polygon on the image).
    $fields['label_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Label (Region)'))
      ->setDescription(t('Reference to the WdbLabel entity. Can be null if the interpretation is not tied to a specific drawn label.'))
      ->setSetting('target_type', 'wdb_label')
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Reference to the WdbSignFunction, defining what this sign means in this context.
    $fields['sign_function_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sign Function'))
      ->setSetting('target_type', 'wdb_sign_function')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // The line number on the page where the sign appears.
    $fields['line_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Line Number'))
      ->setSetting('unsigned', TRUE);

    // The phonetic value or reading of the sign in this context.
    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Phonetic'))
      ->setSetting('max_length', 255)->setTranslatable(TRUE);

    // A priority value, potentially for ordering multiple interpretations.
    $fields['priority'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Priority'));

    // A general-purpose note field for this interpretation.
    $fields['note'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Note'))->setTranslatable(TRUE);

    return $fields;
  }

}
