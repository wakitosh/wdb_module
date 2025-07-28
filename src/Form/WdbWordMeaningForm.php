<?php

namespace Drupal\wdb_core\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the WDB Word Meaning entity edit forms.
 *
 * This class provides the form for creating and editing WdbWordMeaning entities
 * and adds custom status messages upon saving.
 *
 * @ingroup wdb_core
 */
class WdbWordMeaningForm extends ContentEntityForm {

  /**
   * The entity type manager.
   *
   * Note: The property is not type-hinted here to avoid conflicts with parent
   * classes, but it is initialized in the constructor.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WdbWordMeaningForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Get the word_meaning_code field widget and make it read-only.
    if (isset($form['word_meaning_code'])) {
      if (isset($form['word_meaning_code']['widget'][0]['value'])) {
        $form['word_meaning_code']['widget'][0]['value']['#type'] = 'textfield';
        $form['word_meaning_code']['widget'][0]['value']['#disabled'] = TRUE;
        $form['word_meaning_code']['widget'][0]['value']['#description'] = $this->t('This value is automatically generated based on the referenced Word and Meaning Identifier after saving.');
        // Display the current value when editing an existing entity.
        if (!$this->entity->isNew() && $this->entity->get('word_meaning_code')->value) {
          $form['word_meaning_code']['widget'][0]['value']['#default_value'] = $this->entity->get('word_meaning_code')->value;
        }
      }
    }

    // When editing an existing entity, disable the 'langcode' and 'word_ref'
    // fields to prevent changes that could lead to data inconsistency.
    if (!$this->entity->isNew()) {
      if (isset($form['langcode'])) {
        $form['langcode']['#disabled'] = TRUE;
      }
      if (isset($form['word_ref'])) {
        $form['word_ref']['#disabled'] = TRUE;
      }
    }
    else {
      // When creating a new entity, sort the 'word_ref' options by label.
      if (isset($form['word_ref']['widget']['#options'])) {
        $word_storage = $this->entityTypeManager->getStorage('wdb_word');
        $words = $word_storage->loadMultiple();

        $options = [];
        foreach ($words as $word) {
          $options[$word->id()] = $word->label();
        }

        // Sort the options alphabetically by label, maintaining key association.
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        // For non-required fields, re-add the empty option to the top.
        if (isset($form['word_ref']['widget']['#options']['_none'])) {
          $options = ['_none' => $this->t('- None -')] + $options;
        }

        // Replace the default options with the sorted ones.
        $form['word_ref']['widget']['#options'] = $options;
      }
    }

    // Note: To dynamically preview the code with JavaScript, AJAX properties
    // could be added to the 'word_ref' and 'meaning_identifier' fields here.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // The preSave() hook in the entity class handles setting the
    // 'word_meaning_code', so no special logic is needed here before saving.
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    // Set a custom status message based on the save operation result.
    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label WDB Word Meaning.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label WDB Word Meaning.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.wdb_word_meaning.collection');
  }

}
