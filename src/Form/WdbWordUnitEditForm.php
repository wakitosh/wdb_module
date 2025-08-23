<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Safe edit form for WdbWordUnit.
 *
 * Allows editing realized_form and note; locks references and ordering.
 */
class WdbWordUnitEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Lock protected fields in the UI.
    $protected = [
      'source_ref',
      'word_sequence',
      'annotation_page_refs',
      'word_meaning_ref',
      'langcode',
      'original_word_unit_identifier',
    ];
    foreach ($protected as $name) {
      if (isset($form[$name])) {
        $form[$name]['#disabled'] = TRUE;
      }
    }

    // Ensure editable fields are present even if form display isn't configured.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    if (!isset($form['realized_form'])) {
      $form['realized_form'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Realized Form'),
        '#default_value' => $entity->get('realized_form')->value ?? '',
        '#maxlength' => 255,
        '#weight' => 10,
      ];
    }

    else {
      $form['realized_form']['#description'] = $this->t('Edit surface form (typos/variants).');
    }
    if (!isset($form['note'])) {
      $form['note'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Note (Word Unit)'),
        '#default_value' => $entity->get('note')->value ?? '',
        '#weight' => 20,
      ];
    }
    // Ensure grammar taxonomy reference widgets exist when the
    // form display configuration is missing.
    foreach ([
      'person_ref' => $this->t('Person'),
      'gender_ref' => $this->t('Gender'),
      'number_ref' => $this->t('Number'),
      'verbal_form_ref' => $this->t('Verbal Form'),
      'aspect_ref' => $this->t('Aspect'),
      'mood_ref' => $this->t('Mood'),
      'voice_ref' => $this->t('Voice'),
      'grammatical_case_ref' => $this->t('Grammatical Case'),
    ] as $field_name => $label) {
      if (!isset($form[$field_name])) {
        $vid = str_replace('_ref', '', $field_name);
        $options = [];
        // Load terms from the vocabulary to build a dropdown.
        /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        /** @var array<int, object> $terms */
        $terms = $term_storage->loadTree($vid);
        foreach ($terms as $term) {
          $options[$term->tid] = $term->name;
        }
        $default_tid = $entity->get($field_name)->target_id ?? NULL;
        $form[$field_name] = [
          '#type' => 'select',
          '#title' => $label,
          '#options' => $options,
          '#default_value' => $default_tid ?: NULL,
          '#empty_option' => $this->t('- None -'),
          '#required' => FALSE,
          '#weight' => 30,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    // Map fallback widgets back to entity fields when they were injected above.
    foreach (['realized_form', 'note'] as $name) {
      if ($form_state->hasValue($name)) {
        $entity->set($name, $form_state->getValue($name));
      }
    }
    // Map grammar taxonomy references from select dropdowns.
    foreach ([
      'person_ref', 'gender_ref', 'number_ref', 'verbal_form_ref',
      'aspect_ref', 'mood_ref', 'voice_ref', 'grammatical_case_ref',
    ] as $field_name) {
      if ($form_state->hasValue($field_name)) {
        $tid = $form_state->getValue($field_name);
        // Accept empty selection as clearing the reference.
        $entity->set($field_name, $tid ? (int) $tid : NULL);
      }
    }
    $status = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved.'));
    $form_state->setRedirectUrl($entity->toUrl('canonical'));
    return $status;
  }

}
