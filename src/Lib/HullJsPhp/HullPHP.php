<?php

namespace Drupal\wdb_core\Lib\HullJsPhp;

/**
 * A PHP implementation of Hull.js.
 *
 * This is the main class for calculating the concave hull of a set of points.
 */
class HullPHP {

  // Math.cos(90 / (180 / Math.PI)) = Math.cos(Math.PI / 2) = 0.0
  // This constant is used to check if an angle is less than 90 degrees
  // (i.e., if its cosine is positive).
  private const MAX_CONCAVE_ANGLE_COS = 0.0;

  // The maximum percentage of the total area to search for a midpoint.
  private const MAX_SEARCH_BBOX_SIZE_PERCENT = 0.6;

  /**
   * Filters duplicate points from a sorted point set.
   *
   * @param array $pointset
   *   An array of points, pre-sorted by x-coordinate.
   *
   * @return array
   *   An array of unique points.
   */
  private static function filterDuplicates(array $pointset): array {
    if (empty($pointset)) {
      return [];
    }
    $unique = [$pointset[0]];
    $lastPoint = $pointset[0];
    for ($i = 1; $i < count($pointset); $i++) {
      $currentPoint = $pointset[$i];
      if ($lastPoint[0] !== $currentPoint[0] || $lastPoint[1] !== $currentPoint[1]) {
        $unique[] = $currentPoint;
      }
      $lastPoint = $currentPoint;
    }
    return $unique;
  }

