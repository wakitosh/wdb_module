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

    // Skip while vertical drag or shortly after, to avoid fighting with user sizing
    try {
      if (mainContainer.dataset.vdrag === '1') return;
      const lockUntil = Number(mainContainer.dataset.lockUntil || '0');
      if (lockUntil && Date.now() < lockUntil) return;
    } catch (e) { }

    // Apply in all modes (split/stacked/drawer): compute the available space between header and footer
    const mode = mainContainer.dataset.mode;

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
    if (availableHeight > 200) {
      mainContainer.style.height = `${availableHeight}px`;
    } else {
      // If the computed space is too small, prefer letting CSS handle it
      try { mainContainer.style.removeProperty('height'); } catch (e) { }
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
        // Re-check shortly after to counter late layout changes (e.g., OSD init)
        setTimeout(adjustViewerHeight, 700);

        // Run whenever the window is resized (debounced for performance).
        window.addEventListener('resize', Drupal.debounce(adjustViewerHeight, 150, false));

        // Recompute when layout mode changes (split/stacked/drawer)
        const mc = document.getElementById('wdb-main-container');
        if (mc) {
          const mo = new MutationObserver(() => adjustViewerHeight());
          mo.observe(mc, { attributes: true, attributeFilter: ['data-mode'] });
        }

        // On orientation change, recompute after a short delay.
        window.addEventListener('orientationchange', () => {
          setTimeout(adjustViewerHeight, 300);
        });
      });
    }
  };

})(Drupal, once);
