<?php

namespace Drupal\wdb_core\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for WdbCompositeUnique.
 */
class WdbCompositeUniqueValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$constraint instanceof WdbCompositeUnique) {
      throw new UnexpectedTypeException($constraint, WdbCompositeUnique::class);
    }

    // The context object may be the entity, a FieldItemList, or a single
    // FieldItem depending on where the constraint was attached. Normalize.
    $object = $this->context->getObject();
    if ($object instanceof ContentEntityInterface) {
      $entity = $object;
    }
    elseif ($object instanceof FieldItemListInterface) {
      $entity = $object->getEntity();
    }
    elseif ($object instanceof FieldItemInterface) {
      $entity = $object->getEntity();
    }
    else {
      // Not a supported context.
      return;
    }

    // Collect field names (explicit or inferred from context field name).
    $fields = $constraint->fields;
    if (empty($fields)) {
      $field_name = $this->context->getPropertyName();
      if ($field_name) {
        $fields = [$field_name];
      }
      else {
        // Nothing to check.
        return;
      }
    }

    // Ensure all fields exist; if misconfigured, skip silently.
    foreach ($fields as $f) {
      if (!$entity->hasField($f)) {
        return;
      }
    }

    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());

    $lookup = [
      'langcode' => $entity->language()->getId(),
    ];
    foreach ($fields as $f) {
      $item = $entity->get($f);
      if ($item->isEmpty()) {
        // 変更点: 空文字を入力値として扱い重複チェックを行いたいケース.
        // (例: wdb_sign_function.function_name が空の場合) があるため、
        // 文字列/数値系フィールドは NULL ではなく空文字 '' を採用する。
        // entity_reference の空は未選択として NULL 維持し全体のバリデーションをスキップします.
        $def_empty = $entity->getFieldDefinition($f);
        if ($def_empty && $def_empty->getType() === 'entity_reference') {
          // 参照未選択はまだ検証しないため NULL のままです.
          $lookup[$f] = NULL;
        }
        else {
          $lookup[$f] = '';
        }
        continue;
      }
      $def = $entity->getFieldDefinition($f);
      if ($def && $def->getType() === 'entity_reference') {
        // Use first target ID for single-value reference fields.
        $lookup[$f] = $item->first() ? $item->first()->getValue()['target_id'] ?? NULL : NULL;
      }
      else {
        // Scalar (value) field.
        $first = $item->first();
        if ($first && method_exists($first, 'getValue')) {
          $vals = $first->getValue();
          if (is_array($vals) && array_key_exists('value', $vals)) {
            $lookup[$f] = $vals['value'];
          }
          else {
            $lookup[$f] = NULL;
          }
        }
        else {
          $lookup[$f] = NULL;
        }
      }
    }
    // For entities where langcode is inherited from a referenced parent,
    // adjust the langcode in the lookup before querying so validation
    // reflects the eventual stored value (preSave happens later):
    // - wdb_sign_function inherits from sign_ref
    // - wdb_word_meaning inherits from word_ref.
    if ($entity->getEntityTypeId() === 'wdb_sign_function' && $entity->hasField('sign_ref')) {
      $parent = $entity->get('sign_ref')->entity;
      if ($parent instanceof ContentEntityInterface) {
        $parent_lang = $parent->language()->getId();
        if ($parent_lang) {
          $lookup['langcode'] = $parent_lang;
        }
      }
    }
    elseif ($entity->getEntityTypeId() === 'wdb_word_meaning' && $entity->hasField('word_ref')) {
      $parent = $entity->get('word_ref')->entity;
      if ($parent instanceof ContentEntityInterface) {
        $parent_lang = $parent->language()->getId();
        if ($parent_lang) {
          $lookup['langcode'] = $parent_lang;
        }
      }
    }

    // Skip only if any component is NULL (meaning not yet provided). Empty
    // string '' should still be validated so duplicates with blank values
    // are caught before hitting the DB UNIQUE constraint.
    foreach ($fields as $f) {
      if (!array_key_exists($f, $lookup) || $lookup[$f] === NULL) {
        return;
      }
    }

    $query = $storage->getQuery()
      ->condition('langcode', $lookup['langcode'])
      ->accessCheck(FALSE)
      ->range(0, 2);
    foreach ($fields as $f) {
      $query->condition($f, $lookup[$f]);
    }
    $result_ids = $query->execute();

    if ($result_ids) {
      $only_self = (count($result_ids) === 1 && (int) reset($result_ids) === (int) $entity->id());
      if (!$only_self) {
        // Human-friendly labels: for entity_reference fields load the
        // referenced entity label (e.g., show sign_code instead of ID).
        $human_parts = [];
        foreach ($fields as $f) {
          $raw = $lookup[$f];
          $val = $raw;
          try {
            $def = $entity->getFieldDefinition($f);
            if ($def && $def->getType() === 'entity_reference' && ctype_digit((string) $raw)) {
              $target_type = $def->getSetting('target_type');
              if ($target_type) {
                $ref_storage = \Drupal::entityTypeManager()->getStorage($target_type);
                $ref = $ref_storage->load($raw);
                if ($ref) {
                  $val = $ref->label();
                }
              }
            }
          }
          catch (\Exception $e) {
            // Fallback silently to raw.
          }
          $human_parts[] = (string) $val;
        }
        $this->context->addViolation($constraint->message, [
          '%lang' => $lookup['langcode'],
          '%label' => implode(', ', $human_parts),
        ]);
      }
    }

    // Additional safeguard: ensure predicted sign_function_code uniqueness.
    // This specifically addresses cases where function_name is empty and
    // composite (sign_ref, function_name) does not catch duplicates due to
    // timing or legacy data oddities.
    if ($entity->getEntityTypeId() === 'wdb_sign_function' && $entity->hasField('sign_ref')) {
      $sign_parent = $entity->get('sign_ref')->entity;
      if ($sign_parent instanceof ContentEntityInterface && $sign_parent->hasField('sign_code')) {
        $sign_code_val = (string) ($sign_parent->get('sign_code')->value ?? '');
        $fn_val = (string) ($entity->get('function_name')->value ?? '');
        // Normalize as preSave would.
        if ($fn_val === NULL) {
          $fn_val = '';
        }
        if ($sign_code_val !== '') {
          $predicted = $sign_code_val . '_' . $fn_val;
          // Query existing entities with same predicted code + langcode.
          $sf_storage = \Drupal::entityTypeManager()->getStorage('wdb_sign_function');
          $dupe_query = $sf_storage->getQuery()
            ->condition('sign_function_code', $predicted)
            ->condition('langcode', $lookup['langcode'])
            ->accessCheck(FALSE)
            ->range(0, 2);
          if (!$entity->isNew()) {
            $dupe_query->condition($entity->getEntityType()->getKey('id'), $entity->id(), '!=');
          }
          $dupe_ids = $dupe_query->execute();
          if (!empty($dupe_ids)) {
            $this->context->addViolation('The generated sign function code "%code" already exists for language %lang.', [
              '%code' => $predicted,
              '%lang' => $lookup['langcode'],
            ]);
          }
        }
      }
    }
  }

}