  /**
   * Sorts a point set in-place by x-coordinate, then by y-coordinate.
   *
   * @param array $pointset
   *   The array of points to sort.
   */
  private static function sortByX(array &$pointset): void {
    usort($pointset, function ($a, $b) {
        // Use the spaceship operator (PHP 7.0+).
        return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]);
    });
  }

  /**
   * Calculates the squared length of a line segment.
   *
   * @param array $a
   *   The first point [x, y].
   * @param array $b
   *   The second point [x, y].
   *
   * @return float
   *   The squared length.
   */
  private static function sqLength(array $a, array $b): float {
    return pow($b[0] - $a[0], 2) + pow($b[1] - $a[1], 2);
  }

  /**
   * Calculates the cosine of the angle AOB formed by three points.
   *
   * @param array $o
   *   The origin point [x, y].
   * @param array $a
   *   The first point [x, y].
   * @param array $b
   *   The second point [x, y].
   *
   * @return float
   *   The cosine of the angle.
   */
  private static function cosAngle(array $o, array $a, array $b): float {
    $aShifted = [$a[0] - $o[0], $a[1] - $o[1]];
    $bShifted = [$b[0] - $o[0], $b[1] - $o[1]];
    $sqALen = self::sqLength($o, $a);
    $sqBLen = self::sqLength($o, $b);
    $dot = $aShifted[0] * $bShifted[0] + $aShifted[1] * $bShifted[1];

    if ($sqALen == 0 || $sqBLen == 0) {
      // Edge case for overlapping points.
      return 1.0;
    }

    return $dot / sqrt($sqALen * $sqBLen);
  }

  /**
   * Checks if a line segment intersects with any edge of a polygon.
   *
   * @param array $segment
   *   The line segment [[x1, y1], [x2, y2]].
   * @param array $polygonEdges
   *   An array of points forming the polygon edges.
   *
   * @return bool
   *   TRUE if the segment intersects an edge, FALSE otherwise.
   */
  private static function checkIntersect(array $segment, array $polygonEdges): bool {
    for ($i = 0; $i < count($polygonEdges) - 1; $i++) {
      $edge = [$polygonEdges[$i], $polygonEdges[$i + 1]];
      // Skip if the segment's start point is an endpoint of the edge.
      if (($segment[0][0] === $edge[0][0] && $segment[0][1] === $edge[0][1]) ||
            ($segment[0][0] === $edge[1][0] && $segment[0][1] === $edge[1][1])) {
        continue;
      }
      // Skip if the segment's end point (the candidate point) is an endpoint of the edge.
      if (($segment[1][0] === $edge[0][0] && $segment[1][1] === $edge[0][1]) ||
            ($segment[1][0] === $edge[1][0] && $segment[1][1] === $edge[1][1])) {
        continue;
      }
      if (IntersectUtil::intersect($segment, $edge)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Calculates the width and height of the area occupied by a point set.
   *
   * @param array $pointset
   *   An array of points.
   *
   * @return array
   *   An array containing [width, height].
   */
  private static function occupiedArea(array $pointset): array {
    if (empty($pointset)) {
      return [0, 0];
    }
    $minX = $pointset[0][0];
    $minY = $pointset[0][1];
    $maxX = $pointset[0][0];
    $maxY = $pointset[0][1];

    foreach ($pointset as $point) {
      if ($point[0] < $minX) {
        $minX = $point[0];
      }
      if ($point[1] < $minY) {
        $minY = $point[1];
      }
      if ($point[0] > $maxX) {
        $maxX = $point[0];
      }
      if ($point[1] > $maxY) {
        $maxY = $point[1];
      }
    }
    return [$maxX - $minX, $maxY - $minY];
  }

  /**
   * Creates a bounding box around a line segment.
   *
   * @param array $edge
   *   The edge [[x1, y1], [x2, y2]].
   *
   * @return array
   *   The bounding box [minX, minY, maxX, maxY].
   */
  private static function bBoxAround(array $edge): array {
    return [
    // Left.
      min($edge[0][0], $edge[1][0]),
    // Top.
      min($edge[0][1], $edge[1][1]),
    // Right.
      max($edge[0][0], $edge[1][0]),
    // Bottom.
      max($edge[0][1], $edge[1][1]),
    ];
  }

  /**
   * Finds the best midpoint to insert into an edge to make it concave.
   *
   * @param array $edge
   *   The edge to consider.
   * @param array $innerPoints
   *   The points inside the convex hull.
   * @param array $convexHull
   *   The current hull polygon.
   *
   * @return array|null
   *   The best point to insert, or NULL if none is found.
   */
  private static function findMidPoint(array $edge, array $innerPoints, array $convexHull): ?array {
    $bestPoint = NULL;
    $angle1Cos = self::MAX_CONCAVE_ANGLE_COS;
    $angle2Cos = self::MAX_CONCAVE_ANGLE_COS;

    foreach ($innerPoints as $currentPoint) {
      $cos1 = self::cosAngle($edge[0], $edge[1], $currentPoint);
      $cos2 = self::cosAngle($edge[1], $edge[0], $currentPoint);

      // Check if both angles are less than 90 degrees and there are no intersections.
      if ($cos1 > $angle1Cos && $cos2 > $angle2Cos &&
            !self::checkIntersect([$edge[0], $currentPoint], $convexHull) &&
            !self::checkIntersect([$edge[1], $currentPoint], $convexHull)) {

        $angle1Cos = $cos1;
        $angle2Cos = $cos2;
        $bestPoint = $currentPoint;
      }
    }
    return $bestPoint;
  }

  /**
   * Recursively makes the hull concave.
   *
   * @param array $hull
   *   The current hull (passed by reference).
   * @param float $maxSqEdgeLen
   *   The maximum squared edge length.
   * @param array $maxSearchArea
   *   The maximum search area [width, height].
   * @param \Drupal\wdb_core\Lib\HullJsPhp\Grid $grid
   *   The grid of inner points.
   * @param array $edgeSkipList
   *   A list of edges to skip (passed by reference).
   *
   * @return array
   *   The modified hull.
   */
  private static function makeConcave(array &$hull, float $maxSqEdgeLen, array $maxSearchArea, Grid $grid, array &$edgeSkipList): array {
    $midPointInserted = FALSE;

    for ($i = 0; $i < count($hull) - 1; $i++) {
      $edge = [$hull[$i], $hull[$i + 1]];
      $keyInSkipList = $edge[0][0] . ',' . $edge[0][1] . ',' . $edge[1][0] . ',' . $edge[1][1];

      if (self::sqLength($edge[0], $edge[1]) < $maxSqEdgeLen || isset($edgeSkipList[$keyInSkipList])) {
        continue;
      }

      $scaleFactor = 0;
      $bBox = self::bBoxAround($edge);
      $candidateMidPoint = NULL;
      do {
        $extendedBBox = $grid->extendBbox($bBox, $scaleFactor);
        $bBoxWidth = $extendedBBox[2] - $extendedBBox[0];
        $bBoxHeight = $extendedBBox[3] - $extendedBBox[1];

        $pointsInSearchArea = $grid->rangePoints($extendedBBox);
        $candidateMidPoint = self::findMidPoint($edge, $pointsInSearchArea, $hull);

        $scaleFactor++;
        // The search bounding box is progressively extended.
        $bBox = $extendedBBox;

      } while ($candidateMidPoint === NULL && ($maxSearchArea[0] > $bBoxWidth || $maxSearchArea[1] > $bBoxHeight));

      if ($bBoxWidth >= $maxSearchArea[0] && $bBoxHeight >= $maxSearchArea[1]) {
        $edgeSkipList[$keyInSkipList] = TRUE;
      }

      if ($candidateMidPoint !== NULL) {
        // Insert the candidate point into the hull.
        array_splice($hull, $i + 1, 0, [$candidateMidPoint]);
        $grid->removePoint($candidateMidPoint);
        $midPointInserted = TRUE;
      }
    }

    // If a point was inserted, recurse to continue refining the hull.
    if ($midPointInserted) {
      return self::makeConcave($hull, $maxSqEdgeLen, $maxSearchArea, $grid, $edgeSkipList);
    }

    return $hull;
  }

  /**
   * Calculates the concave hull of a set of points.
   *
   * @param array $pointset
   *   The input point set. Each point can be an object or an array.
   *   E.g., [ ['x'=>0,'y'=>0], ['x'=>1,'y'=>1] ] or [ [0,0], [1,1] ].
   * @param float|null $concavity
   *   The concavity parameter. A higher value results in a hull closer to the
   *   convex hull. It acts as a threshold for the maximum edge length.
   * @param array|null $format
   *   Specifies the format of the input points, e.g., ['x', 'y'] or [0, 1].
   *   If null, the input is assumed to be in [x, y] array format.
   *
   * @return array
   *   An array of points forming the concave hull. The format will match the
   *   input format if specified, otherwise it will be an array of [x, y] arrays.
   */
  public static function calculate(array $pointset, $concavity = NULL, ?array $format = NULL): array {
    // Convert input to [x, y] array format.
    $pointsXY = FormatUtil::toXy($pointset, $format);

    if (empty($pointsXY)) {
      return [];
    }

    // Sort by x-coordinate and remove duplicates.
    self::sortByX($pointsXY);
    $uniquePoints = self::filterDuplicates($pointsXY);

    // Handle cases with fewer than 3 unique points.
    if (count($uniquePoints) < 3) {
      $result = $uniquePoints;
      if (!empty($uniquePoints) && count($uniquePoints) < 3) {
        // Close the polygon by adding the start point to the end.
        if (count($result) > 0) {
          $result[] = $result[0];
        }
      }
      return $format ? FormatUtil::fromXy($result, $format) : $result;
    }

    // Calculate the convex hull, which is the base for the concave hull.
    $convexHullPolygon = ConvexHullUtil::convex($uniquePoints);

    // If no concavity is specified, or it's invalid, return the convex hull.
    if ($concavity === NULL ||
          (is_string($concavity) && strtolower($concavity) === 'infinity') ||
          !is_numeric($concavity) ||
          (float) $concavity <= 0 ||
          (float) $concavity >= 100000) {
      return $format ? FormatUtil::fromXy($convexHullPolygon, $format) : $convexHullPolygon;
    }

    $concavity_float = (float) $concavity;

    // Separate inner points from the convex hull points.
    $convexPointsForLookup = [];
    foreach ($convexHullPolygon as $cvPt) {
      if (is_array($cvPt) && isset($cvPt[0]) && isset($cvPt[1])) {
        $convexPointsForLookup[$cvPt[0] . ',' . $cvPt[1]] = TRUE;
      }
    }
    $innerPoints = array_filter($uniquePoints, function ($pt) use ($convexPointsForLookup) {
        return is_array($pt) && isset($pt[0]) && isset($pt[1]) && !isset($convexPointsForLookup[$pt[0] . ',' . $pt[1]]);
    });
    $innerPoints = array_values($innerPoints);

    $area = self::occupiedArea($uniquePoints);
    $maxSearchArea = [
      $area[0] * self::MAX_SEARCH_BBOX_SIZE_PERCENT,
      $area[1] * self::MAX_SEARCH_BBOX_SIZE_PERCENT,
    ];

    $cellSizeDivisor = ($area[0] * $area[1]);
    if ($cellSizeDivisor == 0) {
      $cellSizeDivisor = 1.0;
    }

    $numUniquePoints = count($uniquePoints);
    if ($numUniquePoints == 0) {
      $numUniquePoints = 1;
    }

    $cellSize = ceil(1.0 / ($numUniquePoints / $cellSizeDivisor));
    if ($cellSize <= 0) {
      $cellSize = 1.0;
    }

    $grid = new Grid($innerPoints, $cellSize);
    // Use an associative array to mimic the skip list.
    $edgeSkipList = [];

    // The initial hull for the concave calculation is the convex hull.
    $concaveHullResult = self::makeConcave($convexHullPolygon, pow($concavity_float, 2), $maxSearchArea, $grid, $edgeSkipList);

    return $format ? FormatUtil::fromXy($concaveHullResult, $format) : $concaveHullResult;
  }

}
