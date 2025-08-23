<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Import Log entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Import Log entities.
 */
class WdbImportLogListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new WdbImportLogListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    // Injects the date.formatter service via dependency injection.
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $build = parent::buildHeader();
    $header['label'] = $this->t('Import Job');
    $header['source_filename'] = $this->t('Source File');
    $header['language'] = $this->t('Language');
    $header['user_id'] = $this->t('Author');
    $header['created'] = $this->t('Date');
    $header['summary'] = $this->t('Summary / Status');
    return $header + $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbImportLog $entity */
    $row['label']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl(),
    ];
    $row['source_filename'] = $entity->get('source_filename')->value;
    $row['language'] = $entity->get('language')->value;
    $row['user_id'] = $entity->get('user_id')->entity ? $entity->get('user_id')->entity->label() : '';
    $row['created'] = $this->dateFormatter->format($entity->get('created')->value, 'short');

    // Build the summary cell as a render array to add conditional styling.
    $summary_markup = $entity->get('summary')->value;
    $summary_cell = [
      'data' => [
        '#markup' => $summary_markup,
      ],
    ];

    if (!$entity->get('status')->value) {
      // If the job failed or has been rolled back, italicize the text
      // and add a CSS class to the cell for styling.
      $summary_cell['data']['#prefix'] = '<em>';
      $summary_cell['data']['#suffix'] = '</em>';
      // Use a standard Drupal core class for error text color.
      $summary_cell['class'][] = 'color-error';
    }

    $row['summary'] = $summary_cell;
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbImportLog $entity */
    $operations = parent::getDefaultOperations($entity);

    // Always present a rollback line (B案): active if there are created
    // entities, otherwise disabled (no link) to show no-op state.
    $created_entities_json = $entity->get('created_entities')->value;
    $created_entities = !empty($created_entities_json) ? json_decode($created_entities_json, TRUE) : [];

    $has_created = !empty($created_entities);
    // Show rollback to users with proper permission, independent of
    // delete access.
    $can_rollback = \Drupal::currentUser()->hasPermission('administer wdb import logs');
    if ($can_rollback && $entity->hasLinkTemplate('rollback-form')) {
      if ($has_created) {
        $operations['rollback'] = [
          'title' => $this->t('Rollback'),
          'weight' => 20,
          'url' => $this->ensureDestination($entity->toUrl('rollback-form')),
        ];
      }
      else {
        // Disabled placeholder: keep ordering & clarity without confusing link.
        $operations['rollback_disabled'] = [
          'title' => $this->t('Rollback'),
          'weight' => 20,
          'url' => $entity->toUrl(),
          'attributes' => [
            'class' => ['is-disabled', 'disabled'],
            'aria-disabled' => 'true',
            'tabindex' => '-1',
            'onclick' => 'return false;',
          ],
        ];
      }
    }

    // If delete is not allowed by access handler, show a disabled
    // placeholder to explain.
    if (!$entity->access('delete')) {
      $operations['delete_disabled'] = [
        'title' => $this->t('Delete'),
        'weight' => 30,
        'url' => $entity->toUrl(),
        'attributes' => [
          'class' => ['is-disabled', 'disabled'],
          'aria-disabled' => 'true',
          'tabindex' => '-1',
          'onclick' => 'return false;',
        ],
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    // Attach admin library globally to the listing for disabled styling.
    $build['#attached']['library'][] = 'wdb_core/wdb_admin';
    return $build;
  }

}
