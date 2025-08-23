<?php

namespace Drupal\wdb_core\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\wdb_core\Entity\WdbAnnotationPage;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the WDB Annotation Page entity edit forms.
 *
 * This class provides the form for creating and editing WdbAnnotationPage
 * entities and adds custom status messages upon saving.
 *
 * @ingroup wdb_core
 */
class WdbAnnotationPageForm extends ContentEntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WdbAnnotationPageForm.
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

    $is_new = $this->entity->isNew();

    // Reorder Source dropdown options by WdbSource entity ID (ascending).
    if ($is_new && isset($form['source_ref']['widget']['#options'])) {
      $source_storage = $this->entityTypeManager->getStorage('wdb_source');
      $sources = $source_storage->loadMultiple();
      // Ensure numeric ascending order by entity id.
      ksort($sources, SORT_NUMERIC);
      $options = [];
      foreach ($sources as $source) {
        // Translate label in current interface language.
        $translated = $this->entityRepository->getTranslationFromContext($source);
        $options[$source->id()] = $translated->label();
      }
      if (isset($form['source_ref']['widget']['#options']['_none'])) {
        $options = ['_none' => $this->t('- None -')] + $options;
      }
      $form['source_ref']['widget']['#options'] = $options;
      $form['source_ref']['widget']['#cache']['contexts'][] = 'languages:language_interface';
    }

    // Auto-suggest next page_number via AJAX when source_ref changes.
    if ($is_new && isset($form['source_ref']['widget'])) {
      $form['source_ref']['widget']['#ajax'] = [
        'callback' => '::updateSuggestedPageNumber',
        'event' => 'change',
        'wrapper' => 'page-number-wrapper',
        'progress' => ['type' => 'throbber'],
      ];
    }

    if (isset($form['page_number'])) {
      // Wrap the page_number field to allow AJAX replacement.
      $form['page_number']['#prefix'] = '<div id="page-number-wrapper">';
      $form['page_number']['#suffix'] = '</div>';

      // Determine selected source id from form state. Support common shapes
      // that entity reference widgets may produce.
      $selected_source_id = NULL;
      $source_value = $form_state->getValue('source_ref');
      if (is_array($source_value)) {
        if (isset($source_value[0]['target_id']) && $source_value[0]['target_id'] !== '') {
          $selected_source_id = (int) $source_value[0]['target_id'];
        }
        elseif (isset($source_value['target_id']) && $source_value['target_id'] !== '') {
          $selected_source_id = (int) $source_value['target_id'];
        }
      }

      if ($is_new && $form_state->getTriggeringElement() && $selected_source_id) {
        // Track whether current value was auto-suggested previously.
        $auto_state = $form_state->get('auto_page_number') ?? [];
        $current_val = $form_state->getValue(['page_number', 0, 'value']);
        $was_auto = !empty($auto_state['suggested']) && isset($auto_state['value']) && (string) $auto_state['value'] === (string) $current_val;

        // Re-suggest if empty OR still auto from previous suggestion.
        if ($current_val === NULL || $current_val === '' || $was_auto) {
          $suggested = $this->computeNextPageNumber($selected_source_id);
          if ($suggested !== NULL) {
            $form['page_number']['widget'][0]['value']['#value'] = $suggested;
            $form_state->set('auto_page_number', [
              'suggested' => TRUE,
              'value' => $suggested,
              'source_id' => $selected_source_id,
            ]);
          }
        }
        else {
          // User changed it manually; stop auto updates.
          $form_state->set('auto_page_number', [
            'suggested' => FALSE,
            'value' => $current_val,
            'source_id' => $selected_source_id,
          ]);
        }
      }
    }

    return $form;
  }

  /**
   * AJAX callback to update suggested page number.
   */
  public function updateSuggestedPageNumber(array &$form, FormStateInterface $form_state) {
    return $form['page_number'];
  }

  /**
   * Compute next page_number for given source id.
   */
  protected function computeNextPageNumber(int $source_id): ?int {
    if ($source_id <= 0) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('wdb_annotation_page');
    $query = $storage->getQuery()
      ->condition('source_ref', $source_id)
      ->accessCheck(FALSE)
      ->sort('page_number', 'DESC')
      ->range(0, 1);
    $ids = $query->execute();
    if (!$ids) {
      return 1;
    }
    $entities = $storage->loadMultiple($ids);
    $ap = reset($entities);
    /** @var \Drupal\wdb_core\Entity\WdbAnnotationPage|null $ap */
    if ($ap instanceof WdbAnnotationPage && !$ap->get('page_number')->isEmpty()) {
      $current = (int) $ap->get('page_number')->value;
      return $current + 1;
    }
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    // Set a custom status message based on whether the entity is new or
    // being updated.
    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Annotation Page.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Annotation Page.', [
          '%label' => $entity->label(),
        ]));
    }

    // Redirect the user back to the collection page after saving.
    $form_state->setRedirect('entity.wdb_annotation_page.collection');

    return $status;
  }

}
