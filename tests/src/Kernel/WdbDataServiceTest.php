<?php

/**
 * @file
 * Contains \Drupal\Tests\wdb_core\Kernel\WdbDataServiceTest.
 */

namespace Drupal\Tests\wdb_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\wdb_core\Entity\WdbSource;
use Drupal\wdb_core\Entity\WdbAnnotationPage;
use Drupal\wdb_core\Entity\WdbWordUnit;
use Drupal\wdb_core\Entity\WdbSign;
use Drupal\wdb_core\Entity\WdbSignFunction;
use Drupal\wdb_core\Entity\WdbSignInterpretation;
use Drupal\wdb_core\Entity\WdbWordMap;
use Drupal\wdb_core\Entity\WdbLabel;

/**
 * @coversDefaultClass \Drupal\wdb_core\Service\WdbDataService
 * @group wdb_core
 */
class WdbDataServiceTest extends KernelTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'system', 'user', 'field', 'text', 'taxonomy', 'file', 'wdb_core',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\wdb_core\Service\WdbDataService
   */
  protected $dataService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install all WDB entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('wdb_source');
    $this->installEntitySchema('wdb_annotation_page');
    $this->installEntitySchema('wdb_label');
    $this->installEntitySchema('wdb_sign');
    $this->installEntitySchema('wdb_sign_function');
    $this->installEntitySchema('wdb_sign_interpretation');
    $this->installEntitySchema('wdb_word');
    $this->installEntitySchema('wdb_word_meaning');
    $this->installEntitySchema('wdb_word_unit');
    $this->installEntitySchema('wdb_word_map');

    // Create required vocabularies.
    Vocabulary::create(['vid' => 'lexical_category', 'name' => 'Lexical Category'])->save();
    Vocabulary::create(['vid' => 'subsystem', 'name' => 'Subsystem'])->save();

    // Get services from the container.
    $this->dataService = $this->container->get('wdb_core.data_service');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests the getDataForExport() method.
   *
   * @covers ::getDataForExport
   */
  public function testGetDataForExport() {
    // 1. Prepare a set of interconnected sample entities.
    $source = $this->createEntity('wdb_source', [
      'source_identifier' => 'test_source_01',
      'displayname' => 'Test Source',
    ]);

    $page = $this->createEntity('wdb_annotation_page', [
      'source_ref' => $source->id(),
      'page_number' => 1,
    ]);

    $word_unit = $this->createEntity('wdb_word_unit', [
      'source_ref' => $source->id(),
      'annotation_page_refs' => [$page->id()],
      'realized_form' => 'test_word',
      'word_sequence' => 1.0,
    ]);

    $sign = $this->createEntity('wdb_sign', ['sign_code' => 'X']);
    $sign_function = $this->createEntity('wdb_sign_function', ['sign_ref' => $sign->id()]);
    $sign_interpretation = $this->createEntity('wdb_sign_interpretation', [
      'annotation_page_ref' => $page->id(),
      'sign_function_ref' => $sign_function->id(),
    ]);

    $this->createEntity('wdb_word_map', [
      'word_unit_ref' => $word_unit->id(),
      'sign_interpretation_ref' => $sign_interpretation->id(),
    ]);

    // 2. Call the method being tested.
    $result = $this->dataService->getDataForExport('test_subsystem', 'test_source_01', 1);

    // 3. Assert that the returned data structure is correct.
    $this->assertIsArray($result);
    $this->assertInstanceOf(WdbSource::class, $result['source']);
    $this->assertEquals($source->id(), $result['source']->id());

    $this->assertInstanceOf(WdbAnnotationPage::class, $result['page']);
    $this->assertEquals($page->id(), $result['page']->id());

    $this->assertCount(1, $result['word_units']);
    $result_wu = $result['word_units'][0];

    $this->assertInstanceOf(WdbWordUnit::class, $result_wu['entity']);
    $this->assertEquals($word_unit->id(), $result_wu['entity']->id());

    $this->assertCount(1, $result_wu['sign_interpretations']);
    $result_si = $result_wu['sign_interpretations'][0];

    $this->assertInstanceOf(WdbSignInterpretation::class, $result_si);
    $this->assertEquals($sign_interpretation->id(), $result_si->id());
  }

  /**
   * Tests the getAnnotationDetails() method.
   *
   * @covers ::getAnnotationDetails
   */
  public function testGetAnnotationDetails() {
    // 1. Prepare prerequisite entities and configuration.
    $subsystem_term = Term::create(['vid' => 'subsystem', 'name' => 'hdb']);
    $subsystem_term->save();
    $this->config('wdb_core.subsystem.' . $subsystem_term->id())
      ->set('pageNavigation', 'left-to-right')
      ->save();

    $source = $this->createEntity('wdb_source', [
      'source_identifier' => 'test_source_02',
      'displayname' => 'Test Source for Details',
      'subsystem_tags' => [$subsystem_term->id()],
    ]);

    $page = $this->createEntity('wdb_annotation_page', [
      'source_ref' => $source->id(),
      'page_number' => 1,
    ]);

    $label = $this->createEntity('wdb_label', [
      'annotation_page_ref' => $page->id(),
      'label_name' => 'label-for-details',
    ]);

    $sign_interpretation = $this->createEntity('wdb_sign_interpretation', [
      'annotation_page_ref' => $page->id(),
      'label_ref' => $label->id(),
      'sign_function_ref' => $this->createDefaultSignFunction()->id(),
    ]);

    $word_unit = $this->createEntity('wdb_word_unit', [
      'source_ref' => $source->id(),
      'annotation_page_refs' => [$page->id()],
    ]);

    $this->createEntity('wdb_word_map', [
      'word_unit_ref' => $word_unit->id(),
      'sign_interpretation_ref' => $sign_interpretation->id(),
    ]);

    // 2. Call the method being tested.
    $result = $this->dataService->getAnnotationDetails($label, 'hdb');

    // 3. Assert the structure and content of the returned data.
    $this->assertIsArray($result);
    $this->assertArrayHasKey('title', $result);
    $this->assertEquals('Label: label-for-details', $result['title']);
    $this->assertArrayHasKey('retrieved_data', $result);
    $this->assertArrayHasKey('sign_interpretations', $result['retrieved_data']);
    $this->assertCount(1, $result['retrieved_data']['sign_interpretations']);

    $result_si = $result['retrieved_data']['sign_interpretations'][0];
    $this->assertArrayHasKey('associated_word_units', $result_si);
    $this->assertCount(1, $result_si['associated_word_units']);
    $this->assertEquals($word_unit->id(), $result_si['associated_word_units'][0]['entity']->id());
  }

  /**
   * Helper method to create an entity.
   *
   * @param string $entity_type
   * The entity type ID.
   * @param array $values
   * The entity values.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   * The created entity.
   */
  protected function createEntity($entity_type, array $values) {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    // Provide default values for required fields if not set.
    if ($entity_type === 'wdb_word_unit' && !isset($values['word_meaning_ref'])) {
      $values['word_meaning_ref'] = $this->createDefaultWordMeaning()->id();
    }
    $entity = $storage->create($values);
    $entity->save();
    return $entity;
  }

  /**
   * Helper to create a default word meaning for dependencies.
   */
  protected function createDefaultWordMeaning() {
    $lex_cat = Term::create(['vid' => 'lexical_category', 'name' => 'test_cat']);
    $lex_cat->save();
    $word = $this->createEntity('wdb_word', [
      'basic_form' => 'test',
      'lexical_category_ref' => $lex_cat->id(),
    ]);
    return $this->createEntity('wdb_word_meaning', [
      'word_ref' => $word->id(),
      'meaning_identifier' => 1,
    ]);
  }

  /**
   * Helper to create a default sign function for dependencies.
   */
  protected function createDefaultSignFunction() {
    $sign = $this->createEntity('wdb_sign', ['sign_code' => 'default_sign']);
    return $this->createEntity('wdb_sign_function', ['sign_ref' => $sign->id()]);
  }

}
