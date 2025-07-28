<?php

namespace Drupal\wdb_core\Lib\HullJsPhp;

/**
 * A PHP implementation of Format.js.
 *
 * Provides utilities for converting the format of point sets.
 */
class FormatUtil {

  /**
   * Converts a point set to the [x, y] format.
   *
   * @param array $pointset
   *   An array of points.
   * @param array|null $format
   *   An array defining the keys for x and y, e.g., ['x', 'y'] or [0, 1].
   *
   * @return array
   *   An array of points in the [x, y] format.
   */
  public static function toXy(array $pointset, ?array $format = NULL): array {
    if ($format === NULL) {
      // If no format is specified, return a shallow copy of the array,
      // similar to JavaScript's slice().
      return array_map(function ($p) {
        return $p;
      }, $pointset);
    }

    return array_map(function ($pt) use ($format) {
        $xKey = $format[0];
        $yKey = $format[1];
        $xVal = is_object($pt) ? $pt->{$xKey} : $pt[$xKey];
        $yVal = is_object($pt) ? $pt->{$yKey} : $pt[$yKey];
        return [$xVal, $yVal];
    }, $pointset);
  }

  /**
   * Converts an [x, y] formatted point set to an array of objects.
   *
   * @param array $pointset
   *   An array of points in the [x, y] format.
   * @param array|null $format
   *   An array defining the keys for x and y, e.g., ['x', 'y'].
   *
   * @return array
   *   An array of objects in the specified format.
   */
  public static function fromXy(array $pointset, ?array $format = NULL): array {
    if ($format === NULL) {
      return array_map(function ($p) {
        return $p;
      }, $pointset);
    }

    // Since the original JS created objects like `const o = {}; o.x = ...`,
    // we create stdClass objects in PHP to replicate that behavior.
    return array_map(function ($pt) use ($format) {
        $obj = new \stdClass();
        $obj->{$format[0]} = $pt[0];
        $obj->{$format[1]} = $pt[1];
        return $obj;
    }, $pointset);
  }

}
