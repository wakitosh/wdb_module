<?php

namespace Drupal\wdb_core\Lib\HullJsPhp;

/**
 * A PHP implementation of Intersect.js.
 *
 * Provides a utility for determining if two line segments intersect.
 */
class IntersectUtil {

  /**
   * Checks the orientation of an ordered triplet of points (the "ccw" function).
   *
   * The original JS logic was: val > 0 ? true : (val < 0 ? false : true)
   * This returns true if the turn is clockwise (val > 0) or if the points
   * are collinear (val == 0).
   *
   * @param float $x1
   *   X-coordinate of point 1.
   * @param float $y1
   *   Y-coordinate of point 1.
   * @param float $x2
   *   X-coordinate of point 2.
   * @param float $y2
   *   Y-coordinate of point 2.
   * @param float $x3
   *   X-coordinate of point 3.
   * @param float $y3
   *   Y-coordinate of point 3.
   *
   * @return bool
   *   True for clockwise or collinear, false for counter-clockwise.
   */
  private static function ccw(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): bool {
    $val = (($y3 - $y1) * ($x2 - $x1)) - (($y2 - $y1) * ($x3 - $x1));
    if ($val > 0) {
      return TRUE;
    }
    if ($val < 0) {
      return FALSE;
    }
    // Collinear.
    return TRUE;
  }

  /**
   * Determines if two line segments intersect.
   *
   * @param array $seg1
   *   The first segment, as [[x1, y1], [x2, y2]].
   * @param array $seg2
   *   The second segment, as [[x3, y3], [x4, y4]].
   *
   * @return bool
   *   TRUE if the segments intersect, FALSE otherwise.
   */
  public static function intersect(array $seg1, array $seg2): bool {
    $x1 = $seg1[0][0];
    $y1 = $seg1[0][1];
    $x2 = $seg1[1][0];
    $y2 = $seg1[1][1];
    $x3 = $seg2[0][0];
    $y3 = $seg2[0][1];
    $x4 = $seg2[1][0];
    $y4 = $seg2[1][1];

    return self::ccw($x1, $y1, $x3, $y3, $x4, $y4) !== self::ccw($x2, $y2, $x3, $y3, $x4, $y4) &&
               self::ccw($x1, $y1, $x2, $y2, $x3, $y3) !== self::ccw($x1, $y1, $x2, $y2, $x4, $y4);
  }

}
