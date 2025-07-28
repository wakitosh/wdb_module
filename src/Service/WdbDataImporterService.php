<?php

namespace Drupal\wdb_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\wdb_core\Entity\WdbSource;
use Drupal\wdb_core\Entity\WdbAnnotationPage;
use Drupal\wdb_core\Entity\WdbLabel;
use Drupal\wdb_core\Entity\WdbSign;
use Drupal\wdb_core\Entity\WdbSignFunction;
use Drupal\wdb_core\Entity\WdbSignInterpretation;
use Drupal\wdb_core\Entity\WdbWord;
use Drupal\wdb_core\Entity\WdbWordMeaning;
use Drupal\wdb_core\Entity\WdbWordUnit;
use Drupal\wdb_core\Entity\WdbWordMap;

/**
 * Service for importing linguistic data from a file.
 *
 * This service handles the business logic for processing rows from a data file
 * (e.g., TSV) and creating or updating the corresponding entities in the
 * database. It is typically used within a batch process.
 */
class WdbDataImporterService {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new WdbDataImporterService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Processes a single row of data from the import file.
   *
   * @param array $rowData
   *   The data from a single row of the TSV file.
   * @param string $langcode
   *   The language code for the new entities.
   * @param float $word_seq
   *   The sequence number for the word.
   * @param float $sign_seq
   *   The sequence number for the sign.
   * @param array $context
   *   The batch API context array.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function processImportRow(array $rowData, string $langcode, float $word_seq, float $sign_seq, array &$context): bool {
    $row_num = ($context['results']['created'] ?? 0) + ($context['results']['failed'] ?? 0) + 1;

    // 1. Get values from the row data.
    $source_identifier = trim($rowData['source'] ?? '');
    $page_num = (int) trim($rowData['page'] ?? 0);
    $label_name = trim($rowData['labelname'] ?? '');
    $image_identifier = trim($rowData['image_identifier'] ?? '');
    $sign_code = trim($rowData['sign'] ?? '');
    $function_name = trim($rowData['function'] ?? '');
    $phone = trim($rowData['phone'] ?? '');
    $note = trim($rowData['note'] ?? '');
    $word_unit_from_tsv = (int) trim($rowData['word_unit'] ?? 0);
    $basic_form = trim($rowData['basic_form'] ?? '');
    $realized_form = trim($rowData['realized_form'] ?? '');
    $lexical_category_name = trim($rowData['lexical_category_name'] ?? '');
    $meaning_id = (int) trim($rowData['meaning'] ?? 0);
    $explanation = trim($rowData['explanation'] ?? '');

    $grammar_category_names = [
      'verbal_form' => trim($rowData['verbal_form_name'] ?? ''),
      'gender' => trim($rowData['gender_name'] ?? ''),
      'number' => trim($rowData['number_name'] ?? ''),
      'person' => trim($rowData['person_name'] ?? ''),
      'voice' => trim($rowData['voice_name'] ?? ''),
      'aspect' => trim($rowData['aspect_name'] ?? ''),
      'mood' => trim($rowData['mood_name'] ?? ''),
      'grammatical_case' => trim($rowData['grammatical_case_name'] ?? ''),
    ];

    // 2. Check for required data.
    if (empty($source_identifier) || empty($page_num) || empty($image_identifier) || empty($sign_code) || empty($basic_form) || empty($word_unit_from_tsv)) {
      $context['results']['failed']++;
      $context['results']['errors'][] = $this->t('Skipped row @row_num due to missing required data.', ['@row_num' => $row_num]);
      return FALSE;
    }

    try {
      $line_number = (int) (explode('-', $label_name)[0] ?? 1);

      // 3. Find or create related entities.
      $lexical_category_term = $this->findOrCreateTerm('lexical_category', $lexical_category_name, $langcode, $context);
      if (!$lexical_category_term) {
        throw new \Exception('Lexical category term could not be found or created.');
      }

      $wdb_word_entity = $this->findOrCreateWdbWord($basic_form, $lexical_category_term, $langcode, $context);
      $wdb_word_meaning_entity = $this->findOrCreateWdbWordMeaning($wdb_word_entity, $meaning_id, $explanation, $langcode, $context);

      $wdb_source_entity = $this->findWdbSource($source_identifier);
      if (!$wdb_source_entity) {
        throw new \Exception('Source entity not found: ' . $source_identifier);
      }

      $wdb_annotation_page_entity = $this->findOrCreateWdbAnnotationPage($wdb_source_entity, $page_num, $image_identifier, $context);
      if (!$wdb_annotation_page_entity) {
        throw new \Exception('Annotation Page entity not found for page ' . $page_num);
      }

      $wdb_label_entity = NULL;
      if (!empty($label_name)) {
        $wdb_label_entity = $this->findWdbLabel($wdb_annotation_page_entity, $label_name);
        if (!$wdb_label_entity) {
          $context['results']['warnings'][] = $this->t('Row @row_num: Label entity "@label" not found, proceeding without label link.', ['@row_num' => $row_num, '@label' => $label_name]);
        }
      }

      $wdb_sign_entity = $this->findOrCreateWdbSign($sign_code, $langcode, $context);
      $wdb_sign_function_entity = $this->findOrCreateWdbSignFunction($wdb_sign_entity, $function_name, $langcode, $context);

      // 5. Find or create the WdbSignInterpretation.
      $wdb_si_entity = $this->findOrCreateWdbSignInterpretation($wdb_annotation_page_entity, $wdb_label_entity, $wdb_sign_function_entity, $line_number, $phone, $note, $langcode, $context);

      // 6. Create and associate the remaining entities.
      $grammar_term_refs = $this->findGrammarTerms($grammar_category_names, $langcode, $context);
      $wdb_wu_entity = $this->findOrCreateWdbWordUnit($wdb_source_entity, $wdb_annotation_page_entity, $word_unit_from_tsv, $wdb_word_meaning_entity, $realized_form, $word_seq, $grammar_term_refs, $langcode, $context);
      $this->findOrCreateWdbWordMap($wdb_si_entity, $wdb_wu_entity, $sign_seq, $context);

      $context['results']['created']++;
      return TRUE;

    }
    catch (\Exception $e) {
      $context['results']['failed']++;
      $context['results']['errors'][] = $this->t('Failed to process row @row_num. Error: @message', ['@row_num' => $row_num, '@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  // === Helper Methods ===

  /**
   * Finds an existing taxonomy term by name, or creates one.
   *
   * This method searches for a term with the given name across all available
   * languages. If a match is found (either in the default language or in a
   * translation), that term is returned. If no match is found, a new term is
   * created with the specified language.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param string $name
   *   The term name (can be in any language).
   * @param string $langcode
   *   The language code to use if a new term is created.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The term entity, or NULL if the name is empty.
   */
  private function findOrCreateTerm(string $vid, string $name, string $langcode, array &$context): ?TermInterface {
    if (empty($name)) {
      return NULL;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Query for a term with the given name, regardless of language.
    // The entity query system will search across all translations for a match.
    $query = $term_storage->getQuery()
      ->condition('vid', $vid)
      ->condition('name', $name)
      ->accessCheck(FALSE)
      ->range(0, 1);
    $tids = $query->execute();

    if (!empty($tids)) {
      return $term_storage->load(reset($tids));
    }

    // If no term with this name exists in any language, create a new one.
    // The provided langcode will be set as the term's language.
    $term = $term_storage->create([
      'vid' => $vid,
      'name' => $name,
      'langcode' => $langcode,
    ]);
    $term->save();
    $context['results']['created_entities'][] = ['type' => 'taxonomy_term', 'id' => $term->id()];
    return $term;
  }

  /**
   * Finds an existing WdbWord entity by its unique code, or creates one.
   *
   * @param string $basic_form
   *   The basic form of the word.
   * @param \Drupal\taxonomy\TermInterface $lexical_category_term
   *   The lexical category term.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbWord|null
   *   The word entity.
   */
  private function findOrCreateWdbWord(string $basic_form, TermInterface $lexical_category_term, string $langcode, array &$context): ?WdbWord {
    $word_code = $basic_form . '_' . $lexical_category_term->id();
    $storage = $this->entityTypeManager->getStorage('wdb_word');
    $words = $storage->loadByProperties(['word_code' => $word_code, 'langcode' => $langcode]);
    if (!empty($words)) {
      return reset($words);
    }

    $word = $storage->create([
      'word_code' => $word_code,
      'basic_form' => $basic_form,
      'lexical_category_ref' => $lexical_category_term->id(),
      'langcode' => $langcode,
    ]);
    $word->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_word', 'id' => $word->id()];
    return $word;
  }

  /**
   * Finds an existing WdbWordMeaning entity, or creates one.
   *
   * @param \Drupal\wdb_core\Entity\WdbWord $word_entity
   *   The parent word entity.
   * @param int $meaning_id
   *   The numeric identifier for the meaning.
   * @param string $explanation
   *   The explanation of the meaning.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbWordMeaning|null
   *   The word meaning entity.
   */
  private function findOrCreateWdbWordMeaning(WdbWord $word_entity, int $meaning_id, string $explanation, string $langcode, array &$context): ?WdbWordMeaning {
    $word_meaning_code = $word_entity->get('word_code')->value . '_' . $meaning_id;
    $storage = $this->entityTypeManager->getStorage('wdb_word_meaning');
    $meanings = $storage->loadByProperties(['word_meaning_code' => $word_meaning_code, 'langcode' => $langcode]);
    if (!empty($meanings)) {
      return reset($meanings);
    }

    $meaning = $storage->create([
      'word_meaning_code' => $word_meaning_code,
      'word_ref' => $word_entity->id(),
      'meaning_identifier' => $meaning_id,
      'explanation' => $explanation,
      'langcode' => $langcode,
    ]);
    $meaning->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_word_meaning', 'id' => $meaning->id()];
    return $meaning;
  }

  /**
   * Finds an existing WdbAnnotationPage, or creates one.
   *
   * If an existing page is found, it updates the image identifier if it's empty.
   *
   * @param \Drupal\wdb_core\Entity\WdbSource $source_entity
   *   The parent source entity.
   * @param int $page_num
   *   The page number.
   * @param string $image_identifier
   *   The IIIF image identifier.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbAnnotationPage|null
   *   The annotation page entity.
   */
  private function findOrCreateWdbAnnotationPage(WdbSource $source_entity, int $page_num, string $image_identifier, array &$context): ?WdbAnnotationPage {
    $storage = $this->entityTypeManager->getStorage('wdb_annotation_page');

    // First, search by source and page number.
    $entities = $storage->loadByProperties([
      'source_ref' => $source_entity->id(),
      'page_number' => $page_num,
    ]);

    if ($entity = reset($entities)) {
      // If an entity is found, update the image_identifier if it is currently empty.
      if (empty($entity->get('image_identifier')->value) && !empty($image_identifier)) {
        $entity->set('image_identifier', $image_identifier);
        $entity->save();
      }
      return $entity;
    }

    // If not found, create a new entity.
    $entity = $storage->create([
      'source_ref' => $source_entity->id(),
      'page_number' => $page_num,
    // Temporary page name.
      'page_name' => 'p. ' . $page_num,
      'image_identifier' => $image_identifier,
    ]);
    $entity->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_annotation_page', 'id' => $entity->id()];

    return $entity;
  }

  /**
   * Finds an existing WdbSource entity by its unique identifier.
   *
   * @param string $source_identifier
   *   The source identifier.
   *
   * @return \Drupal\wdb_core\Entity\WdbSource|null
   *   The source entity, or NULL if not found.
   */
  private function findWdbSource(string $source_identifier): ?WdbSource {
    $storage = $this->entityTypeManager->getStorage('wdb_source');
    $entities = $storage->loadByProperties(['source_identifier' => $source_identifier]);
    return !empty($entities) ? reset($entities) : NULL;
  }

  /**
   * Finds an existing WdbLabel entity by page and label name.
   *
   * @param \Drupal\wdb_core\Entity\WdbAnnotationPage $page_entity
   *   The parent annotation page entity.
   * @param string $label_name
   *   The name of the label.
   *
   * @return \Drupal\wdb_core\Entity\WdbLabel|null
   *   The label entity, or NULL if not found.
   */
  private function findWdbLabel(WdbAnnotationPage $page_entity, string $label_name): ?WdbLabel {
    $storage = $this->entityTypeManager->getStorage('wdb_label');
    $entities = $storage->loadByProperties([
      'annotation_page_ref' => $page_entity->id(),
      'label_name' => $label_name,
    ]);
    return !empty($entities) ? reset($entities) : NULL;
  }

  /**
   * Finds an existing WdbSign entity by its code, or creates one.
   *
   * @param string $sign_code
   *   The unique sign code.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbSign|null
   *   The sign entity.
   */
  private function findOrCreateWdbSign(string $sign_code, string $langcode, array &$context): ?WdbSign {
    $storage = $this->entityTypeManager->getStorage('wdb_sign');
    $entities = $storage->loadByProperties(['sign_code' => $sign_code, 'langcode' => $langcode]);
    if (!empty($entities)) {
      return reset($entities);
    }

    $entity = $storage->create(['sign_code' => $sign_code, 'langcode' => $langcode]);
    $entity->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_sign', 'id' => $entity->id()];
    return $entity;
  }

  /**
   * Finds an existing WdbSignFunction entity, or creates one.
   *
   * @param \Drupal\wdb_core\Entity\WdbSign $sign_entity
   *   The parent sign entity.
   * @param string $function_name
   *   The name of the function.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbSignFunction|null
   *   The sign function entity.
   */
  private function findOrCreateWdbSignFunction(WdbSign $sign_entity, string $function_name, string $langcode, array &$context): ?WdbSignFunction {
    $storage = $this->entityTypeManager->getStorage('wdb_sign_function');

    // Since 'sign_function_code' is computed, we must search for existing
    // entities using the fields it depends on.
    $properties = [
      'sign_ref' => $sign_entity->id(),
      'function_name' => $function_name,
      'langcode' => $langcode,
    ];
    $entities = $storage->loadByProperties($properties);

    if (!empty($entities)) {
      return reset($entities);
    }

    // Create a new entity without setting the computed 'sign_function_code'.
    // The value will be generated automatically when the entity is saved.
    $entity = $storage->create([
      'sign_ref' => $sign_entity->id(),
      'function_name' => $function_name,
      'langcode' => $langcode,
    ]);
    $entity->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_sign_function', 'id' => $entity->id()];
    return $entity;
  }

  /**
   * Finds or creates multiple grammar-related taxonomy terms.
   *
   * @param array $grammar_category_names
   *   An associative array where keys are vocabulary IDs and values are term names.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return array
   *   An array of field values for entity creation.
   */
  private function findGrammarTerms(array $grammar_category_names, string $langcode, array &$context): array {
    $refs = [];
    foreach ($grammar_category_names as $vid => $term_name) {
      if (!empty($term_name)) {
        $term = $this->findOrCreateTerm($vid, $term_name, $langcode, $context);
        if ($term) {
          $refs[str_replace('grammatical_', '', $vid) . '_ref'] = $term->id();
        }
      }
    }
    return $refs;
  }

  /**
   * Finds or creates a WdbSignInterpretation entity.
   *
   * @param \Drupal\wdb_core\Entity\WdbAnnotationPage $page
   *   The parent annotation page.
   * @param \Drupal\wdb_core\Entity\WdbLabel|null $label
   *   The associated label entity.
   * @param \Drupal\wdb_core\Entity\WdbSignFunction $sign_function
   *   The associated sign function.
   * @param int $line_number
   *   The line number.
   * @param string $phone
   *   The phonetic value.
   * @param string $note
   *   A note.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbSignInterpretation
   *   The sign interpretation entity.
   */
  private function findOrCreateWdbSignInterpretation(WdbAnnotationPage $page, ?WdbLabel $label, WdbSignFunction $sign_function, int $line_number, string $phone, string $note, string $langcode, array &$context): WdbSignInterpretation {
    $storage = $this->entityTypeManager->getStorage('wdb_sign_interpretation');

    // Search for an existing entity.
    $properties = [
      'annotation_page_ref' => $page->id(),
      'sign_function_ref' => $sign_function->id(),
      'line_number' => $line_number,
      'phone' => $phone,
    ];
    if ($label) {
      $properties['label_ref'] = $label->id();
    }
    $entities = $storage->loadByProperties($properties);

    if (!empty($entities)) {
      return reset($entities);
    }

    // Create a new one.
    $code = 'si_' . $page->id() . '_' . ($label ? $label->id() : 'nolabel') . '_' . $sign_function->id() . '_' . microtime(TRUE);
    $values = [
      'sign_interpretation_code' => substr(hash('sha256', $code), 0, 20),
      'annotation_page_ref' => $page->id(),
      'sign_function_ref' => $sign_function->id(),
      'line_number' => $line_number,
      'phone' => $phone,
      'note' => $note,
      'langcode' => $langcode,
      'label_ref' => $label ? $label->id() : NULL,
    ];

    $entity = $storage->create($values);
    $entity->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_sign_interpretation', 'id' => $entity->id()];
    return $entity;
  }

  /**
   * Finds or creates a WdbWordUnit entity.
   *
   * If an existing unit is found, it appends the current page to its list
   * of occurrences.
   *
   * @param \Drupal\wdb_core\Entity\WdbSource $source
   *   The parent source entity.
   * @param \Drupal\wdb_core\Entity\WdbAnnotationPage $page
   *   The current annotation page.
   * @param int $word_unit_id_from_tsv
   *   The word unit ID from the source file.
   * @param \Drupal\wdb_core\Entity\WdbWordMeaning $word_meaning
   *   The associated word meaning.
   * @param string $realized_form
   *   The realized form of the word.
   * @param float $word_seq
   *   The word sequence number.
   * @param array $grammar_refs
   *   An array of grammar-related term references.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbWordUnit|null
   *   The word unit entity.
   */
  private function findOrCreateWdbWordUnit(WdbSource $source, WdbAnnotationPage $page, int $word_unit_id_from_tsv, WdbWordMeaning $word_meaning, string $realized_form, float $word_seq, array $grammar_refs, string $langcode, array &$context): ?WdbWordUnit {
    $storage = $this->entityTypeManager->getStorage('wdb_word_unit');
    $original_id = $source->get('source_identifier')->value . '_' . $word_unit_id_from_tsv;
    $entities = $storage->loadByProperties(['original_word_unit_identifier' => $original_id, 'langcode' => $langcode]);

    if (!empty($entities)) {
      $entity = reset($entities);
      $existing_page_refs = array_column($entity->get('annotation_page_refs')->getValue(), 'target_id');
      if (!in_array($page->id(), $existing_page_refs)) {
        $entity->get('annotation_page_refs')->appendItem($page->id());
        $entity->save();
      }
      return $entity;
    }

    $values = [
      'original_word_unit_identifier' => $original_id,
      'source_ref' => $source->id(),
      'annotation_page_refs' => [$page->id()],
      'word_meaning_ref' => $word_meaning->id(),
      'realized_form' => $realized_form,
      'word_sequence' => $word_seq,
      'langcode' => $langcode,
    ];
    $entity = $storage->create(array_merge($values, $grammar_refs));
    $entity->save();

    $context['results']['created_entities'][] = ['type' => 'wdb_word_unit', 'id' => $entity->id()];
    return $entity;
  }

  /**
   * Finds or creates a WdbWordMap entity.
   *
   * @param \Drupal\wdb_core\Entity\WdbSignInterpretation $si
   *   The sign interpretation entity.
   * @param \Drupal\wdb_core\Entity\WdbWordUnit $wu
   *   The word unit entity.
   * @param float $sign_seq
   *   The sign sequence number.
   * @param array $context
   *   The batch API context array.
   *
   * @return \Drupal\wdb_core\Entity\WdbWordMap
   *   The word map entity.
   */
  private function findOrCreateWdbWordMap(WdbSignInterpretation $si, WdbWordUnit $wu, float $sign_seq, array &$context): WdbWordMap {
    $storage = $this->entityTypeManager->getStorage('wdb_word_map');
    $entities = $storage->loadByProperties([
      'sign_interpretation_ref' => $si->id(),
      'word_unit_ref' => $wu->id(),
    ]);
    if (!empty($entities)) {
      return reset($entities);
    }

    $entity = $storage->create([
      'sign_interpretation_ref' => $si->id(),
      'word_unit_ref' => $wu->id(),
      'sign_sequence' => $sign_seq,
    ]);
    $entity->save();
    $context['results']['created_entities'][] = ['type' => 'wdb_word_map', 'id' => $entity->id()];
    return $entity;
  }

}
