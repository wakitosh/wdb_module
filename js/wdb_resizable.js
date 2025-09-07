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
      const STORAGE_KEY = 'wdb.viewer.layout';
      const loadState = () => {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch (e) { return {}; }
      };
      const saveState = (state) => {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) { /* noop */ }
      };
      const state = loadState();
      const mainContainer = document.getElementById('wdb-main-container');
      // No longer persist or apply saved mode; viewer decides by width.
      // --- Horizontal Resizing (Left/Right Panels) ---
      once('wdb-resizable-init', '#wdb-resizer', context).forEach(function (resizer) {

        const leftSide = resizer.previousElementSibling; // The viewer container
        const rightSide = resizer.nextElementSibling; // The annotation panel

        let x = 0;
        let rightWidth = 0;
        let dragOffsetX = 0; // pointer offset from resizer's left edge
        let overlay = null; // overlay to swallow pointer events during drag
        let prevOverflow = '';

        const mouseDownHandler = function (e) {
          e.preventDefault();
          const resizerRect = resizer.getBoundingClientRect();
          x = e.clientX;
          dragOffsetX = e.clientX - resizerRect.left;
          rightWidth = rightSide.getBoundingClientRect().width;

          document.addEventListener('mousemove', mouseMoveHandler);
          document.addEventListener('mouseup', mouseUpHandler);

          // Change cursor and prevent text selection during drag.
          document.body.style.cursor = 'col-resize';
          document.body.style.userSelect = 'none';
          // Disable transitions during drag for snappy response
          leftSide.style.transition = 'none';
          rightSide.style.transition = 'none';
          resizer.style.transition = 'none';
          // Prevent page scroll via overlay event suppression (no overflow toggle).

          // Insert a full-page transparent overlay to capture events while dragging.
          overlay = document.createElement('div');
          overlay.className = 'wdb-resize-overlay';
          overlay.style.position = 'fixed';
          overlay.style.inset = '0';
          overlay.style.zIndex = '2147483647';
          overlay.style.cursor = 'col-resize';
          overlay.style.background = 'transparent';
          overlay.style.pointerEvents = 'auto';
          overlay.style.touchAction = 'none';
          // Block wheel/touchmove to fully suppress scroll.
          const _prevent = (ev) => { ev.preventDefault(); };
          overlay.addEventListener('wheel', _prevent, { passive: false });
          overlay.addEventListener('touchmove', _prevent, { passive: false });
          document.body.appendChild(overlay);
        };

        const mouseMoveHandler = function (e) {
          // Keep the divider aligned to the pointer based on initial grab offset
          const containerRect = resizer.parentElement.getBoundingClientRect();
          const desiredDividerX = e.clientX - containerRect.left - dragOffsetX;
          const containerWidth = containerRect.width;
          // Right panel width equals remaining space to the right of divider
          let newRightWidth = containerWidth - desiredDividerX - resizerRectWidth();
          // Clamp to reasonable min widths (50px each)
          const minW = 50;
          const maxRight = containerWidth - resizerRectWidth() - minW;
          if (newRightWidth < minW) newRightWidth = minW;
          if (newRightWidth > maxRight) newRightWidth = maxRight;
          // Update the flex-basis to change the width.
          rightSide.style.flexBasis = `${newRightWidth}px`;
        };

        // Helper to read current resizer width (in case of CSS changes)
        function resizerRectWidth() {
          const r = resizer.getBoundingClientRect();
          return r.width || 0;
        }

        const mouseUpHandler = function () {
          document.removeEventListener('mousemove', mouseMoveHandler);
          document.removeEventListener('mouseup', mouseUpHandler);

          // Restore default cursor and text selection.
          document.body.style.removeProperty('cursor');
          document.body.style.removeProperty('user-select');
          // Restore transitions
          leftSide.style.removeProperty('transition');
          rightSide.style.removeProperty('transition');
          resizer.style.removeProperty('transition');
          // Restore overflow unchanged (we didn't toggle it).

          // Remove overlay if present.
          if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
          }
          overlay = null;

          // Persist split width (Split mode) as pixels of right panel.
          if (mainContainer && mainContainer.dataset.mode === 'split') {
            state.splitRightWidth = rightSide.getBoundingClientRect().width;
            saveState(state);
          }
        };

        resizer.addEventListener('mousedown', mouseDownHandler);

        // Restore saved width in split mode.
        if (mainContainer && mainContainer.dataset.mode === 'split' && state.splitRightWidth) {
          rightSide.style.flexBasis = `${state.splitRightWidth}px`;
        }
      });

      // --- Vertical Resizing (Top/Bottom Panels) ---
      once('wdb-resizable-init-h', '#wdb-resizer-horizontal', context).forEach(function (resizer) {
        const topSide = resizer.previousElementSibling; // The top panel
        const bottomSide = resizer.nextElementSibling; // The bottom panel
        const parentContainer = resizer.parentElement;

        let y = 0;
        let topHeight = 0;
        let startTopHeight = 0; // height snapshot at mousedown
        let parentHeight = 0;
        const resizerHeight = resizer.getBoundingClientRect().height;
        let dragOffsetY = 0; // pointer offset from resizer's top edge
        let overlay = null; // overlay to swallow pointer events during drag
        let prevOverflow = '';
        // Capture gaps around resizer to keep totals exact during delta updates
        let gapTotal = 0;
        let parentSnapHeight = 0;
        let resizerSnapHeight = 0;
        let startBottomHeight = 0;
        let minTopAtStart = 0;
        let minBottomAtStart = 0;
        // Snapshots for absolute computation
        let pointerOffsetToResizerTop = 0; // legacy (unused)
        let pointerOffsetToResizerCenter = 0; // align to visual divider center
        let gapAboveSnap = 0;
        let gapBelowSnap = 0;
        let topOffsetInsideParentStart = 0;
        // Track last computed heights during drag
        let lastTopHeight = null;
        let lastBottomHeight = null;

        // Debounced window resize emitter to notify dependent components (e.g., viewers)
        let _emitResizeTimer = null;
        function emitResizeSoon() {
          if (_emitResizeTimer) {
            clearTimeout(_emitResizeTimer);
          }
          _emitResizeTimer = setTimeout(() => {
            try { window.dispatchEvent(new Event('resize')); } catch (e) { }
            _emitResizeTimer = null;
          }, 32); // ~2 frames
        }

        // --- Helpers for robust availability calculation on restore/save ---
        function getRowGap() {
          const s = window.getComputedStyle(parentContainer);
          const g = parseFloat(s.rowGap || s.gap || '0');
          return Number.isFinite(g) ? g : 0;
        }
        function getAvailHeight() {
          // Use clientHeight (padding included, border excluded) for child layout area
          const client = parentContainer.clientHeight || parentContainer.getBoundingClientRect().height;
          const resH = resizer.offsetHeight || resizer.getBoundingClientRect().height || 0;
          const gap = getRowGap();
          const avail = client - resH - gap;
          return avail > 0 ? avail : 0;
        }
        function applyExplicitHeights(topH) {
          const minH = 50;
          const avail = getAvailHeight();
          const clampedTop = Math.max(minH, Math.min(Math.max(minH, avail - minH), topH));
          const bottomH = Math.max(minH, avail - clampedTop);
          topSide.style.height = `${clampedTop}px`;
          bottomSide.style.height = `${bottomH}px`;
          topSide.style.flex = '0 0 auto';
          bottomSide.style.flex = '0 0 auto';
          lastTopHeight = clampedTop;
          lastBottomHeight = bottomH;
          emitResizeSoon();
          return { top: clampedTop, bottom: bottomH };
        }
        function clearExplicitHeights() {
          // Let CSS control heights (e.g., drawer 60vh) by removing inline locks
          topSide.style.removeProperty('height');
          bottomSide.style.removeProperty('height');
          topSide.style.removeProperty('flex');
          bottomSide.style.removeProperty('flex');
          emitResizeSoon();
        }

        const mouseDownHandler = function (e) {
          e.preventDefault();
          const resizerRect = resizer.getBoundingClientRect();
          y = e.clientY;
          dragOffsetY = e.clientY - resizerRect.top; // kept for compatibility, not used in delta calc
          const topRect = topSide.getBoundingClientRect();
          const bottomRect = bottomSide.getBoundingClientRect();
          startTopHeight = topRect.height;
          startBottomHeight = bottomRect.height;
          parentSnapHeight = parentContainer.getBoundingClientRect().height;
          resizerSnapHeight = resizerRect.height;
          const parentRectStart = parentContainer.getBoundingClientRect();
          topOffsetInsideParentStart = topRect.top - parentRectStart.top;
          pointerOffsetToResizerTop = e.clientY - resizerRect.top;
          pointerOffsetToResizerCenter = e.clientY - (resizerRect.top + resizerRect.height / 2);

          // Measure gaps (flex gap or margins) currently present above/below resizer
          gapAboveSnap = Math.max(0, resizerRect.top - topRect.bottom);
          gapBelowSnap = Math.max(0, bottomRect.top - resizerRect.bottom);
          gapTotal = gapAboveSnap + gapBelowSnap;
          // Effective mins at drag start: don't force-grow the opposite side on first movement
          const minH = 50;
          minTopAtStart = Math.min(startTopHeight, minH);
          minBottomAtStart = Math.min(startBottomHeight, minH);

          // Freeze exact current sizes using height + flex lock to avoid any layout jump
          topSide.style.height = `${startTopHeight}px`;
          bottomSide.style.height = `${startBottomHeight}px`;
          topSide.style.flex = '0 0 auto';
          bottomSide.style.flex = '0 0 auto';
          lastTopHeight = startTopHeight;
          lastBottomHeight = startBottomHeight;

          // Disable transitions during drag for snappy response
          topSide.style.transition = 'none';
          bottomSide.style.transition = 'none';
          resizer.style.transition = 'none';

          document.addEventListener('mousemove', mouseMoveHandler);
          document.addEventListener('mouseup', mouseUpHandler);

          document.body.style.cursor = 'row-resize';
          document.body.style.userSelect = 'none';
          // Prevent page scroll via overlay event suppression (no overflow toggle).

          // Insert a full-page transparent overlay to capture events while dragging.
          overlay = document.createElement('div');
          overlay.className = 'wdb-resize-overlay';
          overlay.style.position = 'fixed';
          overlay.style.inset = '0';
          overlay.style.zIndex = '2147483647';
          overlay.style.cursor = 'row-resize';
          overlay.style.background = 'transparent';
          overlay.style.pointerEvents = 'auto';
          overlay.style.touchAction = 'none';
          // Block wheel/touchmove to fully suppress scroll.
          const _prevent = (ev) => { ev.preventDefault(); };
          overlay.addEventListener('wheel', _prevent, { passive: false });
          overlay.addEventListener('touchmove', _prevent, { passive: false });
          document.body.appendChild(overlay);
        };

        const mouseMoveHandler = function (e) {
          // Snapshot-based absolute compute to eliminate initial jump/drift
          const deltaY = e.clientY - y;
          if (Math.abs(deltaY) < 2) return; // small deadzone to avoid perceptible initial bump

          const parentRect = parentContainer.getBoundingClientRect();
          // Desired resizer top inside parent using the pointer offset captured at mousedown
          const desiredResizerTopInsideParent = (e.clientY - parentRect.top) - pointerOffsetToResizerTop;
          // Compute height from mousedown baseline (top panel offset inside parent and snap gap above)
          let newTopHeight = (desiredResizerTopInsideParent - gapAboveSnap) - topOffsetInsideParentStart;

          // Clamp using only snapshot dimensions to avoid layout-shift-induced jumps
          const maxTop = parentSnapHeight - resizerSnapHeight - minBottomAtStart - gapTotal;
          if (newTopHeight < minTopAtStart) newTopHeight = minTopAtStart;
          if (newTopHeight > maxTop) newTopHeight = maxTop;

          if (!(mainContainer && mainContainer.dataset.mode === 'stacked')) {
            const newBottomHeight = parentSnapHeight - newTopHeight - resizerSnapHeight - gapTotal;
            // Apply explicit heights during drag for exact visual alignment
            topSide.style.height = `${newTopHeight}px`;
            bottomSide.style.height = `${newBottomHeight}px`;
            lastTopHeight = newTopHeight;
            lastBottomHeight = newBottomHeight;
          }
        };

        const mouseUpHandler = function () {
          document.removeEventListener('mousemove', mouseMoveHandler);
          document.removeEventListener('mouseup', mouseUpHandler);

          document.body.style.removeProperty('cursor');
          document.body.style.removeProperty('user-select');
          // Restore overflow unchanged (we didn't toggle it).

          // Keep explicit heights and flex lock on mouseup to avoid any jump
          const finalTop = (lastTopHeight != null) ? lastTopHeight : topSide.getBoundingClientRect().height;
          const finalBottom = (lastBottomHeight != null) ? lastBottomHeight : bottomSide.getBoundingClientRect().height;
          topSide.style.height = `${finalTop}px`;
          bottomSide.style.height = `${finalBottom}px`;
          topSide.style.flex = '0 0 auto';
          bottomSide.style.flex = '0 0 auto';

          // Finally restore transitions
          topSide.style.removeProperty('transition');
          bottomSide.style.removeProperty('transition');
          resizer.style.removeProperty('transition');

          // Remove overlay if present.
          if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
          }
          overlay = null;

          // Persist vertical split only for non-stacked (classic) layout.
          if (mainContainer && mainContainer.dataset.mode !== 'stacked' && mainContainer.dataset.mode !== 'drawer') {
            state.verticalTopHeight = finalTop;
            const avail = getAvailHeight();
            state.verticalRatio = (avail > 0) ? Math.max(0, Math.min(1, finalTop / avail)) : null;
            saveState(state);
          }
          emitResizeSoon();
        };

        resizer.addEventListener('mousedown', mouseDownHandler);

        // Restore sizes with layout-stable approach and keep following resizes/mode changes
        if (mainContainer) {
          const computeTopFromState = () => {
            const avail = getAvailHeight();
            if (avail <= 0) return null;
            if (typeof state.verticalRatio === 'number' && isFinite(state.verticalRatio)) {
              return Math.round(avail * state.verticalRatio);
            }
            if (lastTopHeight != null && avail > 0) {
              // derive ratio from current explicit heights if present
              const ratio = Math.max(0, Math.min(1, lastTopHeight / avail));
              return Math.round(avail * ratio);
            }
            if (state.verticalTopHeight) return state.verticalTopHeight;
            return null;
          };

          const applyForMode = () => {
            if (mainContainer.dataset.mode === 'stacked' || mainContainer.dataset.mode === 'drawer') {
              clearExplicitHeights();
              return;
            }
            const topH = computeTopFromState();
            if (topH != null) {
              const { top } = applyExplicitHeights(topH);
              // update live ratio for future resizes and persistence
              const avail = getAvailHeight();
              if (avail > 0) {
                state.verticalRatio = Math.max(0, Math.min(1, top / avail));
              }
              emitResizeSoon();
            }
          };

          // Initial restore after layout settles
          requestAnimationFrame(() => requestAnimationFrame(applyForMode));

          // Follow parent size changes
          const ro = new ResizeObserver(() => {
            applyForMode();
            // persist updated ratio to keep reload consistent after manual resize
            if (mainContainer.dataset.mode !== 'stacked' && mainContainer.dataset.mode !== 'drawer') {
              try { localStorage.setItem('wdb.viewer.layout', JSON.stringify(state)); } catch (e) { }
            }
          });
          ro.observe(parentContainer);

          // Follow mode changes (drawer <-> classic)
          const mo = new MutationObserver(() => {
            applyForMode();
          });
          mo.observe(mainContainer, { attributes: true, attributeFilter: ['data-mode'] });
        }
        // No stacked restore (fixed 50:50)
      });
      // No drawer height dragging (fixed 60vh via CSS)
    },
  };

})(Drupal, once);
