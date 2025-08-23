<?php

namespace Drupal\Tests\wdb_core\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\wdb_core\Service\WdbTextGeneratorService
 * @group wdb_core
 */
class WdbTextGeneratorServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system', 'user', 'field', 'text', 'taxonomy', 'file', 'wdb_core',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\wdb_core\Service\WdbTextGeneratorService
   */
  private $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    $this->service = $this->container->get('wdb_core.text_generator');
  }

  /**
   * @covers ::getFullText
   */
  public function testPageNotFound(): void {
    $out = $this->service->getFullText('subsys', 'nope', 1);
    $this->assertIsArray($out);
    $this->assertArrayHasKey('error', $out);
    $this->assertSame('Page not found.', $out['error']);
  }

  /**
   * @covers ::getFullText
   */
  public function testNoWordUnits(): void {
    $source = $this->container->get('entity_type.manager')->getStorage('wdb_source')->create([
      'source_identifier' => 'src1',
      'displayname' => 'Src1',
    ]);
    $source->save();

    $page = $this->container->get('entity_type.manager')->getStorage('wdb_annotation_page')->create([
      'source_ref' => $source->id(),
      'page_number' => 5,
    ]);
    $page->save();

    $out = $this->service->getFullText('subsys', 'src1', 5);
    $this->assertArrayHasKey('html', $out);
    $this->assertStringContainsString('No text available', $out['html']);
  }

  /**
   * @covers ::getFullText
   */
  public function testFullFlow(): void {
    $etm = $this->container->get('entity_type.manager');

    $source = $etm->getStorage('wdb_source')->create([
      'source_identifier' => 'src2',
      'displayname' => 'Src2',
    ]);
    $source->save();

    $page = $etm->getStorage('wdb_annotation_page')->create([
      'source_ref' => $source->id(),
      'page_number' => 2,
    ]);
    $page->save();

    // Create two word units and maps to a sign with label that has points.
    $sign = $etm->getStorage('wdb_sign')->create(['sign_code' => 'X']);
    $sign->save();
    $sf = $etm->getStorage('wdb_sign_function')->create(['sign_ref' => $sign->id(), 'function_name' => 'phonogram']);
    $sf->save();

    $label = $etm->getStorage('wdb_label')->create([
      'annotation_page_ref' => $page->id(),
      'label_name' => 'L',
      'polygon_points' => ['10,10', '20,10', '20,20', '10,20'],
    ]);
    $label->save();

    $si = $etm->getStorage('wdb_sign_interpretation')->create([
      'annotation_page_ref' => $page->id(),
      'label_ref' => $label->id(),
      'sign_function_ref' => $sf->id(),
      'phone' => 'a',
    ]);
    $si->save();

    $wu1 = $etm->getStorage('wdb_word_unit')->create([
      'source_ref' => $source->id(),
      'annotation_page_refs' => [$page->id()],
      'realized_form' => 'ab',
      'word_sequence' => 1.0,
      'original_word_unit_identifier' => 'src2_2_1',
    ]);
    $wu1->save();
    $map1 = $etm->getStorage('wdb_word_map')->create([
      'word_unit_ref' => $wu1->id(),
      'sign_interpretation_ref' => $si->id(),
      'sign_sequence' => 1.0,
    ]);
    $map1->save();

    $wu2 = $etm->getStorage('wdb_word_unit')->create([
      'source_ref' => $source->id(),
      'annotation_page_refs' => [$page->id()],
      'realized_form' => 'cd',
      'word_sequence' => 2.0,
      'original_word_unit_identifier' => 'src2_2_2',
    ]);
    $wu2->save();
    $map2 = $etm->getStorage('wdb_word_map')->create([
      'word_unit_ref' => $wu2->id(),
      'sign_interpretation_ref' => $si->id(),
      'sign_sequence' => 2.0,
    ]);
    $map2->save();

    $out = $this->service->getFullText('subsys', 'src2', 2);
    $this->assertArrayHasKey('html', $out);
    $this->assertStringContainsString('class="word-unit', $out['html']);
    $this->assertStringContainsString('data-word-unit-original-id="src2_2_1"', $out['html']);
    $this->assertStringContainsString('data-word-points="[[&quot;10,10&quot;,&quot;20,10&quot;,&quot;20,20&quot;,&quot;10,20&quot;]]"', $out['html']);
    $this->assertStringContainsString('>ab<', $out['html']);
    $this->assertStringContainsString('>cd<', $out['html']);
    $this->assertArrayHasKey('title', $out);
  }

}
