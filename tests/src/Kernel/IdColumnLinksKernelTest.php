<?php

namespace Drupal\Tests\wdb_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\wdb_core\Entity\WdbSign;

/**
 * Tests that the ID column in list builders links to the canonical page.
 *
 * @group wdb_core
 */
class IdColumnLinksKernelTest extends KernelTestBase {

  /**
   * Modules to enable for this kernel test.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'language',
    'wdb_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install schema for the entity under test.
    $this->installEntitySchema('wdb_sign');
    // Needed for auto-increment IDs on some drivers.
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Ensure the ID column is rendered as a link to the canonical route.
   */
  public function testIdColumnIsLinkToCanonical(): void {
    // Create a simple Sign entity (minimal required fields).
    /** @var \Drupal\wdb_core\Entity\WdbSign $sign */
    $sign = WdbSign::create([
      'sign_code' => 'TEST_SIGN',
      'langcode' => 'en',
    ]);
    $sign->save();

    // Get the list builder and build the row for this entity.
    /** @var \Drupal\Core\Entity\EntityListBuilder $list_builder */
    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('wdb_sign');
    $row = $list_builder->buildRow($sign);

  $this->assertArrayHasKey('id', $row, 'Row contains id column.');
  $id_cell = $row['id'];
  $this->assertIsArray($id_cell, 'ID cell is a table cell array.');
  $this->assertArrayHasKey('data', $id_cell, 'ID cell contains renderable data.');
  $link = $id_cell['data'];
  $this->assertSame('link', $link['#type'], 'ID cell is rendered as a link.');
  $this->assertSame((string) $sign->id(), $link['#title'], 'Link title equals the numeric ID.');
  $this->assertTrue(method_exists($link['#url'], 'getRouteName'), 'URL object is present.');
  $this->assertSame('entity.wdb_sign.canonical', $link['#url']->getRouteName(), 'Link points to canonical route.');
  }

}
