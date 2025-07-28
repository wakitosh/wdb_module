<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Form controller for the WDB Word entity edit forms.
 *
 * This form includes AJAX functionality to dynamically update the 'word_code'
 * field based on the values of the 'basic_form' and 'lexical_category_ref'
 * fields.
 */
class WdbWordForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Make the 'word_code' field read-only as it's generated automatically.
    if (isset($form['word_code'])) {
      $form['word_code']['widget'][0]['value']['#disabled'] = TRUE;
      $form['word_code']['widget'][0]['value']['#description'] = $this->t('This code is generated automatically from the Basic Form and Lexical Category.');
      // Add a wrapper for AJAX replacement.
      $form['word_code']['#prefix'] = '<div id="word-code-wrapper">';
      $form['word_code']['#suffix'] = '</div>';
    }

    // Add an AJAX trigger to the 'basic_form' field.
    if (isset($form['basic_form'])) {
      $form['basic_form']['widget'][0]['value']['#ajax'] = [
        'callback' => '::ajaxRefreshWordCode',
        'event' => 'change',
        'wrapper' => 'word-code-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Updating word code...'),
        ],
      ];
    }

    // Add an AJAX trigger to the 'lexical_category_ref' field.
    if (isset($form['lexical_category_ref'])) {
      $form['lexical_category_ref']['widget']['#ajax'] = [
        'callback' => '::ajaxRefreshWordCode',
        'event' => 'change',
        'wrapper' => 'word-code-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Updating word code...'),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state): EntityInterface {
    $entity = parent::buildEntity($form, $form_state);

    // Manually set the word_code on the entity before validation occurs.
    // This is the correct place to build the entity from form values.
    $basic_form = $form_state->getValue(['basic_form', 0, 'value']);
    $lexical_category_tid = $form_state->getValue(['lexical_category_ref', 0, 'target_id']);
    if (!empty($basic_form) && !empty($lexical_category_tid)) {
      $entity->set('word_code', $basic_form . '_' . $lexical_category_tid);
    }

    return $entity;
  }

  /**
   * AJAX callback to refresh the word_code field.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The portion of the form to be re-rendered.
   */
  public function ajaxRefreshWordCode(array &$form, FormStateInterface $form_state) {
    // Get the current values from the form state.
    $basic_form = $form_state->getValue(['basic_form', 0, 'value']);
    $lexical_category_tid = $form_state->getValue(['lexical_category_ref', 0, 'target_id']);

    // Assemble the new word_code.
    $new_word_code = '';
    if (!empty($basic_form) && !empty($lexical_category_tid)) {
      $new_word_code = $basic_form . '_' . $lexical_category_tid;
    }

    // Update the value in the form's word_code field.
    $form['word_code']['widget'][0]['value']['#value'] = $new_word_code;

    // Return the wrapper element to be replaced by the AJAX response.
    return $form['word_code'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // The word_code is now set in buildEntity(), so we can proceed with the
    // normal save process. The preSave() hook in the entity will still ensure
    // the value is correct as a final guarantee.
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label WDB Word.', ['%label' => $entity->label()]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label WDB Word.', ['%label' => $entity->label()]));
    }

    $form_state->setRedirect('entity.wdb_word.collection');
  }

}
