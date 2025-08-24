<?php

namespace Drupal\wdb_core\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic settings form for selecting columns in entity list pages.
 */
class WdbEntityListDisplaySettingsForm extends ConfigFormBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->requestStack = $container->get('request_stack');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    $entity_type_id = $this->getEntityTypeId();
    return ["wdb_core.list_display.$entity_type_id"];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $entity_type_id = $this->getEntityTypeId();
    return 'wdb_core_list_display_settings_' . $entity_type_id;
  }

  /**
   * Resolve entity type ID from route defaults.
   */
  protected function getEntityTypeId(): string {
    $request = $this->requestStack->getCurrentRequest();
    $entity_type_id = (string) ($request->attributes->get('entity_type_id') ?? '');
    return $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getEntityTypeId();
    $config = $this->config("wdb_core.list_display.$entity_type_id");
    $selected = $config->get('fields') ?? [];

    // Build checkbox options from field definitions.
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_type_id);
    $options = [
      'id' => $this->t('ID'),
    ];
    foreach ($definitions as $name => $def) {
      $options[$name] = $def->getLabel();
    }

    // Remove selections for fields that no longer exist.
    if (!empty($selected)) {
      $selected = array_values(array_intersect(array_keys($options), $selected));
    }

    // If no valid saved config, derive sensible defaults from the entity's
    // ListBuilder header (the columns it shows by default).
    $fallback = ['id'];
    if (empty($selected)) {
      try {
        /** @var \Drupal\Core\Entity\EntityListBuilder $list_builder */
        $list_builder = $this->entityTypeManager->getListBuilder($entity_type_id);
        $header = array_keys($list_builder->buildHeader());
        $option_keys = array_keys($options);
        $derived = array_values(array_intersect($header, $option_keys));
        if (!empty($derived)) {
          $fallback = $derived;
        }
      }
      catch (\Throwable $e) {
        // Keep default fallback.
      }
    }

    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Columns to display'),
      '#options' => $options,
      '#default_value' => $selected ?: $fallback,
      '#description' => $this->t('Select the fields to show as columns on the list page. Newly added fields also appear here.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type_id = $this->getEntityTypeId();
    $values = $form_state->getValue('fields') ?? [];
    // Filter out unchecked (value 0) and keep order as submitted keys order.
    $selected = array_values(array_filter($values));
    // Extra safety: remove any values that are not valid fields anymore.
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_type_id);
    $valid = array_merge(['id', 'langcode'], array_keys($definitions));
    $selected = array_values(array_intersect($valid, $selected));
    $this->configFactory->getEditable("wdb_core.list_display.$entity_type_id")
      ->set('fields', $selected)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
