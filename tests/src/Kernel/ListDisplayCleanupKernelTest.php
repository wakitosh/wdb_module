<?php

namespace Drupal\Tests\wdb_core\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies that deleted/non-existent fields don't appear in list headers.
 *
 * @group wdb_core
 */
class ListDisplayCleanupKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'taxonomy',
    'wdb_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Ensure the entity schema exists for building list headers safely.
    $this->installEntitySchema('wdb_source');
    // Some DB drivers require the sequences table.
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Ensure list headers skip ghost fields from saved config.
   */
  public function testHeaderSkipsDeletedFields(): void {
    // Simulate a previously-saved config that includes a deleted field.
    $this->container->get('config.factory')
      ->getEditable('wdb_core.list_display.wdb_source')
      ->set('fields', ['id', 'field_test2', 'source_identifier'])
      ->save();

    // Build header via the list builder.
    /** @var \Drupal\Core\Entity\EntityListBuilder $list_builder */
    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('wdb_source');
    $header = $list_builder->buildHeader();
    $keys = array_keys($header);

    // The real fields remain, the deleted field must not appear.
    $this->assertContains('id', $keys, 'Header contains ID');
    $this->assertContains('source_identifier', $keys, 'Header contains existing field');
    $this->assertNotContains('field_test2', $keys, 'Header does not contain deleted field');
  }

}
