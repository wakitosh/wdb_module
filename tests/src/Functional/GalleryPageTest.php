<?php

/**
 * @file
 * Contains \Drupal\Tests\wdb_core\Functional\GalleryPageTest.
 */

namespace Drupal\Tests\wdb_core\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests the display of the WDB gallery page.
 *
 * @group wdb_core
 */
class GalleryPageTest extends BrowserTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = ['wdb_core', 'taxonomy'];

  /**
   * The theme to install as the default for testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to view the gallery.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The 'subsystem' vocabulary is installed via the module's configuration,
    // so we don't need to create it here.

    // 1. Create a user with the required permission.
    $this->webUser = $this->drupalCreateUser(['view wdb gallery pages']);
    $this->drupalLogin($this->webUser);

    // 2. Create sample data required for the gallery page.
    $subsystem_term = Term::create([
      'vid' => 'subsystem',
      'name' => 'hdb',
    ]);
    $subsystem_term->save();

    $source = $this->container->get('entity_type.manager')
      ->getStorage('wdb_source')
      ->create([
        'source_identifier' => 'test_source_01',
        'displayname' => 'Test Source',
        'subsystem_tags' => [$subsystem_term->id()],
        'pages' => 1,
      ]);
    $source->save();

    $page = $this->container->get('entity_type.manager')
      ->getStorage('wdb_annotation_page')
      ->create([
        'source_ref' => $source->id(),
        'page_number' => 1,
      ]);
    $page->save();
  }

  /**
   * Tests that the gallery page loads correctly and contains the viewer.
   */
  public function testGalleryPageDisplay() {
    // 1. Navigate the virtual browser to the gallery page URL.
    $this->drupalGet('/wdb/hdb/gallery/test_source_01/1');

    // 2. Assert that the page was loaded successfully (HTTP 200).
    $this->assertSession()->statusCodeEquals(200);

    // 3. Assert that the correct page title is displayed.
    // We use assertStringContainsString to get more detailed debug output on failure.
    $expected_title = 'Test Source - Page 1';
    $actual_text = $this->getSession()->getPage()->getText();
    $this->assertStringContainsString($expected_title, $actual_text, "The expected title was not found on the page.");

    // 4. Assert that the OpenSeadragon viewer container element exists on the page.
    $this->assertSession()->elementExists('css', '#openseadragon-viewer');
  }

}
