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
 * Form controller for the WDB Sign Function entity edit forms.
 */
class WdbSignFunctionForm extends ContentEntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WdbSignFunctionForm.
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

    if ($is_new && isset($form['sign_ref']['widget']['#options'])) {
      $sign_storage = $this->entityTypeManager->getStorage('wdb_sign');
      $signs = $sign_storage->loadMultiple();
      $options = [];
      foreach ($signs as $sign) {
        $label = $sign->label();
        $lang = $sign->language()->getId();
        // Append langcode in parentheses for disambiguation (e.g., "い(ja)").
        $options[$sign->id()] = $label . '(' . $lang . ')';
      }
      natcasesort($options);
      if (isset($form['sign_ref']['widget']['#options']['_none'])) {
        $options = ['_none' => $this->t('- None -')] + $options;
      }
      $form['sign_ref']['widget']['#options'] = $options;
    }
    elseif (!$is_new && isset($form['sign_ref'])) {
      // Replace the single selected option label to include langcode.
      if (isset($form['sign_ref']['widget']['#default_value']) && $form['sign_ref']['widget']['#default_value']) {
        $selected = $form['sign_ref']['widget']['#default_value'];
        if (is_array($selected)) {
          $selected = reset($selected);
        }
        $sign = $this->entityTypeManager->getStorage('wdb_sign')->load($selected);
        if ($sign) {
          $label = $sign->label();
          $lang = $sign->language()->getId();
          $form['sign_ref']['widget']['#options'] = [$sign->id() => $label . '(' . $lang . ')'];
        }
      }
      $form['sign_ref']['#disabled'] = TRUE;
    }

    // Hide the langcode selector for all forms; it inherits from sign.
    if (isset($form['langcode'])) {
      $form['langcode']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label WDB Sign Function.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label WDB Sign Function.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.wdb_sign_function.collection');
  }

}
