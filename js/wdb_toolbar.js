/**
 * @file
 * Handles the creation and events for common UI elements like the toolbar and pager.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  // SVG icon definitions.
  const ICONS = {
    view: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
    edit: '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>',
    thumbnails: '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>',
    export: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
    iiif: '<path d="M23.961165.0194155v4.1743324c-.3632779-.1162976-.8065-.2144577-1.1359238.0388296-.5421612.4168569-.539062,1.473405-.5331814,2.0863628.0009443.0984291-.0023871.8709339.0499567.8853235.5711645-.0249047,1.0857967-.2969093,1.6191484-.4670855v3.7666069c-.3996896.1473574-.8516076.2220159-1.2363243.4044112-.0836474.0396575-.3789118.2041791-.3942438.2853388l-.0031239,8.4717248c-1.5285568.5525833-3.0106951,1.2574632-4.5992276,1.6335776l.0007107-8.980504c-.0559804-1.8083799-.1364425-3.6229572-.0776699-5.4347385.0277336-.8549434.0558722-1.6891904.3002361-2.5150419C18.7396881,1.7048713,21.2798291.1820188,23.961165.0194155Z" style="fill: #ee2636; stroke-width:0;"/><g><path d="M24,0v21.3182186H0V0h24ZM23.961165.0194155c-2.681336.1626033-5.221477,1.6854558-6.0096427,4.349138-.2443639.8258515-.2725025,1.6600985-.3002361,2.5150419-.0587726,1.8117812.0216896,3.6263586.0776699,5.4347385l-.0007107,8.980504c1.5885326-.3761143,3.0706709-1.0809943,4.5992276-1.6335776l.0031239-8.4717248c.0153319-.0811597.3105964-.2456814.3942438-.2853388.3847167-.1823953.8366347-.2570539,1.2363243-.4044112v-3.7666069c-.5333517.1701762-1.0479839.4421808-1.6191484.4670855-.0523438-.0143896-.0490124-.7868944-.0499567-.8853235-.0058806-.6129578-.0089798-1.6695059.5331814-2.0863628.3294237-.2532873.7726458-.1551271,1.1359238-.0388296V.0194155ZM1.6664519,1.4800776c-.6161753.0090583-1.1109534.3083341-1.4221776.8319226-.0607035.1021245-.1946555.3552743-.2163313.4632332-.0312874.1558306-.0312197.9518158-.0039399,1.1129893.0241165.1424845.1791077.4618029.2443679.609939.5187889,1.1776141,1.2864046,2.2563877,2.6053279,2.5788757,2.6512585.6482555,3.0546266-2.0698375,1.8605646-3.7825097-.6238427-.8947928-1.9406366-1.8310204-3.0678114-1.81445ZM13.3359121,1.5007925c-1.8217858.1328016-2.1533879,1.7922832-1.404289,3.2175897.7304203,1.3897667,2.7861451,3.2132151,4.4125655,2.1396715,1.04509-.6898276.825193-2.1726775.3067135-3.1306943-.5846252-1.0802369-2.0131802-2.3214641-3.3149899-2.2265669ZM9.2970151,1.5201612c-1.4486376.1035733-2.9342192,1.5364529-3.3109108,2.9023862-.2674553.9698282-.0598628,2.3682017,1.043105,2.6931502,2.214076.6522953,4.9148305-2.3443421,4.1309407-4.4799992-.2938401-.8005483-1.032221-1.174945-1.8631348-1.1155371ZM5.0097087,8.9699608L.4271845,7.2808124v12.377381l4.5825243,1.6406097v-12.3288423ZM16.6407767,8.9699608l-4.5825243-1.6891485v12.3288423l4.5825243,1.6891485v-12.3288423ZM10.8932039,7.3390589c-.0530118-.0025552-.1037437-.0016718-.1562795.0088-.1663398.0331563-.4554936.1611439-.6304957.2238186-1.2910283.4623645-2.558578.9980372-3.8645181,1.4168911.0011774,2.3824998-.0318908,4.7720199-.0097017,7.1562201.0138975,1.4932745.0504853,2.9956712.1181064,4.483379.0101208.222664.0048239.4479875.0187149.6705598l4.5241737-1.6890729V7.3390589Z" style="fill: #fdfdfe; stroke-width:0;"/><polygon points="16.6407767 8.9699608 16.6407767 21.2988031 12.0582524 19.6096547 12.0582524 7.2808124 16.6407767 8.9699608" style="fill: #0074a2; stroke-width:0;"/><g><path d="M10.8932039,7.3390589v12.2705958l-4.5241737,1.6890729c-.013891-.2225724-.0085941-.4478958-.0187149-.6705598-.0676211-1.4877079-.1042089-2.9901046-.1181064-4.483379-.0221891-2.3842002.0108791-4.7737203.0097017-7.1562201,1.3059401-.4188538,2.5734898-.9545266,3.8645181-1.4168911.1750021-.0626747.4641559-.1906623.6304957-.2238186.0525358-.0104719.1032677-.0113553.1562795-.0088Z" style="fill: #ee2636; stroke-width:0;"/><polygon points="5.0097087 8.9699608 5.0097087 21.2988031 .4271845 19.6581934 .4271845 7.2808124 5.0097087 8.9699608" style="fill: #0074a2; stroke-width:0;"/></g><g><path d="M9.2970151,1.5201612c.8309138-.0594079,1.5692947.3149889,1.8631348,1.1155371.7838899,2.1356572-1.9168647,5.1322945-4.1309407,4.4799992-1.1029678-.3249485-1.3105603-1.7233219-1.043105-2.6931502.3766916-1.3659333,1.8622733-2.798813,3.3109108-2.9023862Z" style="fill: #ee2636; stroke-width:0;"/><path d="M13.3359121,1.5007925c1.3018097-.0948972,2.7303648,1.14633,3.3149899,2.2265669.5184795.9580168.7383765,2.4408667-.3067135,3.1306943-1.6264204,1.0735436-3.6821452-.7499049-4.4125655-2.1396715-.7490989-1.4253064-.4174968-3.0847881,1.404289-3.2175897Z" style="fill: #0074a2; stroke-width:0;"/><path d="M1.6664519,1.4800776c1.1271748-.0165704,2.4439687.9196572,3.0678114,1.81445,1.194062,1.7126722.790694,4.4307652-1.8605646,3.7825097-1.3189232-.322488-2.086539-1.4012616-2.6053279-2.5788757-.0652602-.1481361-.2202514-.4674545-.2443679-.609939-.0272797-.1611735-.0273475-.9571587.0039399-1.1129893.0216758-.1079589.1556279-.3611086.2163313-.4632332.3112243-.5235885.8060023-.8228643,1.4221776-.8319226Z" style="fill: #0074a2; stroke-width:0;"/></g></g>',
  prev: '<polyline points="15 18 9 12 15 6"></polyline><line x1="20" y1="12" x2="9" y2="12"></line>',
  next: '<polyline points="9 18 15 12 9 6"></polyline><line x1="4" y1="12" x2="15" y2="12"></line>',
  };

  /**
   * Creates a toolbar button element.
   *
   * @param {string} title - The button's title attribute.
   * @param {string} href - The URL the button links to.
   * @param {boolean} isNewTab - Whether the link should open in a new tab.
   * @param {string} svgPath - The SVG path data for the icon.
   * @param {string} [toolName] - An optional tool name for a data attribute.
   * @returns {HTMLAnchorElement} The created button element.
   */
  function createToolbarButton(title, href, isNewTab, svgPath, toolName) {
    const button = document.createElement('a');
    button.href = href;
    button.title = title;
    button.classList.add('wdb-toolbar-button');
    if (toolName) {
      button.dataset.tool = toolName;
    }
    if (isNewTab) {
      button.target = '_blank';
      button.rel = 'noopener noreferrer';
    }
    const svgIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svgIcon.setAttribute('viewBox', '0 0 24 24');
    svgIcon.innerHTML = svgPath;
    button.appendChild(svgIcon);
    return button;
  }

  /**
   * Helper function to create a link for the dropdown menu.
   *
   * @param {string} text - The link text.
   * @param {string} href - The link URL.
   * @param {boolean} [isNewTab=false] - Whether the link opens in a new tab.
   * @returns {HTMLAnchorElement} The created link element.
   */
  function createToolbarLink(text, href, isNewTab = false) {
    const link = document.createElement('a');
    link.href = href;
    link.textContent = text;
    link.classList.add('wdb-dropdown-item');
    if (isNewTab) {
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }
    return link;
  }

  /**
   * Helper function to build the thumbnail pager content and show it.
   *
   * @param {HTMLElement} pagerContainer - The container for the pager modal.
   * @param {Array} pageList - The list of page data objects.
   * @param {number} currentPage - The current page number.
   * @param {string} pageNavigation - The navigation direction (e.g., 'rtl').
   */
  function buildAndShowThumbnailPager(pagerContainer, pageList, currentPage, pageNavigation) {
    const content = document.createElement('div');
    content.classList.add('wdb-pager-modal-content');

    const closeButton = document.createElement('button');
    closeButton.textContent = '×';
    closeButton.classList.add('wdb-pager-modal-close');
    closeButton.onclick = () => pagerContainer.classList.remove('is-visible');
    content.appendChild(closeButton);

    const ul = document.createElement('ul');
    ul.classList.add('wdb-thumbnail-list');
    if (pageNavigation === 'right-to-left') {
      ul.classList.add('rtl');
    }

    const viewerElement = document.getElementById('openseadragon-viewer');
    if (!viewerElement) {
      return;
    }

    const viewerBounds = viewerElement.getBoundingClientRect();
    pagerContainer.style.top = `${viewerBounds.top}px`;
    pagerContainer.style.left = `${viewerBounds.left}px`;
    pagerContainer.style.width = `${viewerBounds.width}px`;

    pageList.forEach(function (pageData) {
      const li = document.createElement('li');
      const a = document.createElement('a');
      const img = document.createElement('img');
      const span = document.createElement('span');
      a.href = pageData.url;
      a.title = pageData.label;
      img.classList.add('lazyload');
      img.setAttribute('data-src', pageData.thumbnailUrl);
      img.src = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
      img.alt = pageData.label;
      span.textContent = pageData.page;
      if (parseInt(pageData.page, 10) === parseInt(currentPage, 10)) {
        li.classList.add('is-current');
      }
      a.appendChild(img);
      a.appendChild(span);
      li.appendChild(a);
      ul.appendChild(li);
    });

    content.appendChild(ul);
    pagerContainer.innerHTML = '';
    pagerContainer.appendChild(content);

    const currentItem = ul.querySelector('.is-current');
    if (currentItem) {
      setTimeout(() => {
        currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }, 100);
    }
  }

  /**
   * Drupal behavior to initialize the common toolbar.
   */
  Drupal.behaviors.wdbToolbar = {
    attach: function (context, settings) {
      once('wdb-toolbar-init', '#wdb-panel-toolbar', context).forEach(function (panelToolbar) {

        const osdSettings = settings.wdb_core.openseadragon;
        if (!osdSettings) {
          return;
        }

        panelToolbar.innerHTML = '';
        const urls = osdSettings.toolbarUrls || {};

        // --- Mode Toggle Button ---
        if (osdSettings.isEditable) {
          if (urls.view) {
            const btn = createToolbarButton(Drupal.t('View Mode'), urls.view, false, ICONS.view);
            btn.classList.add('order-mode');
            panelToolbar.appendChild(btn);
          }
        }
        else {
          if (urls.edit) {
            const btn = createToolbarButton(Drupal.t('Edit Annotations'), urls.edit, false, ICONS.edit);
            btn.classList.add('order-mode');
            panelToolbar.appendChild(btn);
          }
        }

        // --- Common Buttons ---
        const pagerContainer = document.getElementById('wdb-thumbnail-pager-container');
        if (pagerContainer && osdSettings.pageList && osdSettings.pageList.length > 0) {
          // Determine current page index.
            const pageList = osdSettings.pageList;
            const currentPageNum = parseInt(osdSettings.currentPage, 10);
            const currentIndex = pageList.findIndex(p => parseInt(p.page, 10) === currentPageNum);
            const dir = osdSettings.pageNavigation || 'left-to-right';
            // In RTL, visual "next" (進む) is to the left, so we invert logical previous/next indices.
            const getPrevIndex = () => dir === 'right-to-left' ? currentIndex + 1 : currentIndex - 1;
            const getNextIndex = () => dir === 'right-to-left' ? currentIndex - 1 : currentIndex + 1;
            const prevIndex = getPrevIndex();
            const nextIndex = getNextIndex();

            const makeNavButton = (type, targetIndex) => {
              const title = type === 'prev' ? Drupal.t('Previous Page') : Drupal.t('Next Page');
              const icon = type === 'prev' ? ICONS.prev : ICONS.next;
              const href = (targetIndex >= 0 && targetIndex < pageList.length) ? pageList[targetIndex].url : '#';
              const btn = createToolbarButton(title, href, false, icon);
              btn.dataset.nav = type;
              if (type === 'prev') btn.classList.add('order-prev'); else btn.classList.add('order-next');
              if (href === '#') {
                btn.classList.add('is-disabled');
                btn.setAttribute('aria-disabled', 'true');
              }
              btn.addEventListener('click', (e) => {
                if (btn.classList.contains('is-disabled')) {
                  e.preventDefault();
                  return;
                }
                e.preventDefault();
                window.location.href = href;
              });
              return btn;
            };

            const prevButton = makeNavButton('prev', prevIndex);
            const nextButton = makeNavButton('next', nextIndex);

            // Insert navigation buttons before thumbnails button.
            panelToolbar.appendChild(prevButton);
            panelToolbar.appendChild(nextButton);
          const pagerButton = createToolbarButton(Drupal.t('Thumbnails'), '#', false, ICONS.thumbnails);
          pagerButton.classList.add('order-pager');
          panelToolbar.appendChild(pagerButton);
          pagerButton.addEventListener('click', (e) => {
            e.preventDefault();
            const isVisible = pagerContainer.classList.contains('is-visible');
            if (isVisible) {
              pagerContainer.classList.remove('is-visible');
            }
            else {
              if (!pagerContainer.dataset.initialized) {
                buildAndShowThumbnailPager(pagerContainer, osdSettings.pageList, osdSettings.currentPage, osdSettings.pageNavigation);
                pagerContainer.dataset.initialized = 'true';
              }
              pagerContainer.classList.add('is-visible');
            }
          });

          // Direction-aware keyboard navigation: Arrow keys move logically forward/back (# consider reading direction).
          if (!document.body.dataset.wdbDirPagerKeys) {
            document.body.dataset.wdbDirPagerKeys = '1';
            document.addEventListener('keydown', (ev) => {
              if (ev.defaultPrevented) return;
              if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') return;
              // Avoid interfering with text inputs / editable elements.
              const ae = document.activeElement;
              if (ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.isContentEditable)) return;
              const currentIdx = pageList.findIndex(p => parseInt(p.page, 10) === parseInt(osdSettings.currentPage, 10));
              if (currentIdx === -1) return;
              const isRTL = (osdSettings.pageNavigation || 'left-to-right') === 'right-to-left';
              let targetIdx = currentIdx;
              if (!isRTL) {
                // LTR: Right = forward (next), Left = back (previous).
                if (ev.key === 'ArrowRight') targetIdx = currentIdx + 1;
                else targetIdx = currentIdx - 1;
              }
              else {
                // RTL: flex row-reverse => visually moving left means increasing index.
                if (ev.key === 'ArrowLeft') targetIdx = currentIdx + 1; // forward (next in reading order)
                else targetIdx = currentIdx - 1; // back
              }
              if (targetIdx < 0 || targetIdx >= pageList.length) return;
              window.location.href = pageList[targetIdx].url;
              ev.preventDefault();
            });
          }
        }

        if (!osdSettings.isEditable) {
          const exportButton = createToolbarButton(Drupal.t('Export'), '#', false, ICONS.export);
          exportButton.classList.add('order-export');
          panelToolbar.appendChild(exportButton);
          const dropdown = document.createElement('div');
          dropdown.classList.add('wdb-toolbar-dropdown');
          if (urls.tei) {
            dropdown.appendChild(createToolbarLink(Drupal.t('Download TEI/XML'), urls.tei));
          }
          if (urls.rdf) {
            dropdown.appendChild(createToolbarLink(Drupal.t('Download RDF/XML'), urls.rdf));
          }
            if (urls.text) {
              dropdown.appendChild(createToolbarLink(Drupal.t('Download Text'), urls.text));
            }
            exportButton.appendChild(dropdown);
            if (urls.manifest_v3) {
              const manifestBtn = createToolbarButton(Drupal.t('View IIIF Manifest'), urls.manifest_v3, true, ICONS.iiif);
              manifestBtn.classList.add('order-manifest');
              panelToolbar.appendChild(manifestBtn);
            }
            exportButton.addEventListener('click', (e) => {
              if (e.target.classList.contains('wdb-dropdown-item')) {
                dropdown.classList.remove('is-visible');
                return;
              }
              e.preventDefault();
              dropdown.classList.toggle('is-visible');
            });
            document.addEventListener('click', (e) => {
              if (!exportButton.contains(e.target)) {
                dropdown.classList.remove('is-visible');
              }
            }, true);
  }
      });
    },
  };

})(jQuery, Drupal, drupalSettings, once);
