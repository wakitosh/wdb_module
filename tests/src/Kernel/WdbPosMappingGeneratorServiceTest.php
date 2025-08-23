<?php

namespace Drupal\Tests\wdb_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * @coversDefaultClass \Drupal\wdb_core\Service\WdbPosMappingGeneratorService
 * @group wdb_core
 */
class WdbPosMappingGeneratorServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system', 'user', 'field', 'text', 'taxonomy', 'wdb_core',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\wdb_core\Service\WdbPosMappingGeneratorService
   */
  private $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('wdb_pos_mapping');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system']);

    // Create required vocabulary and a subset of target terms.
    Vocabulary::create(['vid' => 'lexical_category', 'name' => 'Lexical Category'])->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    foreach ([
      'common noun', 'proper noun', 'verbal', 'adjective', 'particle', 'symbol',
    ] as $name) {
      $term = $storage->create(['vid' => 'lexical_category', 'name' => $name]);
      $term->save();
    }

    $this->service = $this->container->get('wdb_core.pos_mapping_generator');
  }

  /**
   * @covers ::generateMappingsFromInternalMap
   */
  public function testGenerateMappings(): void {
    $result = $this->service->generateMappingsFromInternalMap();
    $this->assertArrayHasKey('created', $result);
    $this->assertArrayHasKey('skipped', $result);
    $this->assertGreaterThan(0, $result['created']);

    // Run again to ensure idempotency: created should be 0 on second run.
    $result2 = $this->service->generateMappingsFromInternalMap();
    $this->assertSame(0, $result2['created']);
  }

}
