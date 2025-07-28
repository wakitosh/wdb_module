<?php

namespace Drupal\wdb_core\Lib\HullJsPhp;

/**
 * A PHP implementation of Grid.js.
 *
 * This class manages a set of points in a grid structure to allow for
 * efficient spatial queries.
 */
class Grid {

  /**
   * The grid cells containing the points.
   *
   * @var array
   */
  private array $_cells = [];

  /**
   * The size of each grid cell.
   *
   * @var float
   */
  private float $_cellSize;

  /**
   * The inverse of the cell size, for faster calculations.
   *
   * @var float
   */
  private float $_reverseCellSize;

  /**
   * Constructs a new Grid object.
   *
   * @param array $points
   *   An array of points in [x, y] format.
   * @param float $cellSize
   *   The size of each grid cell.
   *
   * @throws \InvalidArgumentException
   * If the cell size is zero.
   */
  public function __construct(array $points, float $cellSize) {
    if ($cellSize == 0) {
      throw new \InvalidArgumentException("Cell size cannot be zero.");
    }
    $this->_cellSize = $cellSize;
    $this->_reverseCellSize = 1.0 / $cellSize;

    foreach ($points as $point) {
      $x = $this->coordToCellNum($point[0]);
      $y = $this->coordToCellNum($point[1]);

      if (!isset($this->_cells[$x])) {
        $this->_cells[$x] = [];
      }
      if (!isset($this->_cells[$x][$y])) {
        $this->_cells[$x][$y] = [];
      }
      $this->_cells[$x][$y][] = $point;
    }
  }

  /**
   * Gets all points in a specific grid cell.
   *
   * @param int $x
   *   The x-coordinate of the cell.
   * @param int $y
   *   The y-coordinate of the cell.
   *
   * @return array
   *   An array of points, or an empty array if the cell is empty.
   */
  public function cellPoints(int $x, int $y): array {
    return $this->_cells[$x][$y] ?? [];
  }

  /**
   * Gets all points within a given bounding box.
   *
   * @param array $bbox
   *   The bounding box as [minX, minY, maxX, maxY].
   *
   * @return array
   *   An array of points within the bounding box.
   */
  public function rangePoints(array $bbox): array {
    $tlCellX = $this->coordToCellNum($bbox[0]);
    $tlCellY = $this->coordToCellNum($bbox[1]);
    $brCellX = $this->coordToCellNum($bbox[2]);
    $brCellY = $this->coordToCellNum($bbox[3]);
    $points = [];

    for ($x = $tlCellX; $x <= $brCellX; $x++) {
      for ($y = $tlCellY; $y <= $brCellY; $y++) {
        $cellPts = $this->cellPoints($x, $y);
        if (!empty($cellPts)) {
          $points = array_merge($points, $cellPts);
        }
      }
    }
    return $points;
  }

  /**
   * Removes a specific point from the grid.
   *
   * @param array $pointToRemove
   *   The point to remove, in [x, y] format.
   *
   * @return array|null
   *   The modified cell array, or NULL if the cell was not found.
   */
  public function removePoint(array $pointToRemove): ?array {
    $cellX = $this->coordToCellNum($pointToRemove[0]);
    $cellY = $this->coordToCellNum($pointToRemove[1]);

    if (!isset($this->_cells[$cellX][$cellY])) {
      return NULL;
    }

    // Operate by reference.
    $cell = &$this->_cells[$cellX][$cellY];
    $pointIdxInCell = -1;

    foreach ($cell as $i => $p) {
      if ($p[0] === $pointToRemove[0] && $p[1] === $pointToRemove[1]) {
        $pointIdxInCell = $i;
        break;
      }
    }

    if ($pointIdxInCell !== -1) {
      array_splice($cell, $pointIdxInCell, 1);
    }
    // Return the (potentially) modified cell.
    return $cell;
  }

  /**
   * Truncates a float to an integer (rounds towards zero).
   *
   * @param float $val
   *   The float value.
   *
   * @return int
   *   The truncated integer.
   */
  private function trunc(float $val): int {
    return intval($val);
  }

  /**
   * Converts a coordinate to its corresponding cell number.
   *
   * @param float $coord
   *   The coordinate value (x or y).
   *
   * @return int
   *   The cell number.
   */
  private function coordToCellNum(float $coord): int {
    return $this->trunc($coord * $this->_reverseCellSize);
  }

  /**
   * Extends a bounding box by a given scale factor.
   *
   * @param array $bbox
   *   The bounding box as [minX, minY, maxX, maxY].
   * @param float $scaleFactor
   *   The factor by which to extend the bbox.
   *
   * @return array
   *   The extended bounding box.
   */
  public function extendBbox(array $bbox, float $scaleFactor): array {
    return [
      $bbox[0] - ($scaleFactor * $this->_cellSize),
      $bbox[1] - ($scaleFactor * $this->_cellSize),
      $bbox[2] + ($scaleFactor * $this->_cellSize),
      $bbox[3] + ($scaleFactor * $this->_cellSize),
    ];
  }

}
