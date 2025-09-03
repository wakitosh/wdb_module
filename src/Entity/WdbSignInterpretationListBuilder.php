<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Sign Interpretation entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Sign Interpretation entities.
 *
 * @see \Drupal\wdb_core\Entity\WdbSignInterpretation
 */
class WdbSignInterpretationListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return $this->buildConfigurableHeader([
      'id',
      'sign_interpretation_code',
      'annotation_page_ref',
      'label_ref',
      'sign_function_ref',
      'line_number',
      'phone',
      'priority',
      'note',
    ]) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbSignInterpretation $entity */
    return $this->buildConfigurableRow($entity, [
      'id',
      'sign_interpretation_code',
      'annotation_page_ref',
      'label_ref',
      'sign_function_ref',
      'line_number',
      'phone',
      'priority',
      'note',
    ]) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type) {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    $instance->configFactory = $container->get('config.factory');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getListEntityTypeId(): string {
    return 'wdb_sign_interpretation';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Add an operation to open the polygon editor with the linked label
    // pre-selected when a label reference exists.
    /** @var \Drupal\wdb_core\Entity\WdbSignInterpretation $entity */
    $label = $entity->get('label_ref')->entity;
    $page = $entity->get('annotation_page_ref')->entity;
    if ($label && $page) {
      $source = $page->get('source_ref')->entity;
      if ($source) {
        $subsysTerm = $source->get('subsystem_tags')->entity;
        $subsys = $subsysTerm ? strtolower($subsysTerm->getName()) : NULL;
        if ($subsys) {
          $highlight = $label->get('annotation_uri')->value ?: Url::fromRoute(
            'entity.wdb_label.canonical',
            ['wdb_label' => $label->id()],
            ['absolute' => TRUE]
          )->toString();
          $url = Url::fromRoute('wdb_core.annotation_edit_page', [
            'subsysname' => $subsys,
            'source' => $source->get('source_identifier')->value,
            'page' => (int) $page->get('page_number')->value,
          ], [
            'query' => [
              'highlight_annotation' => $highlight,
            ],
          ]);
          $operations['edit_label'] = [
            'title' => $this->t('Edit label'),
            'weight' => 0,
            'url' => $url,
          ];
        }
      }
    }
    return $operations;
  }

}
