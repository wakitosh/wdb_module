/**
 * @file
 * Implements vertical and horizontal resizing for viewer panels.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Drupal behavior to attach resizable functionality to panel dividers.
   */
  Drupal.behaviors.wdbResizablePanels = {
    attach: function (context, settings) {
      // --- Horizontal Resizing (Left/Right Panels) ---
      once('wdb-resizable-init', '#wdb-resizer', context).forEach(function (resizer) {

        const leftSide = resizer.previousElementSibling; // The viewer container
        const rightSide = resizer.nextElementSibling; // The annotation panel

        let x = 0;
        let rightWidth = 0;

        const mouseDownHandler = function (e) {
          x = e.clientX;
          rightWidth = rightSide.getBoundingClientRect().width;

          document.addEventListener('mousemove', mouseMoveHandler);
          document.addEventListener('mouseup', mouseUpHandler);

          // Change cursor and prevent text selection during drag.
          document.body.style.cursor = 'col-resize';
          document.body.style.userSelect = 'none';
        };

        const mouseMoveHandler = function (e) {
          const dx = e.clientX - x;
          const newRightWidth = rightWidth - dx;
          // Update the flex-basis to change the width.
          rightSide.style.flexBasis = `${newRightWidth}px`;
        };

        const mouseUpHandler = function () {
          document.removeEventListener('mousemove', mouseMoveHandler);
          document.removeEventListener('mouseup', mouseUpHandler);

          // Restore default cursor and text selection.
          document.body.style.removeProperty('cursor');
          document.body.style.removeProperty('user-select');
        };

        resizer.addEventListener('mousedown', mouseDownHandler);
      });

      // --- Vertical Resizing (Top/Bottom Panels) ---
      once('wdb-resizable-init-h', '#wdb-resizer-horizontal', context).forEach(function (resizer) {
        const topSide = resizer.previousElementSibling; // The top panel
        const bottomSide = resizer.nextElementSibling; // The bottom panel
        const parentContainer = resizer.parentElement;

        let y = 0;
        let topHeight = 0;
        let parentHeight = 0;
        const resizerHeight = resizer.getBoundingClientRect().height;

        const mouseDownHandler = function (e) {
          y = e.clientY;
          topHeight = topSide.getBoundingClientRect().height;
          parentHeight = parentContainer.getBoundingClientRect().height;

          document.addEventListener('mousemove', mouseMoveHandler);
          document.addEventListener('mouseup', mouseUpHandler);

          document.body.style.cursor = 'row-resize';
          document.body.style.userSelect = 'none';
        };

        const mouseMoveHandler = function (e) {
          const dy = e.clientY - y;
          const newTopHeight = topHeight + dy;
          const newBottomHeight = parentHeight - newTopHeight - resizerHeight;

          // Set minimum heights to prevent panels from collapsing.
          if (newTopHeight > 50 && newBottomHeight > 50) {
            // Set flex-basis for both panels simultaneously to ensure
            // a 1:1 drag response.
            topSide.style.flexBasis = `${newTopHeight}px`;
            bottomSide.style.flexBasis = `${newBottomHeight}px`;
          }
        };

        const mouseUpHandler = function () {
          document.removeEventListener('mousemove', mouseMoveHandler);
          document.removeEventListener('mouseup', mouseUpHandler);

          document.body.style.removeProperty('cursor');
          document.body.style.removeProperty('user-select');
        };

        resizer.addEventListener('mousedown', mouseDownHandler);
      });
    },
  };

})(Drupal, once);
