<?php

namespace Drupal\wdb_core\Lib\HullJsPhp;

/**
 * A PHP implementation of Convex.js.
 *
 * Provides a utility for calculating the convex hull of a set of points using
 * the Monotone Chain Algorithm.
 */
class ConvexHullUtil {

  /**
   * Calculates the cross product of three points.
   *
   * (a[0]-o[0])*(b[1]-o[1]) - (a[1]-o[1])*(b[0]-o[0])
   * If the result is positive, the turn O->A->B is counter-clockwise (left).
   * If negative, it's clockwise (right). If zero, the points are collinear.
   *
   * @param array $o
   *   The origin point [x, y].
   * @param array $a
   *   The first point [x, y].
   * @param array $b
   *   The second point [x, y].
   *
   * @return float
   *   The cross product.
   */
  private static function cross_product(array $o, array $a, array $b): float {
    return ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);
  }

  /**
   * Calculates the upper part of the convex hull chain.
   *
   * This function builds the upper chain from P0 to Pn-1 and excludes Pn-1.
   *
   * @param array $pointset
   *   An array of points, pre-sorted by x-coordinate.
   *
   * @return array
   *   The points forming the upper part of the hull.
   */
  private static function calculateChainPart1(array $pointset): array {
    $hullPart = [];
    foreach ($pointset as $point) {
      while (count($hullPart) >= 2 &&
               self::cross_product($hullPart[count($hullPart) - 2], $hullPart[count($hullPart) - 1], $point) <= 0) {
        array_pop($hullPart);
      }
      $hullPart[] = $point;
    }
    if (!empty($hullPart)) {
      // Exclude the endpoint of the chain (Pn-1).
      array_pop($hullPart);
    }
    return $hullPart;
  }

  /**
   * Calculates the lower part of the convex hull chain.
   *
   * This function reverses the point set and builds the lower chain from Pn-1
   * to P0, excluding P0.
   *
   * @param array $pointset
   *   An array of points, pre-sorted by x-coordinate.
   *
   * @return array
   *   The points forming the lower part of the hull.
   */
  private static function calculateChainPart2(array $pointset): array {
    // Create a new array [Pn-1, ..., P0].
    $reversedPointset = array_reverse($pointset);
    $hullPart = [];
    foreach ($reversedPointset as $point) {
      while (count($hullPart) >= 2 &&
               self::cross_product($hullPart[count($hullPart) - 2], $hullPart[count($hullPart) - 1], $point) <= 0) {
        array_pop($hullPart);
      }
      $hullPart[] = $point;
    }
    if (!empty($hullPart)) {
      // Exclude the endpoint of the chain (the original start point P0).
      array_pop($hullPart);
    }
    return $hullPart;
  }

  /**
   * Calculates the convex hull of a set of points.
   *
   * @param array $pointset_sorted_unique
   *   An array of points [[x,y], ...], pre-sorted by x-coordinate with
   *   duplicates removed.
   *
   * @return array
   *   An array of points forming the convex hull.
   */
  public static function convex(array $pointset_sorted_unique): array {
    if (empty($pointset_sorted_unique)) {
      return $pointset_sorted_unique;
    }

    $n = count($pointset_sorted_unique);

    if ($n < 3) {
      // For 1 or 2 points, the hull is the set of points itself.
      return $pointset_sorted_unique;
    }

    $upper_chain = self::calculateChainPart1($pointset_sorted_unique);
    $lower_chain = self::calculateChainPart2($pointset_sorted_unique);

    // Merge the upper chain first, then the lower chain.
    $hull = array_merge($upper_chain, $lower_chain);

    // Add the starting point of the original sorted set to close the polygon.
    // This ensures the hull starts and ends at P0.
    $hull[] = $pointset_sorted_unique[0];

    return $hull;
  }

}
