<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the WDB Sign Function entity.
 *
 * This entity represents a specific function or role that a WDB Sign can have.
 * For example, a single sign might have multiple functions, such as a phonetic
 * value and an ideographic meaning, each represented by a Sign Function entity.
 *
 * @ContentEntityType(
 *   id = "wdb_sign_function",
 *   label = @Translation("WDB Sign Function"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wdb_core\Entity\WdbSignFunctionListBuilder",
 *     "form" = {
 *       "default" = "Drupal\wdb_core\Form\WdbSignFunctionForm",
 *       "add" = "Drupal\wdb_core\Form\WdbSignFunctionForm",
 *       "edit" = "Drupal\wdb_core\Form\WdbSignFunctionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *   },
 *   base_table = "wdb_sign_function",
 *   data_table = "wdb_sign_function_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer wdb_sign_function entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "sign_function_code",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/wdb/sign_function/{wdb_sign_function}",
 *     "add-form" = "/admin/content/wdb_sign_function/add",
 *     "edit-form" = "/admin/content/wdb_sign_function/{wdb_sign_function}/edit",
 *     "delete-form" = "/admin/content/wdb_sign_function/{wdb_sign_function}/delete",
 *     "collection" = "/admin/content/wdb_sign_function",
 *   },
 *   field_ui_base_route = "entity.wdb_sign_function.collection"
 * )
 */
class WdbSignFunction extends ContentEntityBase implements ContentEntityInterface {

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

    // A unique code for the sign function,
    // generated automatically in preSave().
    // This is used as the entity label.
    $fields['sign_function_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sign Function Code'))
      ->setDescription(t('Identifier combining the sign code and function name.'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => -5])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    // Reference to the parent WDB Sign entity.
    $fields['sign_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sign'))
      ->setDescription(t('Reference to the WDB Sign entity.'))
      ->setSetting('target_type', 'wdb_sign')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => -4])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => -4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // The name of the function (e.g., "phonetic", "ideographic").
    // DESIGN NOTE: This field is intentionally optional to reduce
    // friction for humanities researchers preparing TSV imports. When
    // left blank the generated sign_function_code becomes
    // "<sign_code>_" (trailing underscore). Application-level
    // validation prevents creating another entity with the same
    // (langcode, sign_ref, function_name='') combination and also
    // guards against duplicate generated sign_function_code values.
    // This differs from WdbWordMeaning where meaning_identifier is
    // required but explanation may be blank; here we prioritize rapid
    // data entry where the function label is sometimes unknown or
    // deferred.
    $fields['function_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Function Name'))
      ->setDescription(t('Optional. Can be left blank for quicker data entry; an empty value produces a sign function code in the form <sign_code>_.'))
      ->setSetting('max_length', 100)
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -3])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('WdbCompositeUnique', [
        'fields' => ['sign_ref', 'function_name'],
      ]);

    // A detailed description of the sign's function.
    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'text_default', 'weight' => -1])
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => -1, 'rows' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Normalize NULL function_name to '' so UNIQUE
    // (langcode, sign_ref, function_name) works and
    // prevents duplicate NULL groups.
    if ($this->get('function_name')->isEmpty() || $this->get('function_name')->value === NULL) {
      $this->set('function_name', '');
    }

    // Automatically generate the 'sign_function_code'.
    $sign_entity = $this->get('sign_ref')->entity;
    $function_name_value = $this->get('function_name')->value ?? '';
    if ($sign_entity instanceof WdbSign && $sign_entity->hasField('sign_code')) {
      $sign_code = $sign_entity->get('sign_code')->value;
      if (isset($sign_code)) {
        $this->set('sign_function_code', $sign_code . '_' . $function_name_value);
      }
      // Inherit the langcode from the referenced sign to avoid cross-language
      // inconsistencies. Only override if different or empty.
      $sign_lang = $sign_entity->language()->getId();
      if ($sign_lang && $this->language()->getId() !== $sign_lang) {
        $this->set('langcode', $sign_lang);
      }
    }
  }

}
