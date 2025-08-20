<?php

namespace Drupal\wdb_core\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wdb_core\Entity\WdbWord;
use Drupal\wdb_core\Entity\WdbWordMeaning;
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

    $is_new = $this->entity->isNew();

    // Hide langcode entirely; inherit from referenced word.
    if (isset($form['langcode'])) {
      $form['langcode']['#access'] = FALSE;
    }

    $word_storage = $this->entityTypeManager->getStorage('wdb_word');

    if ($is_new && isset($form['word_ref']['widget']['#options'])) {
      $words = $word_storage->loadMultiple();
      $options = [];
      foreach ($words as $word) {
        if (!$word instanceof WdbWord) {
          continue;
        }
        // Translate the word label in the current interface language.
        $translated_word = $this->entityRepository->getTranslationFromContext($word);
        $basic = $translated_word->label();
        $lex = '';
        if ($word->hasField('lexical_category_ref') && !$word->get('lexical_category_ref')->isEmpty()) {
          $lex_entity = $word->get('lexical_category_ref')->entity;
          if ($lex_entity) {
            $lex_entity = $this->entityRepository->getTranslationFromContext($lex_entity);
            $lex = $lex_entity->label();
          }
        }
        $lang = $word->language()->getId();
        // Format: basic (lexical / lang)  - lexical optional.
        if ($lex !== '') {
          $label = $basic . ' (' . $lex . ' / ' . $lang . ')';
        }
        else {
          $label = $basic . ' (' . $lang . ')';
        }
        $options[$word->id()] = $label;
      }
      natcasesort($options);
      if (isset($form['word_ref']['widget']['#options']['_none'])) {
        $options = ['_none' => $this->t('- None -')] + $options;
      }
      $form['word_ref']['widget']['#options'] = $options;
      // Ensure correct per-language caching of translated option labels.
      $form['word_ref']['widget']['#cache']['contexts'][] = 'languages:language_interface';
    }
    elseif (!$is_new && isset($form['word_ref'])) {
      // Replace displayed label with formatted composite and disable.
      $selected = NULL;
      if (isset($form['word_ref']['widget']['#default_value'])) {
        $selected = $form['word_ref']['widget']['#default_value'];
        if (is_array($selected)) {
          $selected = reset($selected);
        }
      }
      if (!$selected && $this->entity->get('word_ref')->target_id) {
        $selected = $this->entity->get('word_ref')->target_id;
      }
      if ($selected) {
        $word = $word_storage->load($selected);
        if ($word instanceof WdbWord) {
          $translated_word = $this->entityRepository->getTranslationFromContext($word);
          $basic = $translated_word->label();
          $lex = '';
          if ($word->hasField('lexical_category_ref') && !$word->get('lexical_category_ref')->isEmpty()) {
            $lex_entity = $word->get('lexical_category_ref')->entity;
            if ($lex_entity) {
              $lex_entity = $this->entityRepository->getTranslationFromContext($lex_entity);
              $lex = $lex_entity->label();
            }
          }
          $lang = $word->language()->getId();
          if ($lex !== '') {
            $label = $basic . ' (' . $lex . ' / ' . $lang . ')';
          }
          else {
            $label = $basic . ' (' . $lang . ')';
          }
          $form['word_ref']['widget']['#options'] = [$word->id() => $label];
          $form['word_ref']['widget']['#cache']['contexts'][] = 'languages:language_interface';
        }
      }
      $form['word_ref']['#disabled'] = TRUE;
    }

    // Auto-suggest next meaning_identifier via AJAX when word_ref changes.
    if ($is_new && isset($form['word_ref']['widget'])) {
      // Attach AJAX to the select element. The options_select widget stores
      // the select items directly under ['widget'].
      $form['word_ref']['widget']['#ajax'] = [
        'callback' => '::updateSuggestedMeaningIdentifier',
        'event' => 'change',
        'wrapper' => 'meaning-identifier-wrapper',
        'progress' => ['type' => 'throbber'],
      ];
    }
    if (isset($form['meaning_identifier'])) {
      // Wrap to allow AJAX replace.
      $form['meaning_identifier']['#prefix'] = '<div id="meaning-identifier-wrapper">';
      $form['meaning_identifier']['#suffix'] = '</div>';
      // Determine selected word id from form state. Support both shapes the
      // entity reference widget may produce.
      $selected_word_id = NULL;
      $word_value = $form_state->getValue('word_ref');
      if (is_array($word_value)) {
        if (isset($word_value[0]['target_id']) && $word_value[0]['target_id'] !== '') {
          $selected_word_id = (int) $word_value[0]['target_id'];
        }
        elseif (isset($word_value['target_id']) && $word_value['target_id'] !== '') {
          $selected_word_id = (int) $word_value['target_id'];
        }
      }
      if ($is_new && $form_state->getTriggeringElement() && $selected_word_id) {
        // Track whether current value was auto-suggested previously.
        $auto_state = $form_state->get('auto_meaning_identifier') ?? [];
        $current_val = $form_state->getValue(['meaning_identifier', 0, 'value']);
        $was_auto = !empty($auto_state['suggested']) && isset($auto_state['value']) && (string) $auto_state['value'] === (string) $current_val;

        // Re-suggest if empty OR still auto from previous suggestion.
        if ($current_val === NULL || $current_val === '' || $was_auto) {
          $suggested = $this->computeNextMeaningIdentifier($selected_word_id);
          if ($suggested !== NULL) {
            $form['meaning_identifier']['widget'][0]['value']['#value'] = $suggested;
            $form_state->set('auto_meaning_identifier', [
              'suggested' => TRUE,
              'value' => $suggested,
              'word_id' => $selected_word_id,
            ]);
          }
        }
        else {
          // User changed it manually; stop auto updates.
          $form_state->set('auto_meaning_identifier', [
            'suggested' => FALSE,
            'value' => $current_val,
            'word_id' => $selected_word_id,
          ]);
        }
      }
    }

    return $form;
  }

  /**
   * AJAX callback to update suggested meaning identifier.
   */
  public function updateSuggestedMeaningIdentifier(array &$form, FormStateInterface $form_state) {
    return $form['meaning_identifier'];
  }

  /**
   * Compute next meaning_identifier for given word id.
   */
  protected function computeNextMeaningIdentifier(int $word_id): ?int {
    if ($word_id <= 0) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('wdb_word_meaning');
    $query = $storage->getQuery()
      ->condition('word_ref', $word_id)
      ->accessCheck(FALSE)
      ->sort('meaning_identifier', 'DESC')
      ->range(0, 1);
    $ids = $query->execute();
    if (!$ids) {
      return 1;
    }
    $entities = $storage->loadMultiple($ids);
    $wm = reset($entities);
    if ($wm instanceof WdbWordMeaning && !$wm->get('meaning_identifier')->isEmpty()) {
      $current = (int) $wm->get('meaning_identifier')->value;
      return $current + 1;
    }
    return 1;
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
