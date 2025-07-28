/**
 * @file
 * Dynamically adjusts the height of the main viewer container.
 *
 * This script calculates the available vertical space in the viewport and sets
 * the height of the main container accordingly, ensuring it fits neatly
 * between the header and the footer.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Adjusts the height of the main viewer container.
   */
  function adjustViewerHeight() {
    const mainContainer = document.querySelector('#wdb-main-container');
    // The selector for the footer element may need to be adjusted based on the theme.
    const footer = document.querySelector('footer');

    if (!mainContainer) {
      return;
    }

    // Get the total height of the viewport.
    const viewportHeight = window.innerHeight;

    // Get the Y coordinate of the top of the viewer container.
    const containerTopOffset = mainContainer.getBoundingClientRect().top;

    // Get the height of the footer (or 0 if it doesn't exist).
    const footerHeight = footer ? footer.offsetHeight : 0;

    // A margin to add at the bottom.
    const marginBottom = 20; // Provides a 20px margin.

    // Calculate the available height for the viewer container.
    const availableHeight = viewportHeight - containerTopOffset - footerHeight - marginBottom;

    // Set the calculated height on the container's style.
    if (availableHeight > 200) { // Set a minimum height of 200px.
      mainContainer.style.height = `${availableHeight}px`;
    }
  }

  /**
   * Drupal behavior to initialize the dynamic layout adjustments.
   */
  Drupal.behaviors.wdbDynamicLayout = {
    attach: function (context, settings) {
      // Use once() to ensure this behavior is attached only once.
      once('wdb-dynamic-layout-init', 'body', context).forEach(function () {
        // Run on initial page load.
        adjustViewerHeight();

        // Run whenever the window is resized (debounced for performance).
        window.addEventListener('resize', Drupal.debounce(adjustViewerHeight, 150, false));
      });
    }
  };

})(Drupal, once);
