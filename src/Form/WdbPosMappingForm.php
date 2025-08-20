<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the WDB POS Mapping configuration entity form.
 */
class WdbPosMappingForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a WdbPosMappingForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service (for contextual translations).
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\wdb_core\Entity\WdbPosMapping $pos_mapping */
    $pos_mapping = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $pos_mapping->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $pos_mapping->id(),
      '#machine_name' => [
        'exists' => '\Drupal\wdb_core\Entity\WdbPosMapping::load',
      ],
      '#disabled' => !$pos_mapping->isNew(),
    ];

    $form['source_pos_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source POS String'),
      '#description' => $this->t('The exact Part-of-Speech string from the external tool (e.g., MeCab UniDic). Example: "名詞-普通名詞-一般"'),
      '#default_value' => $pos_mapping->source_pos_string,
      '#required' => TRUE,
    ];
    // Build a list of lexical category terms (translated to UI language).
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $tree = $term_storage->loadTree('lexical_category');
    $lc_options = [];
    foreach ($tree as $item) {
      /** @var \stdClass $item */
      $term_entity = $term_storage->load($item->tid);
      if (!$term_entity) {
        continue;
      }
      $translated = $this->entityRepository->getTranslationFromContext($term_entity);
      $label = $translated->label();
      $lc_options[$item->tid] = str_repeat('--', (int) $item->depth) . ' ' . $label;
    }

    $form['target_lexical_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Target Lexical Category'),
      '#options' => $lc_options,
      '#default_value' => $pos_mapping->target_lexical_category,
      '#description' => $this->t('The WDB lexical category this source POS maps to.'),
      '#required' => TRUE,
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('Lighter items (smaller numbers) are evaluated first.'),
      '#default_value' => $pos_mapping->weight,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $pos_mapping = $this->entity;
    $status = $pos_mapping->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created the %label POS mapping rule.', ['%label' => $pos_mapping->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Saved the %label POS mapping rule.', ['%label' => $pos_mapping->label()]));
    }
    $form_state->setRedirectUrl($pos_mapping->toUrl('collection'));
  }

}
