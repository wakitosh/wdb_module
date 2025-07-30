<?php

/**
 * @file
 * Contains \Drupal\Tests\wdb_core\Kernel\WdbDataImporterServiceTest.
 */

namespace Drupal\Tests\wdb_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\wdb_core\Entity\WdbSource;
use Drupal\wdb_core\Entity\WdbAnnotationPage;
use Drupal\wdb_core\Entity\WdbLabel;

/**
 * @coversDefaultClass \Drupal\wdb_core\Service\WdbDataImporterService
 * @group wdb_core
 */
class WdbDataImporterServiceTest extends KernelTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'text',
    'taxonomy',
    'file',
    'wdb_core',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\wdb_core\Service\WdbDataImporterService
   */
  protected $importer;

  /**
   * The entity type manager.
   *
   * This property is provided by the parent KernelTestBase class after setUp.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the database schemas for required entities.
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
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

    // Create the 'lexical_category' vocabulary.
    Vocabulary::create(['vid' => 'lexical_category', 'name' => 'Lexical Category'])->save();

    // Get the services from the container.
    $this->importer = $this->container->get('wdb_core.data_importer');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests the processImportRow() method.
   *
   * @covers ::processImportRow
   */
  public function testProcessImportRow() {
    // 1. Prepare prerequisite entities.
    $source = $this->createWdbSource();
    $page = $this->createWdbAnnotationPage($source);
    $label = $this->createWdbLabel($page);

    // 2. Define a sample row of data from a TSV file.
    $rowData = [
      'source' => 'test_source_01',
      'page' => '1',
      'labelname' => '1-1',
      'image_identifier' => 'test/image.jpg',
      'sign' => 'い',
      'function' => 'phonogram',
      'phone' => 'i',
      'note' => 'Test note',
      'word_unit' => '1',
      'basic_form' => 'いづれ',
      'realized_form' => 'いつれ',
      'lexical_category_name' => 'demonstrative',
      'meaning' => '1',
      'explanation' => 'which',
    ];

    // 3. Define the initial batch context, ensuring all keys are set.
    $context = [
      'results' => [
        'created' => 0,
        'failed' => 0,
        'errors' => [],
        'warnings' => [],
        'created_entities' => [],
      ],
    ];

    // 4. Call the method being tested.
    $success = $this->importer->processImportRow($rowData, 'ja', 1.0, 1.0, $context);

    // 5. Assert that the operation was successful.
    $this->assertTrue($success);
    $this->assertEquals(1, $context['results']['created']);

    // 6. Verify that the entities were created correctly in the database.
    $storage = $this->entityTypeManager->getStorage('wdb_word_unit');
    $word_units = $storage->loadByProperties(['realized_form' => 'いつれ']);
    $this->assertCount(1, $word_units, 'A new word unit was created.');

    /** @var \Drupal\wdb_core\Entity\WdbWordUnit $word_unit */
    $word_unit = reset($word_units);
    $this->assertEquals($source->id(), $word_unit->get('source_ref')->target_id);

    // Verify the word meaning and word were created and linked correctly.
    $word_meaning = $word_unit->get('word_meaning_ref')->entity;
    $this->assertNotNull($word_meaning);
    $this->assertEquals('which', $word_meaning->get('explanation')->value);

    $word = $word_meaning->get('word_ref')->entity;
    $this->assertNotNull($word);
    $this->assertEquals('いづれ', $word->get('basic_form')->value);
  }

  // --- Helper methods to create entities for the test ---

  protected function createWdbSource() {
    $storage = $this->entityTypeManager->getStorage('wdb_source');
    $source = $storage->create([
      'source_identifier' => 'test_source_01',
      'displayname' => 'Test Source',
    ]);
    $source->save();
    return $source;
  }

  protected function createWdbAnnotationPage($source) {
    $storage = $this->entityTypeManager->getStorage('wdb_annotation_page');
    $page = $storage->create([
      'source_ref' => $source->id(),
      'page_number' => 1,
    ]);
    $page->save();
    return $page;
  }

  protected function createWdbLabel($page) {
    $storage = $this->entityTypeManager->getStorage('wdb_label');
    $label = $storage->create([
      'annotation_page_ref' => $page->id(),
      'label_name' => '1-1',
    ]);
    $label->save();
    return $label;
  }

}
