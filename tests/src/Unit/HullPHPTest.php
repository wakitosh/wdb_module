<?php

/**
 * @file
 * Contains \Drupal\Tests\wdb_core\Unit\HullPHPTest.
 */

namespace Drupal\Tests\wdb_core\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\wdb_core\Lib\HullJsPhp\HullPHP;

/**
 * @coversDefaultClass \Drupal\wdb_core\Lib\HullJsPhp\HullPHP
 * @group wdb_core
 */
class HullPHPTest extends UnitTestCase {

  /**
   * Tests the calculate() method for a concave hull.
   *
   * This test uses a set of points that should form a C-shape, where a
   * convex hull would incorrectly connect the two ends.
   *
   * @covers ::calculate
   */
  public function testConcaveHullForCShape() {
    // 1. Prepare the input data. A set of points forming a C-shape.
    $pointset = [
      [0, 0], [1, 0], [2, 0], [3, 0], [4, 0], // Bottom edge
      [4, 1], [4, 2], [4, 3], [4, 4], // Right edge
      [3, 4], [2, 4], [1, 4], [0, 4], // Top edge
      [0, 3], [0, 2], [0, 1], // Left edge
    ];

    // 2. Define the expected result for a concave hull.
    // With an appropriate concavity, the hull should follow the C-shape.
    $expectedHull = [
      [0, 0], [1, 0], [2, 0], [3, 0], [4, 0],
      [4, 1], [4, 2], [4, 3], [4, 4],
      [3, 4], [2, 4], [1, 4], [0, 4],
      [0, 3], [0, 2], [0, 1],
      [0, 0], // Closed loop
    ];

    // 3. Call the method being tested with a suitable concavity value.
    // A lower concavity value allows for more "dents".
    $concavity = 2;
    $actualHull = HullPHP::calculate($pointset, $concavity);

    // 4. Assert that the actual result matches the expected result.
    $this->assertEquals($expectedHull, $actualHull);
  }

  /**
   * Tests that a high concavity value returns the convex hull.
   *
   * @covers ::calculate
   */
  public function testHighConcavityReturnsConvexHull() {
    // 1. Use the same C-shape points.
    $pointset = [
      [0, 0], [1, 0], [2, 0], [3, 0], [4, 0],
      [4, 1], [4, 2], [4, 3], [4, 4],
      [3, 4], [2, 4], [1, 4], [0, 4],
      [0, 3], [0, 2], [0, 1],
    ];

    // 2. Define the expected result for a CONVEX hull.
    // It should connect the outer points, ignoring the concave part.
    $expectedConvexHull = [
      [0, 0],
      [4, 0],
      [4, 4],
      [0, 4],
      [0, 0],
    ];

    // 3. Call the method with a very high concavity value.
    $concavity = 1000; // A large value should effectively produce a convex hull.
    $actualHull = HullPHP::calculate($pointset, $concavity);

    // 4. Assert.
    $this->assertEquals($expectedConvexHull, $actualHull);
  }

}

