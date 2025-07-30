<?php

/**
 * @file
 * Contains \Drupal\Tests\wdb_core\Unit\ConvexHullUtilTest.
 */

namespace Drupal\Tests\wdb_core\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\wdb_core\Lib\HullJsPhp\ConvexHullUtil;

/**
 * @coversDefaultClass \Drupal\wdb_core\Lib\HullJsPhp\ConvexHullUtil
 * @group wdb_core
 */
class ConvexHullUtilTest extends UnitTestCase {

  /**
   * Tests the convex() method with a simple square set of points.
   *
   * @covers ::convex
   */
  public function testSquareConvexHull() {
    // 1. Prepare the input data.
    // Note: The algorithm expects the data to be pre-sorted by x-coordinate.
    $pointset = [
      [0, 0],
      [0, 5],
      [5, 0],
      [5, 5],
    ];
    // Manually sort for the test.
    usort($pointset, function ($a, $b) {
      return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]);
    });

    // 2. Define the expected result.
    // The convex hull of a square is the square itself, closed by repeating the first point.
    $expectedHull = [
      [0, 0],
      [5, 0],
      [5, 5],
      [0, 5],
      [0, 0],
    ];

    // 3. Call the method being tested.
    $actualHull = ConvexHullUtil::convex($pointset);

    // 4. Assert that the actual result matches the expected result.
    $this->assertEquals($expectedHull, $actualHull);
  }

  /**
   * Tests the convex() method with a point inside the hull.
   *
   * @covers ::convex
   */
  public function testConvexHullWithInternalPoint() {
    // 1. Prepare input data with a point inside the square.
    $pointset = [
      [0, 0],
      [5, 0],
      [5, 5],
      [0, 5],
      [2, 2], // This point is inside the hull.
    ];
    usort($pointset, function ($a, $b) {
      return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]);
    });

    // 2. The expected result should ignore the internal point.
    $expectedHull = [
      [0, 0],
      [5, 0],
      [5, 5],
      [0, 5],
      [0, 0],
    ];

    // 3. Call the method.
    $actualHull = ConvexHullUtil::convex($pointset);

    // 4. Assert.
    $this->assertEquals($expectedHull, $actualHull);
  }

}
