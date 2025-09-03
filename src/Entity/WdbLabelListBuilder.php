<?php

namespace Drupal\wdb_core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;
use Drupal\wdb_core\Entity\Traits\ConfigurableListDisplayTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of WDB Label entities.
 *
 * This class is responsible for rendering the administrative list of all
 * WDB Label entities at /admin/content/wdb_label.
 *
 * @see \Drupal\wdb_core\Entity\WdbLabel
 */
class WdbLabelListBuilder extends EntityListBuilder {
  use ConfigurableListDisplayTrait;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Cached set of label IDs that are referenced by SignInterpretation.
   *
   * @var array<int,bool>|null
   */
  protected ?array $referencedLabelIdSet = NULL;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = $this->buildConfigurableHeader([
      'id',
      'label_name',
      'annotation_page_ref',
      'label_center_x',
      'label_center_y',
      'annotation_uri',
    ]) + parent::buildHeader();
    // Add a pseudo column showing whether the label is linked from any
    // Sign Interpretation, placing it just before the operations column.
    if (isset($header['operations'])) {
      $ops = $header['operations'];
      unset($header['operations']);
      $header['linked'] = $this->t('Linked');
      $header['operations'] = $ops;
    }
    else {
      $header['linked'] = $this->t('Linked');
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\wdb_core\Entity\WdbLabel $entity */
    // Build parent row first to obtain operations and other default cells.
    $parent_row = parent::buildRow($entity);
    $operations = $parent_row['operations'] ?? NULL;
    if (isset($parent_row['operations'])) {
      unset($parent_row['operations']);
    }

    // Build configurable data cells.
    $data_row = $this->buildConfigurableRow($entity, [
      'id',
      'label_name',
      'annotation_page_ref',
      'label_center_x',
      'label_center_y',
      'annotation_uri',
    ]);

    // Compute Linked column from cached referenced ID set.
    $ref = $this->getReferencedLabelIdSet();
    $data_row['linked'] = !empty($ref[$entity->id()]) ? (string) $this->t('Yes') : (string) $this->t('No');

    $row = $data_row + $parent_row;
    if ($operations !== NULL) {
      $row['operations'] = $operations;
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type) {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    $instance->configFactory = $container->get('config.factory');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getListEntityTypeId(): string {
    return 'wdb_label';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Add custom operations: edit in editor (preselect) and delete with guard.
    if ($entity instanceof WdbLabel) {
      /** @var \Drupal\wdb_core\Entity\WdbLabel $entity */
      $page = $entity->get('annotation_page_ref')->entity;
      if ($page instanceof WdbAnnotationPage) {
        /** @var \Drupal\wdb_core\Entity\WdbAnnotationPage $page */
        $source = $page->get('source_ref')->entity;
        if ($source instanceof WdbSource) {
          // Resolve subsystem name from first tag.
          $subsysTerm = $source->get('subsystem_tags')->entity;
          $subsys = $subsysTerm ? strtolower($subsysTerm->getName()) : NULL;
          if ($subsys) {
            // Build highlight id (prefer annotation_uri)
            $highlight = $entity->get('annotation_uri')->value;
            if (!$highlight) {
              $highlight = Url::fromRoute(
                'entity.wdb_label.canonical',
                ['wdb_label' => $entity->id()],
                ['absolute' => TRUE]
              )->toString();
            }
            $url = Url::fromRoute('wdb_core.annotation_edit_page', [
              'subsysname' => $subsys,
              'source' => $source->get('source_identifier')->value,
              'page' => (int) $page->get('page_number')->value,
            ], [
              'query' => [
                'highlight_annotation' => $highlight,
              ],
            ]);

            $operations['edit_in_editor'] = [
              'title' => $this->t('Edit label'),
              'weight' => 0,
              'url' => $url,
            ];
          }
        }
      }
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $request = $this->requestStack->getCurrentRequest();
    $orphanOnly = $request ? (string) $request->query->get('orphan_only', '0') === '1' : FALSE;

    if (!$orphanOnly) {
      return parent::getEntityIds();
    }

    // Collect referenced label IDs (distinct) from SignInterpretation.
    $referenced_label_ids = array_keys($this->getReferencedLabelIdSet());

    $label_query = $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->sort($this->entityType->getKey('id'), 'ASC')
      ->pager($this->limit);

    if (!empty($referenced_label_ids)) {
      $label_query->condition($this->entityType->getKey('id'), $referenced_label_ids, 'NOT IN');
    }

    return $label_query->execute();
  }

  /**
   * Get a cached set of referenced label IDs keyed by ID => TRUE.
   *
   * @return array<int,bool>
   *   Set of referenced label IDs.
   */
  protected function getReferencedLabelIdSet(): array {
    if ($this->referencedLabelIdSet !== NULL) {
      return $this->referencedLabelIdSet;
    }
    $db = \Drupal::database();
    $ids = $db->select('wdb_sign_interpretation_field_data', 'si')
      ->fields('si', ['label_ref'])
      ->isNotNull('label_ref')
      ->condition('label_ref', 0, '>')
      ->distinct()
      ->execute()
      ->fetchCol();
    $set = [];
    foreach ($ids as $id) {
      $int = (int) $id;
      if ($int > 0) {
        $set[$int] = TRUE;
      }
    }
    $this->referencedLabelIdSet = $set;
    return $this->referencedLabelIdSet;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $request = $this->requestStack->getCurrentRequest();
    $orphanOnly = $request ? (string) $request->query->get('orphan_only', '0') === '1' : FALSE;

    $all_url = Url::fromRoute('entity.wdb_label.collection')->toString();
    $orph_url = Url::fromRoute('entity.wdb_label.collection', [], ['query' => ['orphan_only' => 1]])->toString();

    $build['wdb_label_filters'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="wdb-list-tabs" style="margin-bottom:8px;border-bottom:1px solid #ccc;"><a href="{{ all_url }}" class="tab{{ all_active }}" style="display:inline-block;padding:6px 10px;border:1px solid #ccc;border-bottom:none;border-radius:4px 4px 0 0;margin-right:4px;text-decoration:none;{{ all_style }}">{{ all_label }}</a><a href="{{ orph_url }}" class="tab{{ orph_active }}" style="display:inline-block;padding:6px 10px;border:1px solid #ccc;border-bottom:none;border-radius:4px 4px 0 0;text-decoration:none;{{ orph_style }}">{{ orph_label }}</a></div>',
      '#context' => [
        'all_url' => $all_url,
        'orph_url' => $orph_url,
        'all_label' => (string) $this->t('All'),
        'orph_label' => (string) $this->t('Orphans only'),
        'all_active' => $orphanOnly ? '' : ' is-active',
        'orph_active' => $orphanOnly ? ' is-active' : '',
        // Inline styles to highlight active tab more strongly.
        'all_style' => $orphanOnly ? 'background:#f8f9fa;color:#333;' : 'background:#e6f2ff;border-color:#69c;color:#003;font-weight:600;',
        'orph_style' => $orphanOnly ? 'background:#e6f2ff;border-color:#69c;color:#003;font-weight:600;' : 'background:#f8f9fa;color:#333;',
      ],
      '#weight' => -100,
    ];

    return $build;
  }

}
