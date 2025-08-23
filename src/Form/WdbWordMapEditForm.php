<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Safe edit form for WdbWordMap.
 *
 * Allows editing sign_sequence only; locks entity references.
 */
class WdbWordMapEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Lock protected refs.
    foreach (['sign_interpretation_ref', 'word_unit_ref'] as $name) {
      if (isset($form[$name])) {
        $form[$name]['#disabled'] = TRUE;
      }
    }

    // Ensure editable field is present even if form display isn't configured.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    if (!isset($form['sign_sequence'])) {
      $form['sign_sequence'] = [
        '#type' => 'number',
        '#title' => $this->t('Sign Sequence'),
        '#default_value' => $entity->get('sign_sequence')->value ?? NULL,
        '#step' => 0.001,
        '#required' => TRUE,
        '#weight' => 10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    // Map fallback widget back to entity field when injected above.
    if ($form_state->hasValue('sign_sequence')) {
      $entity->set('sign_sequence', $form_state->getValue('sign_sequence'));
    }
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved.'));
    $form_state->setRedirectUrl($entity->toUrl('canonical'));
    return $status;
  }

}
