/**
 * @file
 * Initializes OpenSeadragon and Annotorious v3 for the editor page,
 * and implements a custom annotation editor and API integration.
 */
(function ($, Drupal, OpenSeadragon, AnnotoriousOSD, drupalSettings, once) {
  'use strict';

  /**
   * Drupal behavior to initialize the OpenSeadragon editor.
   */
  Drupal.behaviors.wdbOpenSeadragonEditor = {
    attach: function (context, settings) {
      once('openseadragon-editor-init', '#openseadragon-viewer', context).forEach(function (viewerElement) {
        if (settings.wdb_core && settings.wdb_core.openseadragon) {

          const osdSettings = settings.wdb_core.openseadragon;
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
            gestureSettingsMouse: {
              clickToZoom: false,
            },
          });

          const anno = AnnotoriousOSD.createOSDAnnotator(viewer, {
            drawingEnabled: false,
            drawingMode: 'click',
          });
          anno.setDrawingTool('polygon');
          viewerElement.annotorious = anno;
          // --- Add custom drawing tool buttons to the toolbar ---
          const panelToolbar = document.getElementById('wdb-panel-toolbar');
          if (panelToolbar) {
            const createToolButton = (title, svgPath, toolName) => {
              const button = document.createElement('button');
              button.type = 'button';
              button.title = title;
              button.classList.add('wdb-toolbar-button');
              if (toolName) {
                button.dataset.tool = toolName;
              }
              const svgIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
              svgIcon.setAttribute('viewBox', '0 0 24 24');
              svgIcon.innerHTML = svgPath;
              button.appendChild(svgIcon);
              return button;
            };
            const ICONS = {
              select: '<path d="M5.8889517,3.0758973v16.4799991c0,.5422223.44.9777781.9777777.9777781.28,0,.5511111-.1200002.7377778-.3333333l3.6711111-4.1999995,2.582222,5.1688881c.3511111.7022222,1.2044449.9866676,1.9066664.6355553s.986667-1.2044449.6355559-1.9066671l-2.5199992-5.053332h5.2488887c.5422223,0,.9822222-.4399999.9822222-.9822222,0-.2800001-.1200002-.5466665-.3288892-.7333336L7.6045072,2.3070084c-.1911111-.168889-.431111-.2622223-.6844444-.2622223-.5688889,0-1.0311112.4622223-1.0311111,1.0311112Z"/>',
              polygon: '<path d="M2.7143487,8.228547l18.8571432-3.0857142-1.7142855,13.7142854-12.6857147-2.4000003L2.7143487,8.228547Z"/><g><circle cx="2.7143487" cy="8.228547" r="1.7142856"/><circle cx="21.5714922" cy="5.1428328" r="1.7142861"/><circle cx="19.8572064" cy="18.8571181" r="1.7142855"/><circle cx="7.1714917" cy="16.4571192" r="1.7142855"/></g>',
            };

            const selectButton = createToolButton(Drupal.t('Select'), ICONS.select, 'select');
            const polygonButton = createToolButton(Drupal.t('Draw Polygon'), ICONS.polygon, 'polygon');

            panelToolbar.prepend(polygonButton);
            panelToolbar.prepend(selectButton);

            const setActive = (tool) => {
              panelToolbar.querySelectorAll('[data-tool]').forEach(btn => btn.classList.remove('is-active'));
              const activeBtn = panelToolbar.querySelector(`[data-tool="${tool}"]`);
              if (activeBtn) {
                activeBtn.classList.add('is-active');
              }
            };

            // Click event for the select button.
            selectButton.addEventListener('click', (e) => {
              e.preventDefault();
              anno.setDrawingEnabled(false);
              // Update the UI state immediately.
              setActive('select');
            });

            // Click event for the polygon button.
            polygonButton.addEventListener('click', (e) => {
              e.preventDefault();
              anno.setDrawingTool('polygon');
              anno.setDrawingEnabled(true);
              setActive('polygon');
            });

            anno.on('setDrawingTool', tool => setActive(tool));
            setActive('select');
          }

          // --- Create DOM elements for the custom editor and confirmation modal ---
          const editorElement = document.createElement('div');
          editorElement.className = 'wdb-editor-popup';
          editorElement.innerHTML = `
            <div class="form-item">
              <textarea placeholder="Enter label..."></textarea>
            </div>
            <div class="form-actions">
              <button class="button delete" data-action="delete" title="Delete"><svg viewBox="0 0 48 48"><defs><style> .a {fill: none; stroke: #000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2px; }</style></defs><line class="a" x1="9" y1="12" x2="39" y2="12"/><polyline class="a" points="17 12 20 8 28 8 31 12"/><polyline class="a" points="36 16 34.222 40 13.778 40 12 16"/><g><line class="a" x1="24" y1="18" x2="24" y2="32"/><line class="a" x1="30" y1="18" x2="30" y2="32"/><line class="a" x1="18" y1="18" x2="18" y2="32"/></g></svg></button>
              <button class="button button--danger" data-action="cancel">Cancel</button>
              <button class="button button--primary" data-action="save">OK</button>
            </div>
          `;
          document.body.appendChild(editorElement);

          const confirmModalOverlay = document.createElement('div');
          confirmModalOverlay.className = 'wdb-confirm-modal-overlay';
          confirmModalOverlay.innerHTML = `
            <div class="wdb-confirm-modal">
              <p>${Drupal.t('Are you sure you want to delete this annotation?')}</p>
              <button class="button" data-action="confirm-cancel">Cancel</button>
              <button class="button button--primary" data-action="confirm-delete">Delete</button>
            </div>
          `;
          document.body.appendChild(confirmModalOverlay);

          const textarea = editorElement.querySelector('textarea');
          const saveButton = editorElement.querySelector('[data-action=save]');
          const cancelButton = editorElement.querySelector('[data-action=cancel]');
          const deleteButton = editorElement.querySelector('[data-action=delete]');
          const confirmDeleteButton = confirmModalOverlay.querySelector('[data-action=confirm-delete]');
          const confirmCancelButton = confirmModalOverlay.querySelector('[data-action=confirm-cancel]');

          let currentAnnotation = null;
          let originalAnnotationBeforeEdit = null;
          // Flag to track if a cancel operation is in progress.
          let isCancelling = false;

          const openEditor = (annotation, element) => {
            currentAnnotation = annotation;
            originalAnnotationBeforeEdit = JSON.parse(JSON.stringify(annotation));

            const body = annotation.bodies.find(b => b.purpose === 'commenting');
            textarea.value = body ? body.value : '';
            const bounds = element.getBoundingClientRect();
            editorElement.style.top = `${window.scrollY + bounds.top}px`;
            editorElement.style.left = `${window.scrollX + bounds.right + 5}px`;
            editorElement.style.display = 'block';
            textarea.focus();
          };

          const closeEditor = () => {
            editorElement.style.display = 'none';
            currentAnnotation = null;
            originalAnnotationBeforeEdit = null;
          };

          saveButton.addEventListener('click', () => {
            if (currentAnnotation) {
              const newBodyValue = textarea.value.trim();
              if (!newBodyValue) {
                alert(Drupal.t('Label body cannot be empty.'));
                return;
              }

              const newBody = { purpose: 'commenting', value: newBodyValue };

              // Get the current live state of the annotation to reflect shape changes made by the user.
              const liveAnnotation = anno.getAnnotationById(currentAnnotation.id);

              const updatedAnnotation = {
                ...liveAnnotation,
                bodies: [newBody],
              };

              anno.updateAnnotation(updatedAnnotation);
            }
            closeEditor();
            anno.setSelected(undefined);
          });

          cancelButton.addEventListener('click', () => {
            if (currentAnnotation) {
              const isNew = originalAnnotationBeforeEdit.bodies.length === 0;

              if (isNew) {
                anno.removeAnnotation(currentAnnotation.id);
              }
              else {
                // Set the cancelling flag before overwriting with the original state.
                isCancelling = true;
                anno.updateAnnotation(originalAnnotationBeforeEdit);
                anno.cancelSelected(currentAnnotation.id);
              }
            }
            closeEditor();
          });

          deleteButton.addEventListener('click', () => {
            if (currentAnnotation) {
              confirmModalOverlay.style.display = 'flex';
            }
          });

          confirmCancelButton.addEventListener('click', () => {
            confirmModalOverlay.style.display = 'none';
          });

          confirmDeleteButton.addEventListener('click', () => {
            if (currentAnnotation) {
              anno.removeAnnotation(currentAnnotation.id);
            }
            confirmModalOverlay.style.display = 'none';
            closeEditor();
          });

          // --- Connect Annotorious events with the editor ---
          anno.on('selectionChanged', (selected) => {
            const selection = selected.length > 0 ? selected[0] : null;

            // If selection is cleared.
            if (!selection) {
              // If the editor is open (due to an unintentional click), discard changes and close it.
              if (currentAnnotation) {
                const isNew = originalAnnotationBeforeEdit.bodies.length === 0;
                if (isNew) {
                  anno.removeAnnotation(currentAnnotation.id);
                }
                else {
                  anno.updateAnnotation(originalAnnotationBeforeEdit);
                }
              }
              closeEditor();
              return;
            }

            // If something was selected.
            // If we were already editing something else, cancel it.
            if (currentAnnotation && currentAnnotation.id !== selection.id) {
              const isNew = originalAnnotationBeforeEdit.bodies.length === 0;
              if (isNew) {
                anno.removeAnnotation(currentAnnotation.id);
              }
              else {
                anno.updateAnnotation(originalAnnotationBeforeEdit);
              }
            }

            // Open the editor for the new selection.
            const element = viewer.element.querySelector('.a9s-annotation.selected');
            if (element) {
              openEditor(selection, element);
            }
          });

          // --- API Integration ---
          viewer.addHandler('open', () => {
            if (osdSettings.annotationListUrl) {
              anno.loadAnnotations(osdSettings.annotationListUrl);
            }
          });

          // The 'updateAnnotation' event handles both creating new and updating existing annotations.
          anno.on('updateAnnotation', (annotation, previous) => {
            // If this update was triggered by a cancel operation, do not call the API.
            if (isCancelling) {
              isCancelling = false;
              return;
            }

            const commentBody = annotation.bodies.find(b => b.purpose === 'commenting');
            if (!commentBody || !(commentBody.value || '').trim()) {
              anno.updateAnnotation(previous.id, previous);
              return;
            }
            if (osdSettings.initialCanvasID) {
              annotation.target.source = osdSettings.initialCanvasID;
            }

            const isNew = !previous.bodies.find(b => b.purpose === 'commenting');
//            const endpoint = isNew ? '/create' : '/update';
//            const apiUrl = osdSettings.annotationEndpoint.url + endpoint;
            const apiUrl = isNew ? osdSettings.annotationEndpoint.create : osdSettings.annotationEndpoint.update;

            fetch(apiUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(annotation),
            })
              .then(response => response.ok ? response.json() : Promise.reject(new Error(response.statusText)))
              .then(savedAnnotation => {
                if (isNew) {
                  // Replace the temporary client-side annotation with the permanent one from the server.
                  anno.removeAnnotation(annotation.id);
                  anno.addAnnotation(savedAnnotation);
                  anno.setDrawingTool('polygon');
                  anno.setDrawingEnabled(true);
                }
              })
              .catch(error => {
                console.error(`Error ${isNew ? 'creating' : 'updating'} annotation:`, error);
                anno.updateAnnotation(previous.id, previous);
              });
          });

          anno.on('deleteAnnotation', annotation => {
            // This event only fires for existing annotations being deleted.
            const isNew = !annotation.id.startsWith('http');
            if (isNew) {
              // This was just an unsaved new annotation being deleted, so no API call is needed.
              return;
            }
            const deleteUrl = `${osdSettings.annotationEndpoint.destroy}?uri=${encodeURIComponent(annotation.id)}`;
            fetch(deleteUrl, { method: 'DELETE' })
              .then(response => {
                if (!response.ok) {
                  throw new Error(response.statusText);
                }
              })
              .catch(error => {
                console.error('Error deleting annotation:', error);
                alert('Error deleting annotation.');
                // Re-add the annotation if the API call fails.
                anno.addAnnotation(annotation);
              });
          });
        }
      });
    },
  };
})(jQuery, Drupal, window.OpenSeadragon, window.AnnotoriousOSD, drupalSettings, once);
