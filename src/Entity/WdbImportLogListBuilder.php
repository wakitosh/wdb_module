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
    $header['label'] = $this->t('Import Job');
    $header['source_filename'] = $this->t('Source File');
    $header['language'] = $this->t('Language');
    $header['user_id'] = $this->t('Author');
    $header['created'] = $this->t('Date');
    $header['summary'] = $this->t('Summary / Status');
    return $header + parent::buildHeader();
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

    // Add a "Rollback" operation only if there are entities to roll back.
    $created_entities_json = $entity->get('created_entities')->value;
    $created_entities = !empty($created_entities_json) ? json_decode($created_entities_json, TRUE) : [];

    if (!empty($created_entities) && $entity->access('delete') && $entity->hasLinkTemplate('rollback-form')) {
      $operations['rollback'] = [
        'title' => $this->t('Rollback'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('rollback-form')),
      ];
    }

    return $operations;
  }

}
