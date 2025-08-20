<?php

namespace Drupal\wdb_core\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Composite uniqueness constraint plugin.
 *
 * Ensures a composite uniqueness across specified fields + langcode.
 *
 * Usage example:
 * @code
 *   $fields['basic_form']->addConstraint('WdbCompositeUnique', [
 *     'fields' => ['basic_form', 'lexical_category_ref'],
 *   ]);
 * @endcode
 *
 * @Constraint(
 *   id = "WdbCompositeUnique",
 *   label = @Translation("WDB composite uniqueness"),
 *   type = "entity"
 * )
 */
class WdbCompositeUnique extends Constraint {

  /**
   * Message template.
   *
   * @var string
   */
  public string $message = 'The combination (language: %lang, %label) already exists.';

  /**
   * Field names participating (excluding langcode automatically added).
   *
   * @var string[]
   */
  public array $fields = [];

  /**
   * {@inheritdoc}
   */
  public function getTargets(): string|array {
    return self::CLASS_CONSTRAINT;
  }

}
