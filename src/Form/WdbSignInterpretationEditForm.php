<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Safe edit form for WdbSignInterpretation.
 *
 * Locks protected fields and allows editing only safe fields
 * (phone, priority, note, line_number).
 */
class WdbSignInterpretationEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Disable protected fields in the UI.
    $protected = [
      'sign_interpretation_code',
      'langcode',
      'annotation_page_ref',
      // label_ref can be adjusted later; keep it editable.
      'sign_function_ref',
    ];
    foreach ($protected as $name) {
      if (isset($form[$name])) {
        $form[$name]['#disabled'] = TRUE;
      }
    }
    // Ensure editable fields are present even if form display isn't configured.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    if (!isset($form['phone'])) {
      $form['phone'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Phonetic'),
        '#default_value' => $entity->get('phone')->value ?? '',
        '#maxlength' => 255,
        '#weight' => 10,
      ];
    }
    if (!isset($form['line_number'])) {
      $form['line_number'] = [
        '#type' => 'number',
        '#title' => $this->t('Line Number'),
        '#default_value' => $entity->get('line_number')->value ?? NULL,
        '#min' => 0,
        '#step' => 1,
        '#weight' => 20,
      ];
    }
    // Ensure label_ref is selectable even without form display.
    // (same page labels only).
    if (!isset($form['label_ref'])) {
      $options = [];
      $storage = $this->entityTypeManager->getStorage('wdb_label');
      $annotation_page_id = $entity->get('annotation_page_ref')->target_id ?? NULL;
      if ($annotation_page_id) {
        $ids = $storage->getQuery()
          ->accessCheck(TRUE)
          ->condition('annotation_page_ref', $annotation_page_id)
          ->sort('label_name', 'ASC')
          ->execute();
        if ($ids) {
          $labels = $storage->loadMultiple($ids);
          foreach ($labels as $label) {
            $options[$label->id()] = $label->label();
          }
        }
      }
      $form['label_ref'] = [
        '#type' => 'select',
        '#title' => $this->t('Label (Region)'),
        '#options' => $options,
        '#default_value' => $entity->get('label_ref')->target_id ?? NULL,
        '#empty_option' => $this->t('- None -'),
        '#required' => FALSE,
        '#description' => $this->t('Select a label from the same Annotation Page.'),
        '#weight' => 15,
      ];
    }
    if (!isset($form['priority'])) {
      $form['priority'] = [
        '#type' => 'number',
        '#title' => $this->t('Priority'),
        '#default_value' => $entity->get('priority')->value ?? NULL,
        '#step' => 0.001,
        '#weight' => 30,
      ];
    }
    if (!isset($form['note'])) {
      $form['note'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Note'),
        '#default_value' => $entity->get('note')->value ?? '',
        '#weight' => 40,
      ];
    }

    // Add an optional reason for manual edit.
    $form['override_reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Edit reason (optional)'),
      '#description' => $this->t('Describe why this record is being manually edited.'),
      '#weight' => 90,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    // Map fallback widgets back to entity fields when they were injected above.
    foreach (['phone', 'line_number', 'priority', 'note'] as $name) {
      if ($form_state->hasValue($name)) {
        $entity->set($name, $form_state->getValue($name));
      }
    }
    // Map label_ref from fallback select when present.
    if ($form_state->hasValue('label_ref')) {
      $label_id = (int) $form_state->getValue('label_ref');
      $entity->set('label_ref', $label_id ?: NULL);
    }

    // Reason is collected but not persisted/logged here to keep it lean.
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved.'));
    $form_state->setRedirectUrl($entity->toUrl('canonical'));
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Ensure the selected label_ref belongs to the same Annotation Page.
    if ($form_state->hasValue('label_ref')) {
      $label_id = (int) $form_state->getValue('label_ref');
      if ($label_id) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->getEntity();
        $annotation_page_id = (int) ($entity->get('annotation_page_ref')->target_id ?? 0);
        $label = $this->entityTypeManager->getStorage('wdb_label')->load($label_id);
        if (!$label) {
          $form_state->setErrorByName('label_ref', $this->t('Selected label does not exist.'));
          return;
        }
        /** @var \Drupal\Core\Entity\ContentEntityInterface $label */
        $label_page = (int) ($label->get('annotation_page_ref')->target_id ?? 0);
        if ($annotation_page_id && $label_page && $annotation_page_id !== $label_page) {
          $form_state->setErrorByName('label_ref', $this->t('The selected label belongs to a different Annotation Page.'));
        }
      }
    }
  }

}
