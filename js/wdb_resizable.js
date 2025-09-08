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
      // Debug toggle (set localStorage['wdb.debug.resize'] = '1' to enable)
      const DBG = (() => { try { return !!localStorage.getItem('wdb.debug.resize'); } catch (e) { return false; } })();
      const dbg = (...args) => { if (DBG) { try { console.log('[wdb-resize]', ...args); } catch (e) { } } };
      // No longer persist or apply saved mode; viewer decides by width.
      // --- Horizontal Resizing (Left/Right Panels) ---
      once('wdb-resizable-init', '#wdb-resizer', context).forEach(function (resizer) {
        // If Split.js is active for horizontal split, skip legacy handler
        if (document.getElementById('wdb-main-container')?.classList.contains('splitjs-active')) {
          return;
        }

        const leftSide = resizer.previousElementSibling; // The viewer container
        const rightSide = resizer.nextElementSibling; // The annotation panel

        let x = 0;
        let rightWidth = 0;
        let dragOffsetX = 0; // pointer offset from resizer's left edge
        let overlay = null; // overlay to swallow pointer events during drag
        let prevOverflow = '';
        let isHDragging = false;
        let hRestoreSuppressUntil = 0;
        let lastRightWidth = null;
        let lastLeftWidth = null;
        let hDragRafId = 0;
        // After mouseup, keep widths pinned for a short settling window
        let hSettleUntil = 0;
        let hSettleRafId = 0;
        let hStableFrames = 0; // consecutive frames considered stable
        // Helper to read numeric min-width (px) from computed style
        function getMinWidthPx(el, fallback = 0) {
          try {
            const s = window.getComputedStyle(el);
            const v = parseFloat(s.minWidth);
            return Number.isFinite(v) ? v : fallback;
          } catch (e) { return fallback; }
        }
        // Read whether horizontal width changes should be locked (e.g., during vertical interactions)
        function isHLocked() {
          if (!mainContainer) return false;
          const until = Number(mainContainer.dataset.hLockUntil || '0');
          return until && Date.now() < until;
        }
        // Reassert pinned horizontal widths from dataset during lock
        function reassertPinnedH() {
          if (!mainContainer) return;
          const r = Number(mainContainer.dataset.hRightPx || '0');
          const l = Number(mainContainer.dataset.hLeftPx || '0');
          if (r > 0 && l >= 0) {
            rightSide.style.setProperty('flex', '0 0 auto', 'important');
            leftSide.style.setProperty('flex', '0 0 auto', 'important');
            // Use content-box width and clamp to CSS min widths
            const parentEl = resizer.parentElement;
            const cw = (parentEl && (parentEl.clientWidth || parentEl.getBoundingClientRect().width)) || 0;
            const resW = resizer.offsetWidth || resizerRectWidth();
            const minLeft = getMinWidthPx(leftSide, 200);
            const minRight = getMinWidthPx(rightSide, 270);
            let rr = r;
            const maxRight = Math.max(minRight, cw - resW - minLeft);
            if (rr < minRight) rr = minRight;
            if (rr > maxRight) rr = maxRight;
            let ll = Math.max(minLeft, cw - resW - rr);
            // Final exactness guard
            if (ll + resW + rr > cw) {
              ll = Math.max(minLeft, cw - resW - rr);
            }
            rightSide.style.setProperty('flex-basis', `${rr}px`, 'important');
            leftSide.style.setProperty('flex-basis', `${ll}px`, 'important');
            lastRightWidth = rr;
            lastLeftWidth = ll;
          }
        }
        function startHDragPinLoop() {
          if (hDragRafId) return;
          const tick = () => {
            if (!isHDragging) { hDragRafId = 0; return; }
            if (lastRightWidth != null) {
              const cur = rightSide.getBoundingClientRect().width;
              if (Math.abs(cur - lastRightWidth) > 0.75) {
                rightSide.style.setProperty('flex-basis', `${lastRightWidth}px`, 'important');
              }
            }
            if (lastLeftWidth != null) {
              const curL = leftSide.getBoundingClientRect().width;
              if (Math.abs(curL - lastLeftWidth) > 0.75) {
                leftSide.style.setProperty('flex-basis', `${lastLeftWidth}px`, 'important');
              }
            }
            hDragRafId = requestAnimationFrame(tick);
          };
          hDragRafId = requestAnimationFrame(tick);
        }
        function stopHDragPinLoop() {
          if (hDragRafId) { cancelAnimationFrame(hDragRafId); hDragRafId = 0; }
        }

        function startHPostSettleLoop() {
          if (hSettleRafId) return;
          const tick = () => {
            if (Date.now() >= hSettleUntil) { hSettleRafId = 0; return; }
            try {
              const parentEl = resizer.parentElement;
              const cw = (parentEl && (parentEl.clientWidth || parentEl.getBoundingClientRect().width)) || 0;
              const resW = resizer.offsetWidth || resizerRectWidth();
              if (cw > 0 && lastRightWidth != null) {
                // Recompute left to exactly fill remaining space and clamp
                const minLeft = getMinWidthPx(leftSide, 200);
                const minRight = getMinWidthPx(rightSide, 270);
                let leftW = Math.max(minLeft, cw - resW - lastRightWidth);
                let rightW = lastRightWidth;
                const maxRight = Math.max(minRight, cw - resW - minLeft);
                if (rightW > maxRight) rightW = maxRight;
                if (leftW + resW + rightW > cw) {
                  // Adjust left to match exactly if rounding drift remains
                  leftW = Math.max(minLeft, cw - resW - rightW);
                }
                // Check if current widths already match target within epsilon
                const eps = 0.5;
                const curR = rightSide.getBoundingClientRect().width;
                const curL = leftSide.getBoundingClientRect().width;
                const sumOK = Math.abs((curL + resW + curR) - cw) <= 1.0;
                const rOK = Math.abs(curR - rightW) <= eps;
                const lOK = Math.abs(curL - leftW) <= eps;
                if (rOK && lOK && sumOK) {
                  hStableFrames++;
                } else {
                  hStableFrames = 0;
                }
                // Only write styles if deltas are meaningful to avoid extra paints
                if (!rOK) {
                  rightSide.style.setProperty('flex', '0 0 auto', 'important');
                  rightSide.style.setProperty('flex-basis', `${rightW}px`, 'important');
                }
                if (!lOK) {
                  leftSide.style.setProperty('flex', '0 0 auto', 'important');
                  leftSide.style.setProperty('flex-basis', `${leftW}px`, 'important');
                }
                lastRightWidth = rightW;
                lastLeftWidth = leftW;
                dbg('settle pin', { cw, resW, rightW, leftW, curR, curL, hStableFrames });
                // If stable for a few frames, end settle early to reduce perceived “wait”
                if (hStableFrames >= 4) { hSettleRafId = 0; return; }
              }
            } catch (e) { }
            hSettleRafId = requestAnimationFrame(tick);
          };
          hSettleRafId = requestAnimationFrame(tick);
        }

        // Helper: snapshot current widths, clamp, pin, and record dataset for lock
        function snapAndPinCurrentH(reason) {
          try {
            const parentEl = resizer.parentElement;
            const cw = (parentEl && (parentEl.clientWidth || parentEl.getBoundingClientRect().width)) || 0;
            const resW = resizer.offsetWidth || resizerRectWidth();
            const minLeft = getMinWidthPx(leftSide, 200);
            const minRight = getMinWidthPx(rightSide, 270);
            let rr = rightSide.getBoundingClientRect().width;
            const maxRight = Math.max(minRight, cw - resW - minLeft);
            if (rr < minRight) rr = minRight;
            if (rr > maxRight) rr = maxRight;
            let ll = Math.max(minLeft, cw - resW - rr);
            if (ll + resW + rr > cw) {
              ll = Math.max(minLeft, cw - resW - rr);
            }
            rightSide.style.setProperty('flex', '0 0 auto', 'important');
            leftSide.style.setProperty('flex', '0 0 auto', 'important');
            rightSide.style.setProperty('flex-basis', `${rr}px`, 'important');
            leftSide.style.setProperty('flex-basis', `${ll}px`, 'important');
            lastRightWidth = rr;
            lastLeftWidth = ll;
            if (mainContainer) {
              mainContainer.dataset.hRightPx = String(rr);
              mainContainer.dataset.hLeftPx = String(ll);
              mainContainer.dataset.hLockUntil = String(Date.now() + 900);
            }
            dbg('snapPin', reason, { cw, resW, rr, ll });
          } catch (e) { }
        }

        const mouseDownHandler = function (e) {
          e.preventDefault();
          isHDragging = true;
          const resizerRect = resizer.getBoundingClientRect();
          x = e.clientX;
          dragOffsetX = e.clientX - resizerRect.left;
          // Measure current widths
          rightWidth = rightSide.getBoundingClientRect().width;
          const parentElStart = resizer.parentElement;
          const cwStart = (parentElStart && (parentElStart.clientWidth || parentElStart.getBoundingClientRect().width)) || 0;
          const resWStart = resizer.offsetWidth || resizerRect.width || 0;
          const minLeft = getMinWidthPx(leftSide, 200);
          const minRight = getMinWidthPx(rightSide, 270);
          const maxRight = Math.max(minRight, cwStart - resWStart - minLeft);
          // Clamp right to feasible range, then compute left as remainder
          if (rightWidth < minRight) rightWidth = minRight;
          if (rightWidth > maxRight) rightWidth = maxRight;
          let leftWidth = Math.max(minLeft, cwStart - resWStart - rightWidth);
          lastRightWidth = rightWidth;
          lastLeftWidth = leftWidth;
          // Freeze sides so layout can't reflow them during drag
          leftSide.style.setProperty('flex', '0 0 auto', 'important');
          leftSide.style.setProperty('flex-basis', `${leftWidth}px`, 'important');
          rightSide.style.setProperty('flex', '0 0 auto', 'important');
          rightSide.style.setProperty('flex-basis', `${rightWidth}px`, 'important');

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
          startHDragPinLoop();
        };

        const mouseMoveHandler = function (e) {
          // Keep the divider aligned to the pointer based on initial grab offset
          const parentEl = resizer.parentElement;
          const containerRect = parentEl.getBoundingClientRect();
          const desiredDividerX = e.clientX - containerRect.left - dragOffsetX;
          // Use content-box width to avoid border-induced drift
          const containerWidth = parentEl.clientWidth || containerRect.width;
          // Right panel width equals remaining space to the right of divider
          const resW = resizer.offsetWidth || resizerRectWidth();
          let newRightWidth = containerWidth - desiredDividerX - resW;
          // Clamp using CSS min-width for both sides to avoid overflow or negative widths
          const minLeft = getMinWidthPx(leftSide, 200);
          const minRight = getMinWidthPx(rightSide, 270);
          const maxRight = Math.max(minRight, containerWidth - resW - minLeft);
          if (newRightWidth < minRight) newRightWidth = minRight;
          if (newRightWidth > maxRight) newRightWidth = maxRight;
          // Update the flex-basis to change the width.
          rightSide.style.setProperty('flex-basis', `${newRightWidth}px`, 'important');
          lastRightWidth = newRightWidth;
          // Keep left side consistent with remainder
          let newLeftWidth = containerWidth - resW - newRightWidth;
          // Final guard: ensure sum never exceeds container content width
          if (newLeftWidth < minLeft) {
            newLeftWidth = minLeft;
            const maxR = Math.max(minRight, containerWidth - resW - newLeftWidth);
            const adjR = Math.min(newRightWidth, maxR);
            if (adjR !== newRightWidth) {
              newRightWidth = adjR;
              rightSide.style.setProperty('flex-basis', `${newRightWidth}px`, 'important');
              lastRightWidth = newRightWidth;
            }
          }
          leftSide.style.setProperty('flex-basis', `${newLeftWidth}px`, 'important');
          lastLeftWidth = newLeftWidth;
          // If external change tweaked width in this same frame, reapply immediately
          const cur = rightSide.getBoundingClientRect().width;
          if (Math.abs(cur - newRightWidth) > 0.75) {
            rightSide.style.setProperty('flex-basis', `${newRightWidth}px`, 'important');
          }
          const curL = leftSide.getBoundingClientRect().width;
          if (Math.abs(curL - newLeftWidth) > 0.75) {
            leftSide.style.setProperty('flex-basis', `${newLeftWidth}px`, 'important');
          }
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
          stopHDragPinLoop();

          // Keep both sides explicit to avoid any jump at release
          try {
            const parentElEnd = resizer.parentElement;
            const cw = (parentElEnd && (parentElEnd.clientWidth || parentElEnd.getBoundingClientRect().width)) || 0;
            const resW = resizer.offsetWidth || resizerRectWidth();
            const minLeft = getMinWidthPx(leftSide, 200);
            const minRight = getMinWidthPx(rightSide, 270);
            let finalRight = rightSide.getBoundingClientRect().width;
            const maxRight = Math.max(minRight, cw - resW - minLeft);
            if (finalRight < minRight) finalRight = minRight;
            if (finalRight > maxRight) finalRight = maxRight;
            let finalLeft = Math.max(minLeft, cw - resW - finalRight);
            if (finalLeft + resW + finalRight > cw) {
              finalLeft = Math.max(minLeft, cw - resW - finalRight);
            }
            rightSide.style.setProperty('flex', '0 0 auto', 'important');
            leftSide.style.setProperty('flex', '0 0 auto', 'important');
            rightSide.style.setProperty('flex-basis', `${finalRight}px`, 'important');
            leftSide.style.setProperty('flex-basis', `${finalLeft}px`, 'important');
            lastRightWidth = finalRight;
            lastLeftWidth = finalLeft;
          } catch (e) { }

          // Persist split width (Split mode) as pixels of right panel.
          const widthPx = rightSide.getBoundingClientRect().width;
          // Use content-box width (clientWidth) to match restore calculations and avoid border-induced drift
          const parentElForSave = resizer.parentElement;
          const parentWidth = (parentElForSave && (parentElForSave.clientWidth || parentElForSave.getBoundingClientRect().width)) || 0;
          state.splitRightWidth = widthPx;
          state.splitRightRatio = parentWidth > 0 ? Math.max(0, Math.min(1, widthPx / parentWidth)) : null;
          saveState(state);
          // Brief suppression window in case something tries to restore immediately
          hRestoreSuppressUntil = Date.now() + 1500;
          // Record pinned widths for observers and set a short horizontal lock
          if (mainContainer) {
            try {
              mainContainer.dataset.hRightPx = String(lastRightWidth || rightSide.getBoundingClientRect().width || 0);
              const parentEl = resizer.parentElement;
              const cw = (parentEl && (parentEl.clientWidth || parentEl.getBoundingClientRect().width)) || 0;
              const resW = resizer.offsetWidth || resizerRectWidth();
              const leftPx = Math.max(0, cw - resW - (Number(mainContainer.dataset.hRightPx) || 0));
              mainContainer.dataset.hLeftPx = String(leftPx);
              mainContainer.dataset.hLockUntil = String(Date.now() + 800);
            } catch (e) { }
          }
          // Start a short settle loop to absorb late scrollbars/layout flush
          hSettleUntil = Date.now() + 700;
          startHPostSettleLoop();
          setTimeout(() => { isHDragging = false; }, 50);
        };

        resizer.addEventListener('mousedown', mouseDownHandler);

        // Restore saved width in desktop mode (not stacked/drawer). Prefer ratio, fallback to px.
        const applyHRestore = () => {
          // If Split.js is active, do not restore widths here
          if (document.getElementById('wdb-main-container')?.classList.contains('splitjs-active')) return;
          if (mainContainer && (mainContainer.dataset.mode === 'stacked' || mainContainer.dataset.mode === 'drawer')) return;
          if (isHDragging || Date.now() < hRestoreSuppressUntil || Date.now() < hSettleUntil) return;
          const parentEl = resizer.parentElement;
          const cw = (parentEl && (parentEl.clientWidth || parentEl.getBoundingClientRect().width)) || 0;
          const resW = resizer.offsetWidth || resizerRectWidth();
          // If current inline widths already satisfy invariants, keep them (avoid late re-application)
          try {
            const curR = rightSide.getBoundingClientRect().width;
            const curL = leftSide.getBoundingClientRect().width;
            const minLeft = getMinWidthPx(leftSide, 200);
            const minRight = getMinWidthPx(rightSide, 270);
            const sumOK = Math.abs((curL + resW + curR) - cw) <= 1.0;
            const minsOK = (curL >= minLeft - 0.5) && (curR >= minRight - 0.5);
            if (cw > 0 && sumOK && minsOK) {
              lastRightWidth = curR;
              lastLeftWidth = curL;
              return;
            }
          } catch (e) { /* noop */ }
          let w = null;
          // Prefer last saved pixel width for exactness; fall back to ratio
          if (state.splitRightWidth) {
            w = state.splitRightWidth;
          } else if (typeof state.splitRightRatio === 'number' && isFinite(state.splitRightRatio) && cw > 0) {
            w = Math.round(cw * state.splitRightRatio);
          }
          if (w != null) {
            // Clamp to CSS min-widths and container
            const minLeft = getMinWidthPx(leftSide, 200);
            const minRight = getMinWidthPx(rightSide, 270);
            const maxRight = Math.max(minRight, cw - resW - minLeft);
            if (w < minRight) w = minRight;
            if (w > maxRight) w = maxRight;
            // Pin both sides explicitly to prevent flex reflow jitter
            rightSide.style.setProperty('flex', '0 0 auto', 'important');
            leftSide.style.setProperty('flex', '0 0 auto', 'important');
            rightSide.style.setProperty('flex-basis', `${w}px`, 'important');
            const newLeft = Math.max(minLeft, cw - resW - w);
            leftSide.style.setProperty('flex-basis', `${newLeft}px`, 'important');
            lastRightWidth = w;
            lastLeftWidth = newLeft;
          }
        };
        applyHRestore();
        const roH = new ResizeObserver(() => {
          if (isHDragging) return;
          if (isHLocked()) { reassertPinnedH(); return; }
          if (Date.now() >= hRestoreSuppressUntil) applyHRestore();
        });
        roH.observe(resizer.parentElement);

        // During window resize, briefly suppress restore and pin current widths to avoid rollback/jitter
        let _hResizeSuppTimer = null;
        let _lastResizeCW = null;
        window.addEventListener('resize', () => {
          // On first event in a burst, start suppression and settle pinning
          if (_hResizeSuppTimer === null) {
            const now = Date.now();
            hRestoreSuppressUntil = Math.max(hRestoreSuppressUntil, now + 1000);
            hSettleUntil = Math.max(hSettleUntil, now + 600);
            // Only snap if parent width changed meaningfully
            try {
              const parentEl = resizer.parentElement;
              const cw = (parentEl && (parentEl.clientWidth || parentEl.getBoundingClientRect().width)) || 0;
              if (_lastResizeCW == null || Math.abs((_lastResizeCW || 0) - cw) > 1) {
                _lastResizeCW = cw;
                snapAndPinCurrentH('win-resize-start');
              }
            } catch (e) { }
            startHPostSettleLoop();
          }
          // Debounce end of resize; after quiet period, apply restore once
          if (_hResizeSuppTimer) clearTimeout(_hResizeSuppTimer);
          _hResizeSuppTimer = setTimeout(() => {
            _hResizeSuppTimer = null;
            if (!isHDragging && !isHLocked()) {
              applyHRestore();
              dbg('win-resize-end restore');
            }
          }, 150);
        });

        // During suppression window after mouseup, reassert explicit widths if something overrides them
        const reassertIfSuppressedH = () => {
          if (!isHDragging && (Date.now() < hRestoreSuppressUntil || isHLocked())) {
            if (lastRightWidth != null && lastLeftWidth != null) {
              rightSide.style.setProperty('flex', '0 0 auto', 'important');
              leftSide.style.setProperty('flex', '0 0 auto', 'important');
              rightSide.style.setProperty('flex-basis', `${lastRightWidth}px`, 'important');
              leftSide.style.setProperty('flex-basis', `${lastLeftWidth}px`, 'important');
            }
          }
        };
        const moSidesH = new MutationObserver((mutations) => {
          for (const m of mutations) {
            if (m.type === 'attributes' && m.attributeName === 'style') { reassertIfSuppressedH(); break; }
          }
        });
        moSidesH.observe(leftSide, { attributes: true, attributeFilter: ['style'] });
        moSidesH.observe(rightSide, { attributes: true, attributeFilter: ['style'] });
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
        // Dragging state to suppress restore logic during user interaction
        let isVDragging = false;
        // After mouseup, briefly suppress any restore/apply that could override final user size
        let vRestoreSuppressUntil = 0; // epoch ms
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
        // Guard to avoid infinite loops when we reassert heights
        let _vReasserting = false;
        // RAF id for pinning heights during drag
        let vDragRafId = 0;
        function startVDragPinLoop() {
          if (vDragRafId) return;
          const tick = () => {
            if (!isVDragging) { vDragRafId = 0; return; }
            // If external code changed heights, immediately reapply last computed ones
            if (lastTopHeight != null && lastBottomHeight != null) {
              const curTop = topSide.getBoundingClientRect().height;
              const curBot = bottomSide.getBoundingClientRect().height;
              if (Math.abs(curTop - lastTopHeight) > 0.5 || Math.abs(curBot - lastBottomHeight) > 0.5) {
                topSide.style.setProperty('height', `${lastTopHeight}px`, 'important');
                bottomSide.style.setProperty('height', `${lastBottomHeight}px`, 'important');
              }
            }
            vDragRafId = requestAnimationFrame(tick);
          };
          vDragRafId = requestAnimationFrame(tick);
        }
        function stopVDragPinLoop() {
          if (vDragRafId) { cancelAnimationFrame(vDragRafId); vDragRafId = 0; }
        }

        // Debounced window resize emitter to notify dependent components (e.g., viewers)
        let _emitResizeTimer = null;
        function emitResizeSoon() {
          if (_emitResizeTimer) {
            clearTimeout(_emitResizeTimer);
          }
          _emitResizeTimer = setTimeout(() => {
            try { window.dispatchEvent(new Event('resize')); } catch (e) { }
            _emitResizeTimer = null;
          }, 180); // slightly longer to avoid racing other observers
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
          topSide.style.setProperty('height', `${clampedTop}px`, 'important');
          bottomSide.style.setProperty('height', `${bottomH}px`, 'important');
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
          isVDragging = true;
          dbg && dbg('vdrag mousedown');
          // Inform other layout scripts to pause container height adjustments
          if (mainContainer) {
            try { mainContainer.dataset.vdrag = '1'; } catch (e) { }
            // Also lock horizontal split briefly to avoid cross-axis wobble
            try {
              const hResizer = document.getElementById('wdb-resizer');
              if (hResizer) {
                const leftPane = hResizer.previousElementSibling;
                const rightPane = hResizer.nextElementSibling;
                const r = rightPane ? rightPane.getBoundingClientRect().width : 0;
                const parentElH = hResizer.parentElement;
                const cw = (parentElH && (parentElH.clientWidth || parentElH.getBoundingClientRect().width)) || 0;
                const resW = hResizer.offsetWidth || hResizer.getBoundingClientRect().width || 0;
                // Clamp with current min widths to avoid late overflow
                const minLeft = leftPane ? parseFloat(getComputedStyle(leftPane).minWidth) || 200 : 200;
                const minRight = rightPane ? parseFloat(getComputedStyle(rightPane).minWidth) || 270 : 270;
                let rr = Math.max(minRight, Math.min(r, Math.max(minRight, cw - resW - minLeft)));
                let ll = Math.max(minLeft, cw - resW - rr);
                mainContainer.dataset.hRightPx = String(rr);
                mainContainer.dataset.hLeftPx = String(ll);
                mainContainer.dataset.hLockUntil = String(Date.now() + 900);
              }
            } catch (e) { }
          }
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
          topSide.style.setProperty('height', `${startTopHeight}px`, 'important');
          bottomSide.style.setProperty('height', `${startBottomHeight}px`, 'important');
          topSide.style.flex = '0 0 auto';
          bottomSide.style.flex = '0 0 auto';
          lastTopHeight = startTopHeight;
          lastBottomHeight = startBottomHeight;

          // Start per-frame pinning to prevent snap-back while paused
          startVDragPinLoop();

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
          // log first few frames to ensure handler runs
          if (isVDragging && (lastTopHeight == null || lastBottomHeight == null)) { dbg && dbg('vdrag mousemove'); }
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
            topSide.style.setProperty('height', `${newTopHeight}px`, 'important');
            bottomSide.style.setProperty('height', `${newBottomHeight}px`, 'important');
            lastTopHeight = newTopHeight;
            lastBottomHeight = newBottomHeight;

            // Keep in-memory ratio in sync during drag so any observer-based restore
            // uses the latest user size (persisting to localStorage only on mouseup).
            const avail = getAvailHeight();
            if (avail > 0) {
              state.verticalRatio = Math.max(0, Math.min(1, newTopHeight / avail));
            }

            // If some external change tweaked heights in this same frame, reapply immediately.
            // This pins the divider to pointer during drag.
            const curTop = topSide.getBoundingClientRect().height;
            const curBot = bottomSide.getBoundingClientRect().height;
            if (Math.abs(curTop - newTopHeight) > 1 || Math.abs(curBot - newBottomHeight) > 1) {
              topSide.style.setProperty('height', `${newTopHeight}px`, 'important');
              bottomSide.style.setProperty('height', `${newBottomHeight}px`, 'important');
            }
          }
        };

        const mouseUpHandler = function () {
          document.removeEventListener('mousemove', mouseMoveHandler);
          document.removeEventListener('mouseup', mouseUpHandler);
          dbg && dbg('vdrag mouseup');

          document.body.style.removeProperty('cursor');
          document.body.style.removeProperty('user-select');
          // Restore overflow unchanged (we didn't toggle it).

          // Keep explicit heights and flex lock on mouseup to avoid any jump
          const finalTop = (lastTopHeight != null) ? lastTopHeight : topSide.getBoundingClientRect().height;
          const finalBottom = (lastBottomHeight != null) ? lastBottomHeight : bottomSide.getBoundingClientRect().height;
          topSide.style.setProperty('height', `${finalTop}px`, 'important');
          bottomSide.style.setProperty('height', `${finalBottom}px`, 'important');
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

          // Suppress restore for a short window to avoid race with observers/layout
          vRestoreSuppressUntil = Date.now() + 1000; // extend suppression to avoid late observers
          // Allow observers after a brief delay (keep dragging state a tad longer)
          setTimeout(() => { isVDragging = false; }, 50);
          stopVDragPinLoop();
          // Release vdrag flag soon and set a short lock for layout adjusters
          if (mainContainer) {
            try {
              const lockUntil = Date.now() + 1000; // align with suppression window
              mainContainer.dataset.lockUntil = String(lockUntil);
              setTimeout(() => { try { delete mainContainer.dataset.vdrag; } catch (e) { } }, 80);
              // Keep horizontal lock a bit longer to ride out accordion/layout updates
              mainContainer.dataset.hLockUntil = String(Date.now() + 1000);
            } catch (e) { }
          }
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
            if (isVDragging) return; // do not override while user is dragging
            if (Date.now() < vRestoreSuppressUntil) return; // brief post-drag suppression
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
            if (!isVDragging && Date.now() >= vRestoreSuppressUntil) {
              applyForMode();
              // persist updated ratio to keep reload consistent after manual resize
              if (mainContainer.dataset.mode !== 'stacked' && mainContainer.dataset.mode !== 'drawer') {
                try { localStorage.setItem('wdb.viewer.layout', JSON.stringify(state)); } catch (e) { }
              }
            }
          });
          ro.observe(parentContainer);

          // Watch for external style changes on top/bottom panels and reassert during suppression
          const reassertIfSuppressed = () => {
            // Do not interfere during active drag; only act during post-drag suppression window
            if (!isVDragging && Date.now() < vRestoreSuppressUntil) {
              if (lastTopHeight != null && !_vReasserting && mainContainer.dataset.mode !== 'stacked' && mainContainer.dataset.mode !== 'drawer') {
                _vReasserting = true;
                try {
                  applyExplicitHeights(lastTopHeight);
                } finally {
                  setTimeout(() => { _vReasserting = false; }, 0);
                }
              }
            }
          };
          const moSides = new MutationObserver((mutations) => {
            for (const m of mutations) {
              if (m.type === 'attributes' && m.attributeName === 'style') {
                reassertIfSuppressed();
                break;
              }
            }
          });
          moSides.observe(topSide, { attributes: true, attributeFilter: ['style'] });
          moSides.observe(bottomSide, { attributes: true, attributeFilter: ['style'] });

          // Follow mode changes (drawer <-> classic)
          const mo = new MutationObserver(() => {
            if (!isVDragging && Date.now() >= vRestoreSuppressUntil) {
              applyForMode();
            }
          });
          mo.observe(mainContainer, { attributes: true, attributeFilter: ['data-mode'] });
        }
        // No stacked restore (fixed 50:50)
      });
      // No drawer height dragging (fixed 60vh via CSS)
    },
  };

})(Drupal, once);
