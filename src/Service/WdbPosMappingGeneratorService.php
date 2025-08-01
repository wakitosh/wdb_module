<?php

namespace Drupal\wdb_core\Service;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service to generate POS mapping configuration entities automatically.
 *
 * This service uses a predefined internal map to create WdbPosMapping config
 * entities, linking UniDic POS strings to the system's lexical category terms.
 */
class WdbPosMappingGeneratorService {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected TransliterationInterface $transliteration;

  /**
   * A map of UniDic POS patterns to WDB lexical category term names.
   *
   * This constant is the single source of truth for the mappings.
   * The matching process is performed in the order defined here.
   * The keys are UniDic POS patterns (e.g., "名詞-普通名詞-一般-*").
   * The values are the English names of the target taxonomy terms in the
   * 'lexical_category' vocabulary.
   */
  private const UNIDIC_TO_WDB_MAP = [
    // --- Noun (Nominal) ---
    '名詞-固有名詞-人名-姓' => 'personal',
    '名詞-固有名詞-人名-名' => 'personal',
    '名詞-固有名詞-人名-一般' => 'personal',
    '名詞-固有名詞-地名-国' => 'place',
    '名詞-固有名詞-地名-一般' => 'place',
    '名詞-固有名詞-一般-*' => 'proper noun',
    '名詞-普通名詞-サ変可能-*' => 'common noun',
    '名詞-普通名詞-サ変形状詞可能-*' => 'common noun',
    '名詞-普通名詞-形状詞可能-*' => 'common noun',
    '名詞-普通名詞-助数詞可能-*' => 'common noun',
    '名詞-普通名詞-副詞可能-*' => 'common noun',
    '名詞-普通名詞-一般-*' => 'common noun',
    '名詞-数詞-*-*' => 'numeral',
    '名詞-助動詞語幹-*-*' => 'nominal',
    // --- Verb (Verbal) ---
    '動詞-非自立可能-*-*' => 'verbal',
    '動詞-一般-*-*' => 'verbal',
    // --- Adjective ---
    '形容詞-非自立可能-*-*' => 'adjective',
    '形容詞-一般-*-*' => 'adjective',
    // --- Other POS ---
    '代名詞-*-*-*' => 'pronominal',
    '副詞-*-*-*' => 'adverb',
    '形状詞-タリ-*-*' => 'adjectival verb',
    '形状詞-一般-*-*' => 'adjectival verb',
    '形状詞-助動詞語幹-*-*' => 'adjectival verb',
    '連体詞-*-*-*' => 'pre-noun adjectival',
    '接続詞-*-*-*' => 'conjunction',
    '感動詞-フィラー-*-*' => 'interjection',
    '感動詞-一般-*-*' => 'interjection',
    '助動詞-*-*-*' => 'auxiliary verb',
    // --- Particle ---
    '助詞-格助詞-*-*' => 'case marker',
    '助詞-接続助詞-*-*' => 'conjunctive particle',
    '助詞-終助詞-*-*' => 'sentence ending particle',
    '助詞-副助詞-*-*' => 'adverbial particle',
    '助詞-係助詞-*-*' => 'adverbial particle',
    '助詞-準体助詞-*-*' => 'phrasal particle',
    '助詞-*-*-*' => 'particle',
    // --- Affix ---
    '接頭辞-*-*-*' => 'prefix',
    '接尾辞-形状詞的-*-*' => 'suffix',
    '接尾辞-形容詞的-*-*' => 'suffix',
    '接尾辞-動詞的-*-*' => 'suffix',
    '接尾辞-名詞的-サ変可能-*' => 'suffix',
    '接尾辞-名詞的-一般-*' => 'suffix',
    '接尾辞-名詞的-助数詞-*' => 'suffix',
    '接尾辞-名詞的-副詞可能-*' => 'suffix',
    // --- Symbol ---
    '補助記号-括弧開-*-*' => 'parenthese',
    '補助記号-括弧閉-*-*' => 'parenthese',
    '補助記号-句点-*-*' => 'punctuation mark',
    '補助記号-読点-*-*' => 'reading mark',
    '補助記号-ＡＡ-顔文字-*' => 'symbol',
    '補助記号-ＡＡ-一般-*' => 'symbol',
    '補助記号-一般-*-*' => 'symbol',
    '記号-文字-*-*' => 'character',
    '記号-一般-*-*' => 'symbol',
    '空白-*-*-*' => 'symbol',
  ];

  /**
   * Constructs a new WdbPosMappingGeneratorService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TransliterationInterface $transliteration) {
    $this->entityTypeManager = $entity_type_manager;
    $this->transliteration = $transliteration;
  }

  /**
   * Generates mapping entities from the internally defined map.
   *
   * This method iterates through the UNIDIC_TO_WDB_MAP constant, creating a
   * new WdbPosMapping config entity for each entry if it does not already exist.
   *
   * @return array
   *   An array containing the results of the operation (created, skipped, etc.).
   */
  public function generateMappingsFromInternalMap(): array {
    $results = ['created' => 0, 'skipped' => 0, 'skipped_list' => []];

    // Pre-load all lexical category terms for efficient lookup.
    $wdb_terms_by_name = [];
    try {
      $lc_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'lexical_category']);
      foreach ($lc_terms as $term) {
        $wdb_terms_by_name[$term->getName()] = $term->id();
      }
    }
    catch (\Exception $e) {
      return ['error' => 'Could not load lexical_category vocabulary. Make sure it exists and has terms.'];
    }

    if (empty($wdb_terms_by_name)) {
      return ['error' => 'No terms found in the lexical_category vocabulary. Please run `drush wdb_core:create-default-terms` first.'];
    }

    $mapping_storage = $this->entityTypeManager->getStorage('wdb_pos_mapping');
    $weight = 0;

    foreach (self::UNIDIC_TO_WDB_MAP as $unidic_pattern => $wdb_term_name) {

      // Ensure the target WDB term exists before creating a mapping.
      if (isset($wdb_terms_by_name[$wdb_term_name])) {
        $target_tid = $wdb_terms_by_name[$wdb_term_name];

        // Generate a machine-safe ID from the UniDic pattern.
        $sanitized_pattern = $this->transliteration->transliterate($unidic_pattern, 'en', '_');
        $sanitized_pattern = str_replace('*', 'all', $sanitized_pattern);
        $machine_name_safe_part = preg_replace('/[^a-z0-9_]+/', '_', strtolower($sanitized_pattern));
        $machine_name_safe_part = preg_replace('/__+/', '_', $machine_name_safe_part);
        $machine_name = 'map_' . trim($machine_name_safe_part, '_');

        // Create the mapping entity only if it doesn't already exist.
        if ($mapping_storage->load($machine_name) === NULL) {
          $mapping_storage->create([
            'id' => $machine_name,
            'label' => $this->t('Mapping for @pos', ['@pos' => $unidic_pattern]),
            'source_pos_string' => $unidic_pattern,
            'target_lexical_category' => $target_tid,
            'weight' => $weight,
          ])->save();
          $results['created']++;
        }
      }
      else {
        // Log cases where the target term does not exist in the vocabulary.
        $results['skipped']++;
        $results['skipped_list'][] = $wdb_term_name;
      }
      $weight++;
    }
    $results['skipped_list'] = array_unique($results['skipped_list']);
    return $results;
  }

}
