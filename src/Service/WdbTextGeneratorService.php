<?php

namespace Drupal\wdb_core\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for generating full text from annotated data.
 */
class WdbTextGeneratorService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new WdbTextGeneratorService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the full transliterated text for a given annotation page.
   *
   * This method reconstructs the text of a page by fetching all associated
   * word units, sorting them by sequence, and wrapping each word in an HTML
   * span with data attributes for interactivity.
   *
   * @param string $subsysname
   *   The machine name of the subsystem.
   * @param string $source_identifier
   *   The identifier of the source document.
   * @param int $page_number
   *   The page number.
   *
   * @return array
   *   An associative array containing the generated 'html' and a 'title'.
   */
  public function getFullText(string $subsysname, string $source_identifier, int $page_number): array {
    // 1. Get the WdbAnnotationPage entity.
    $annotation_page_storage = $this->entityTypeManager->getStorage('wdb_annotation_page');
    $annotation_pages = $annotation_page_storage->loadByProperties([
      'annotation_code' => $source_identifier . '_' . $page_number,
    ]);
    /** @var \Drupal\wdb_core\Entity\WdbAnnotationPage $wdb_annotation_page_entity */
    $wdb_annotation_page_entity = reset($annotation_pages);
    if (!$wdb_annotation_page_entity) {
      return ['error' => 'Page not found.'];
    }

    // 2. Get all WdbWordUnit entities referencing this page, sorted by sequence.
    $word_unit_storage = $this->entityTypeManager->getStorage('wdb_word_unit');
    $wu_query = $word_unit_storage->getQuery()
      // Search on a multi-value entity reference field.
      ->condition('annotation_page_refs', $wdb_annotation_page_entity->id())
      // Sort by the 'word_sequence' field.
      ->sort('word_sequence', 'ASC')
      ->accessCheck(FALSE);
    $wu_ids = $wu_query->execute();

    if (empty($wu_ids)) {
      return ['html' => '<p>No text available for this page.</p>'];
    }

    $word_units = $word_unit_storage->loadMultiple($wu_ids);

    // Sort again in PHP to be absolutely sure of the order, as database
    // sorting on float fields can sometimes be unpredictable.
    uasort($word_units, function ($a, $b) {
      $seq_a = $a->get('word_sequence')->value ?? 0;
      $seq_b = $b->get('word_sequence')->value ?? 0;
      // Use the spaceship operator (PHP 7+).
      return $seq_a <=> $seq_b;
    });

    $html = '';
    if (!empty($word_units)) {
      $word_map_storage = $this->entityTypeManager->getStorage('wdb_word_map');

      foreach ($word_units as $wu_entity) {
        /** @var \Drupal\wdb_core\Entity\WdbWordUnit $wu_entity */

        // Get the polygon coordinates of all constituent signs for this word.
        $map_ids = $word_map_storage->getQuery()->condition('word_unit_ref', $wu_entity->id())->accessCheck(FALSE)->execute();
        $all_points_for_word = [];
        if ($map_ids) {
          $maps = $word_map_storage->loadMultiple($map_ids);
          foreach ($maps as $map) {
            $si = $map->get('sign_interpretation_ref')->entity;
            if ($si && $si->get('label_ref')->entity && !$si->get('label_ref')->entity->get('polygon_points')->isEmpty()) {
              $points = array_map(fn($item) => $item['value'], $si->get('label_ref')->entity->get('polygon_points')->getValue());
              $all_points_for_word[] = $points;
            }
          }
        }

        // Add data-* attributes to the span for client-side interactivity.
        // Defensive: missing values may be NULL; cast to string.
        $original_id_value = (string) ($wu_entity->get('original_word_unit_identifier')->value ?? '');
        $realized_form_value = (string) ($wu_entity->get('realized_form')->value ?? '');
        $points_json = json_encode($all_points_for_word) ?: '[]';

        $html .= '<span class="word-unit is-clickable" ' .
          'data-word-unit-original-id="' . Html::escape($original_id_value) . '" ' .
          'data-word-points="' . Html::escape($points_json) . '">' .
          Html::escape($realized_form_value) .
          '</span> ';
      }
    }
    return [
      'html' => $html,
      'title' => 'Transliteration',
    ];
  }

}
