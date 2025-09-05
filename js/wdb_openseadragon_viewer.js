/**
 * @file
 * Initializes OpenSeadragon and Annotorious v3 for the viewer page,
 * and handles all interactions for the annotation panel and full text display.
 */
(function ($, Drupal, OpenSeadragon, AnnotoriousOSD, drupalSettings, once) {
  'use strict';

  // Variables shared within this script's scope.
  let tempWordAnnotationId = null;
  let tooltip = null;
  // Track whether the pointer is currently inside the viewer area.
  let isPointerInsideViewer = false;

  /**
   * Helper function to add the tooltip DOM element to the page just once.
   */
  function initTooltip() {
    if (!document.querySelector('.wdb-tooltip')) {
      tooltip = document.createElement('div');
      tooltip.className = 'wdb-tooltip';
      document.body.appendChild(tooltip);
    }
    else {
      tooltip = document.querySelector('.wdb-tooltip');
    }
  }

  /**
   * Drupal behavior to initialize the OpenSeadragon viewer.
   */
  Drupal.behaviors.wdbOpenSeadragonViewer = {
    attach: function (context, settings) {

      // Consolidate all logic into a single once() block.
      once('openseadragon-viewer-init', '#openseadragon-viewer', context).forEach(function (viewerElement) {
        if (!settings.wdb_core || !settings.wdb_core.openseadragon) {
          return;
        }

        // Initialize the tooltip.
        initTooltip();

        // Initialize the viewer.
        const osdSettings = drupalSettings.wdb_core.openseadragon;
        const viewer = OpenSeadragon({
          drawer: 'canvas',
          element: viewerElement,
          prefixUrl: osdSettings.prefixUrl,
          tileSources: osdSettings.tileSources,
          showNavigator: true,
          defaultZoomLevel: 0,
          minZoomLevel: 0.5,
          homeFillsViewer: true,
          crossOriginPolicy: 'Anonymous',
          gestureSettingsMouse: { clickToZoom: false },
          gestureSettingsTouch: { clickToZoom: false },
          gestureSettingsPen: { clickToZoom: false }, // added to prevent pen tap zoom
          gestureSettingsUnknown: { clickToZoom: false },
        });

        // If annotations exist, update the initial text in the panel.
        if (osdSettings.hasAnnotations) {
          const panelContent = document.getElementById('wdb-annotation-panel-content');
          if (panelContent) {
            panelContent.innerHTML = `<p>${Drupal.t('Click on an annotation to see details.')}</p>`;
          }
        }

        // ------------------------------
        // Layout switching (Split / Stacked / Drawer)
        // ------------------------------
        const mainContainer = document.getElementById('wdb-main-container');
        const infoPanel = document.getElementById('wdb-annotation-info-panel');
        const STORAGE_KEY = 'wdb.viewer.layout';
        const loadState = () => { try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch (e) { return {}; } };
        const state = loadState();

        // Apply saved sizes for the current mode (only split mode remains resizable).
        function applySavedSizesForMode(mode) {
          const info = document.getElementById('wdb-annotation-info-panel');
          if (!info) return;
          if (mode === 'split' && typeof state.splitRightWidth === 'number') {
            info.style.flexBasis = `${state.splitRightWidth}px`;
          }
        }
        /**
         * Sets layout mode on the main container.
         * @param {'split'|'stacked'|'drawer'} mode
         */
        const setLayoutMode = (mode) => {
          if (!mainContainer) return;
          mainContainer.classList.remove('layout--split', 'layout--stacked', 'layout--drawer', 'drawer-open');
          if (mode === 'split') mainContainer.classList.add('layout--split');
          if (mode === 'stacked') mainContainer.classList.add('layout--stacked');
          if (mode === 'drawer') mainContainer.classList.add('layout--drawer');
          mainContainer.dataset.mode = mode;
          // apply saved splitter sizes for this mode
          applySavedSizesForMode(mode);
          // sync UI whenever mode changes
          syncUiForLayout();
        };
        const toggleDrawerOpen = () => {
          if (!mainContainer) return;
          if (!mainContainer.classList.contains('layout--drawer')) return;
          mainContainer.classList.toggle('drawer-open');
        };

        // Initial mode: prefer drawer for very small screens, stacked otherwise; split for desktop.
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        // Always choose mode by current width; do not persist the mode.
        if (vw <= 540) setLayoutMode('drawer');
        else if (vw <= 900) setLayoutMode('stacked');
        else setLayoutMode('split');
        // Re-evaluate on resize with a small debounce.
        let _resizeTid;
        window.addEventListener('resize', function () {
          clearTimeout(_resizeTid);
          _resizeTid = setTimeout(() => {
            const w = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
            const current = mainContainer?.dataset?.mode;
            if (w > 900 && current !== 'split') setLayoutMode('split');
            else if (w <= 900 && w > 540 && current !== 'stacked') setLayoutMode('stacked');
            else if (w <= 540 && current !== 'drawer') setLayoutMode('drawer');
            else {
              // Mode unchanged, still refresh dependent UI in case responsive CSS changed.
              if (typeof syncUiForLayout === 'function') syncUiForLayout();
              if (current) applySavedSizesForMode(current);
            }
          }, 150);
        }, { passive: true });

        // Sync UI (FAB visibility, toolbar placement, edit button) based on layout mode.
        function syncUiForLayout() {
          const mode = mainContainer?.dataset?.mode;
          // FAB should exist only in drawer mode.
          let fab = document.querySelector('.wdb-panel-fab');
          if (mode === 'drawer') {
            if (!fab) {
              fab = document.createElement('button');
              fab.type = 'button';
              fab.className = 'wdb-panel-fab';
              fab.setAttribute('aria-controls', 'wdb-annotation-info-panel');
              fab.setAttribute('aria-expanded', 'false');
              fab.textContent = Drupal.t('Panel');
              fab.addEventListener('click', () => {
                const beforeOpen = mainContainer.classList.contains('drawer-open');
                toggleDrawerOpen();
                const afterOpen = mainContainer.classList.contains('drawer-open');
                fab.setAttribute('aria-expanded', afterOpen ? 'true' : 'false');
              });
              document.body.appendChild(fab);
            }
          } else if (fab) {
            fab.remove();
          }

          // Hide "Edit Annotations" button in stacked/drawer modes (view page only).
          try {
            const toolbar = document.getElementById('wdb-panel-toolbar');
            if (toolbar && osdSettings && osdSettings.toolbarUrls && osdSettings.toolbarUrls.edit) {
              const editUrl = osdSettings.toolbarUrls.edit;
              const candidates = toolbar.querySelectorAll('a.wdb-toolbar-button.order-mode');
              candidates.forEach((btn) => {
                if (btn.getAttribute('href') === editUrl) {
                  const hide = (mode === 'stacked' || mode === 'drawer');
                  btn.style.display = hide ? 'none' : '';
                  btn.setAttribute('aria-hidden', hide ? 'true' : 'false');
                }
              });
            }
          } catch (e) { /* noop */ }

          // Move the toolbar above the viewer in drawer mode; restore in others.
          try {
            const toolbar = document.getElementById('wdb-panel-toolbar');
            const panel = document.getElementById('wdb-annotation-info-panel');
            if (!toolbar || !panel || !mainContainer) return;

            const placeholderId = 'wdb-toolbar-placeholder';
            const hostId = 'wdb-toolbar-host';
            if (mode === 'drawer') {
              // Ensure a host container exists at the top of the main container.
              let host = document.getElementById(hostId);
              if (!host) {
                host = document.createElement('div');
                host.id = hostId;
                // Insert host as the first child of main container.
                mainContainer.insertBefore(host, mainContainer.firstChild);
              }
              // Create a placeholder where the toolbar originally lived (once).
              if (!document.getElementById(placeholderId)) {
                const placeholder = document.createElement('div');
                placeholder.id = placeholderId;
                panel.insertBefore(placeholder, panel.firstChild);
              }
              // Move toolbar into host if not already there.
              if (toolbar.parentElement !== host) {
                host.appendChild(toolbar);
              }
            } else {
              // Restore toolbar back into the panel if a placeholder exists.
              const placeholder = document.getElementById(placeholderId);
              if (placeholder && toolbar.parentElement && toolbar.parentElement.id !== panel.id) {
                placeholder.replaceWith(toolbar);
              }
              // Remove host if present (cleanup).
              const host = document.getElementById(hostId);
              if (host && host.childElementCount === 0) {
                host.remove();
              }
            }
          } catch (e) { /* noop */ }
        }

        // Define the styling function for annotations.
        const stylingFunction = (annotation, state) => {
          // Style for selected annotations or the temporary word hull.
          if (state?.selected || annotation.id === tempWordAnnotationId) {
            return { fill: 'rgba(255, 255, 255, 0.1)', stroke: '#ffffff', strokeWidth: 2 };
          }
          // Style for hovered annotations.
          // Only render hover highlight while the pointer is inside the viewer.
          if (state?.hovered && isPointerInsideViewer) {
            return { fill: 'rgba(255, 255, 255, 0.1)', stroke: '#ffffff', strokeWidth: 2 };
          }
          // Default: invisible.
          return { fillOpacity: 0, strokeOpacity: 0 };
        };

        // Initialize Annotorious.
        const anno = AnnotoriousOSD.createOSDAnnotator(viewer);
        anno.setUserSelectAction('SELECT');
        anno.setStyle(stylingFunction);

        // Store the anno instance on the DOM element for later access.
        viewerElement.annotorious = anno;

        // If the pointer is already over the viewer at load time, mark as inside
        // so that initial hover highlights/tooltips work without requiring a re-entry.
        try {
          if (viewerElement && typeof viewerElement.matches === 'function') {
            isPointerInsideViewer = viewerElement.matches(':hover');
          }
        } catch (e) { /* noop */ }

        // --- Pointer in/out management to avoid sticky hover/tooltip ---------
        const hideTooltipAndClearHover = () => {
          if (tooltip) tooltip.classList.remove('is-visible');
          viewerElement.style.cursor = 'default';
          // Force re-evaluation of styles so hovered visuals disappear.
          // setStyle with the same function is a cheap way to trigger rerender.
          try { anno.setStyle(stylingFunction); } catch (e) { /* noop */ }
        };

        // Keep track when pointer enters/leaves the viewer area.
        viewerElement.addEventListener('pointerenter', () => {
          isPointerInsideViewer = true;
        });
        viewerElement.addEventListener('pointerleave', () => {
          isPointerInsideViewer = false;
          hideTooltipAndClearHover();
        });

        // Fallbacks: when the window loses focus or the mouse leaves the document
        // (e.g., fast exit beyond the canvas), clear tooltip/hover visuals as well.
        window.addEventListener('blur', () => {
          isPointerInsideViewer = false;
          hideTooltipAndClearHover();
        });
        document.addEventListener('mouseleave', () => {
          isPointerInsideViewer = false;
          hideTooltipAndClearHover();
        });

        // --- Selection handling helpers ---
        let lastPanelAnnotationId = null;          // Last annotation whose details were loaded
        let programmaticSelection = false;         // Guard so selectAnnotation handler ignores our own sets
        const safeSetSelected = (id) => {
          programmaticSelection = true;
          try { anno.setSelected(id); } finally { setTimeout(() => { programmaticSelection = false; }, 0); }
        };

        /**
         * Helper function to pan the viewer to the center of a given annotation.
         * @param {string} annotationId - The ID of the annotation to pan to.
         */
        const panToAnnotation = (annotationId) => {
          const annotation = anno.getAnnotationById(annotationId);
          if (annotation && annotation.target.selector.geometry) {
            const { minX, minY, maxX, maxY } = annotation.target.selector.geometry.bounds;
            const centerX = minX + (maxX - minX) / 2;
            const centerY = minY + (maxY - minY) / 2;
            const imageCenter = new OpenSeadragon.Point(centerX, centerY);

            // Convert image coordinates to viewport coordinates and pan.
            viewer.viewport.panTo(viewer.viewport.imageToViewportCoordinates(imageCenter), false);
          }
        };

        /**
         * Updates the annotation panel content via Ajax and optionally focuses the viewer.
         * @param {string} subsysname - The machine name of the subsystem.
         * @param {string} annotationUri - The URI of the annotation to display.
         * @param {boolean} [focusOnFirstSign=true] - Whether to select and pan to the first sign.
         */
        const updateAnnotationPanel = (subsysname, annotationUri, focusOnFirstSign = true) => {
          const url = Drupal.url(`wdb/ajax/annotation_details_by_uri/${subsysname}?uri=${encodeURIComponent(annotationUri)}`);
          const throbber = '<div class="ajax-progress ajax-progress-throbber"><div class="throbber">&nbsp;</div></div>';
          const panelContent = $('#wdb-annotation-panel-content');

          panelContent.html(throbber);

          $.get(url, (response) => {
            if (response && response.title && response.content) {
              $('#wdb-annotation-panel-title').html(response.title);
              panelContent.html(response.content);

              // Handle highlighting in the full text panel.
              const fullTextPanel = $('#wdb-full-text-content');
              fullTextPanel.find('.word-unit.is-highlighted').removeClass('is-highlighted');
              if (response.current_word_unit_id) {
                const wordToHighlight = fullTextPanel.find(`[data-word-unit-original-id="${response.current_word_unit_id}"]`);
                if (wordToHighlight.length) {
                  wordToHighlight.addClass('is-highlighted');
                  // Scroll the highlighted word into view if it's not visible.
                  const container = fullTextPanel[0];
                  const element = wordToHighlight[0];
                  if (!element.scrollIntoView) return;
                  const containerRect = container.getBoundingClientRect();
                  const elementRect = element.getBoundingClientRect();
                  if (elementRect.top < containerRect.top || elementRect.bottom > containerRect.bottom) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                  }
                }
              }

              if (focusOnFirstSign) {
                if (tempWordAnnotationId) {
                  anno.removeAnnotation(tempWordAnnotationId);
                  tempWordAnnotationId = null;
                }
                const firstSignItem = panelContent.find('.sign-thumbnail[data-annotation-uri]').first();
                if (firstSignItem.length) {
                  const firstSignAnnotationUri = firstSignItem.data('annotation-uri');
                  if (firstSignAnnotationUri && anno.getAnnotationById(firstSignAnnotationUri)) {
                    safeSetSelected(firstSignAnnotationUri);
                    panToAnnotation(firstSignAnnotationUri);
                  }
                }
              }
              lastPanelAnnotationId = annotationUri;
            }
            else {
              panelContent.html($('<p>').text(Drupal.t('Error: Invalid data format received.')));
            }
          }).fail(() => {
            panelContent.html($('<p>').text(Drupal.t('Error: Could not load annotation details.')));
          });
        };

        /**
         * Helper function to parse the URL and return the annotation URI to highlight.
         * @returns {string|null} The annotation URI or null.
         */
        const getHighlightAnnotationFromUrl = () => {
          const params = new URLSearchParams(window.location.search);
          return params.get('highlight_annotation');
        };

        // === Viewer and Annotorious Event Listeners ===

        // Load annotations and full text when the viewer opens.
        viewer.addHandler('open', () => {
          if (osdSettings.annotationListUrl) {
            anno.loadAnnotations(osdSettings.annotationListUrl)
              .then(() => {
                // If a highlight parameter is in the URL, select and pan to it.
                const highlightId = getHighlightAnnotationFromUrl();
                if (highlightId) {
                  viewer.addOnceHandler('animation-finish', () => {
                    safeSetSelected(highlightId);
                    updateAnnotationPanel(osdSettings.context.subsysname, highlightId, false);
                  });
                  panToAnnotation(highlightId);
                }
              });
          }
          if (osdSettings.context) {
            const { subsysname, source, page } = osdSettings.context;
            const fullTextUrl = Drupal.url(`wdb/ajax/full_text/${subsysname}/${source}/${page}`);
            $.get(fullTextUrl).done(response => {
              if (response && response.html) {
                $('#wdb-full-text-content').html(response.html);
              }
            });
          }
        });

        // Handle clicks on annotations in the viewer (mouse or synthesized). Keep lightweight duplicate guard.
        anno.on('clickAnnotation', (annotation) => {
          if (annotation?.id && annotation.id !== lastPanelAnnotationId) {
            updateAnnotationPanel(osdSettings.context.subsysname, annotation.id, true);
          }
        });

        // Unified: fires for mouse, touch, pen. Some Annotorious versions pass an array of selected annotations.
        anno.on('selectAnnotation', (payload) => {
          if (programmaticSelection) return;
          let annotation = payload;
          if (Array.isArray(payload)) {
            annotation = payload[0];
          }
          if (annotation?.id && annotation.id !== lastPanelAnnotationId) {
            updateAnnotationPanel(osdSettings.context.subsysname, annotation.id, true);
          }
        });

        // Show tooltip on mouse enter.
        anno.on('mouseEnterAnnotation', (annotation) => {
          viewerElement.style.cursor = 'pointer';
          const commentBody = annotation.bodies.find(b => b.purpose === 'commenting');
          const labelText = commentBody ? commentBody.value : '';
          if (labelText && tooltip) {
            tooltip.textContent = labelText;
            tooltip.classList.add('is-visible');
            const geometry = annotation.target.selector.geometry;
            if (geometry && geometry.bounds) {
              const { maxX, maxY } = geometry.bounds;
              const viewerPoint = viewer.viewport.imageToViewerElementCoordinates(new OpenSeadragon.Point(maxX, maxY));
              const viewerRect = viewer.element.getBoundingClientRect();
              tooltip.style.top = `${window.scrollY + viewerRect.top + viewerPoint.y + 10}px`;
              tooltip.style.left = `${window.scrollX + viewerRect.left + viewerPoint.x + 10}px`;
            }
          }
        });

        // Hide tooltip on mouse leave.
        anno.on('mouseLeaveAnnotation', (annotation) => {
          viewerElement.style.cursor = 'default';
          if (tooltip) {
            tooltip.classList.remove('is-visible');
          }
        });

        // --- Touch fallback -------------------------------------------------
        // Some touch environments may not emit clickAnnotation/selectAnnotation reliably.
        // Fallback: on a touch pointerup inside the viewer, inspect current selection.
        viewerElement.addEventListener('pointerup', (ev) => {
          if (ev.pointerType !== 'touch') return;
          // Defer slightly to allow internal selection logic to run first.
          setTimeout(() => {
            try {
              if (programmaticSelection) return;
              if (typeof anno.getSelected === 'function') {
                const selected = anno.getSelected();
                if (selected && selected.length > 0) {
                  const first = selected[0];
                  const id = first?.id || first; // depending on implementation
                  if (id && id !== lastPanelAnnotationId) {
                    updateAnnotationPanel(osdSettings.context.subsysname, id, true);
                  }
                }
              }
            } catch (e) {
              // Silent fallback
            }
          }, 10);
        }, { passive: true });

        // === Click Listeners within the Panel (Event Delegation) ===
        // Reuse mainContainer for delegated events below.
        // (already defined above)
        if (mainContainer && !mainContainer.wdbListenerAttached) {
          mainContainer.wdbListenerAttached = true;

          // Use Pointer Events (pointerup) to unify mouse/touch/pen. Fallback to click if unsupported.
          const interactionEvent = window.PointerEvent ? 'pointerup' : 'click';
          let lastInteractionTime = 0;
          $(mainContainer).on(interactionEvent, '.nav-button-icon, .is-clickable', function (event) {
            // Ignore secondary buttons or synthetic duplicates.
            if (event.button && event.button !== 0) return;
            const now = Date.now();
            if (now - lastInteractionTime < 40) return; // simple debounce to avoid duplicate firing on some devices
            lastInteractionTime = now;
            event.preventDefault();

            const clickedElement = $(this);

            const clearTempWordAnnotation = () => {
              if (tempWordAnnotationId && anno.getAnnotationById(tempWordAnnotationId)) {
                anno.removeAnnotation(tempWordAnnotationId);
              }
              tempWordAnnotationId = null;
            };

            // Dynamically displays a word's hull polygon.
            const showWordHull = (pointsData) => {
              clearTempWordAnnotation();
              try {
                if (typeof pointsData === 'string') {
                  pointsData = JSON.parse($('<textarea />').html(pointsData).text());
                }
                const flatPoints = pointsData.flat().filter(p => p && p.length > 0);
                if (flatPoints.length > 0) {
                  const concavity = osdSettings.subsystemConfig.hullConcavity ?? 20;
                  const hullApiUrl = `/wdb/api/hull?points=${encodeURIComponent(JSON.stringify(flatPoints))}&concavity=${concavity}`;
                  fetch(hullApiUrl)
                    .then(response => response.json())
                    .then(hullPoints => {
                      if (hullPoints && hullPoints.length > 0) {
                        tempWordAnnotationId = 'temp-word-hull-' + Date.now();
                        const newAnnotation = {
                          id: tempWordAnnotationId,
                          type: 'Annotation',
                          bodies: [],
                          target: {
                            selector: {
                              type: 'POLYGON',
                              geometry: {
                                bounds: {
                                  minX: Math.min(...hullPoints.map(p => p[0])),
                                  minY: Math.min(...hullPoints.map(p => p[1])),
                                  maxX: Math.max(...hullPoints.map(p => p[0])),
                                  maxY: Math.max(...hullPoints.map(p => p[1])),
                                },
                                points: hullPoints,
                              },
                            },
                          },
                        };
                        anno.addAnnotation(newAnnotation);
                        safeSetSelected(newAnnotation.id);
                        panToAnnotation(tempWordAnnotationId);
                      }
                    });
                }
              }
              catch (e) {
                console.error('Failed to handle word click:', e);
              }
            };

            // 1. Handle navigation button clicks.
            if (clickedElement.hasClass('nav-button-icon') && !clickedElement.is('[disabled]')) {
              const nextAnnotationUri = clickedElement.data('annotation-uri');
              if (nextAnnotationUri) {
                const { subsysname } = osdSettings.context;
                const isWordNav = clickedElement.hasClass('prev-word') || clickedElement.hasClass('next-word');
                if (isWordNav) {
                  const wordPoints = clickedElement.data('word-points');
                  showWordHull(wordPoints);
                  updateAnnotationPanel(subsysname, nextAnnotationUri, false);
                }
                else {
                  clearTempWordAnnotation();
                  updateAnnotationPanel(subsysname, nextAnnotationUri, true);
                }
              }
            }
            // 2. Handle individual sign clicks.
            else if (clickedElement.data('annotation-uri')) {
              // Sign (thumbnail etc.) tapped/clicked in panel.
              clearTempWordAnnotation();
              const annotationId = clickedElement.data('annotation-uri');
              if (annotationId && anno.getAnnotationById(annotationId)) {
                safeSetSelected(annotationId);
                panToAnnotation(annotationId);
                if (osdSettings?.context?.subsysname) {
                  updateAnnotationPanel(osdSettings.context.subsysname, annotationId, false);
                }
              }
            }
            // 3. Handle word thumbnail clicks.
            else if (clickedElement.hasClass('word-thumbnail')) {
              const rawAttr = clickedElement.attr('data-word-points');
              showWordHull(rawAttr);
            }
            // 4. Handle clicks on words in the full text area.
            else if (clickedElement.hasClass('word-unit')) {
              const wordUnitId = clickedElement.data('word-unit-original-id');
              const rawAttr = clickedElement.attr('data-word-points');
              showWordHull(rawAttr);
              if (wordUnitId) {
                const getUriUrl = Drupal.url(`wdb/ajax/get_uri_from_wu/${wordUnitId}`);
                $.get(getUriUrl)
                  .done(response => {
                    if (response && response.annotation_uri) {
                      const { subsysname } = osdSettings.context;
                      updateAnnotationPanel(subsysname, response.annotation_uri, false);
                    }
                  });
              }
            }
          });
        }

        // Keyboard accessibility for drawer mode.
        document.addEventListener('keydown', (e) => {
          if (!mainContainer) return;
          if (mainContainer.dataset.mode !== 'drawer') return;
          if (e.key === ' ' || e.key === 'Enter') {
            // Avoid toggling while typing in inputs.
            const ae = document.activeElement;
            const isEditable = ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.isContentEditable);
            if (!isEditable) {
              e.preventDefault();
              toggleDrawerOpen();
            }
          }
        });
      });
    },
  };
})(jQuery, Drupal, window.OpenSeadragon, window.AnnotoriousOSD, drupalSettings, once);
