<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the WDB Sign entity.
 *
 * This entity represents a fundamental sign or grapheme in the writing system,
 * serving as a dictionary entry for a character.
 *
 * @ContentEntityType(
 *   id = "wdb_sign",
 *   label = @Translation("WDB Sign"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wdb_core\Entity\WdbSignListBuilder",
 *     "form" = {
 *       "default" = "Drupal\wdb_core\Form\WdbSignForm",
 *       "add" = "Drupal\wdb_core\Form\WdbSignForm",
 *       "edit" = "Drupal\wdb_core\Form\WdbSignForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "wdb_sign",
 *   data_table = "wdb_sign_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer wdb_sign entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "sign_code",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   },
 *   links = {
 *     "canonical" = "/wdb/sign/{wdb_sign}",
 *     "add-form" = "/admin/content/wdb_sign/add",
 *     "edit-form" = "/admin/content/wdb_sign/{wdb_sign}/edit",
 *     "delete-form" = "/admin/content/wdb_sign/{wdb_sign}/delete",
 *     "collection" = "/admin/content/wdb_sign"
 *   },
 *   field_ui_base_route = "entity.wdb_sign.collection"
 * )
 */
class WdbSign extends ContentEntityBase implements ContentEntityInterface {

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
      ->setDescription(t('The language code of the WDB Sign entity.'))
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Corresponds to the original 'sign' (varchar(20), PK) column.
    // This unique code is used as the entity label.
    $fields['sign_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sign Code'))
      ->setDescription(t('The unique code for the sign.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 20)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => -5])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('WdbCompositeUnique', [
        'fields' => ['sign_code'],
      ]);

    return $fields;
  }

}
