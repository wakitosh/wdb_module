<?php

namespace Drupal\wdb_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Url;
use Drupal\wdb_core\Service\WdbDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to generate a IIIF Presentation API v3 compliant manifest.
 */
class IiifV3ManifestController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The WDB data service.
   *
   * @var \Drupal\wdb_core\Service\WdbDataService
   */
  protected WdbDataService $wdbDataService;

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * Constructs a new IiifV3ManifestController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\wdb_core\Service\WdbDataService $wdb_data_service
   *   The WDB data service.
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The HTTP client factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, WdbDataService $wdb_data_service, ClientFactory $http_client_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->wdbDataService = $wdb_data_service;
    $this->httpClientFactory = $http_client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('wdb_core.data_service'),
      $container->get('http_client_factory')
    );
  }

  /**
   * Builds the V3 manifest and returns it as a JSON response.
   *
   * @param string $subsysname
   *   The machine name of the subsystem.
   * @param string $source
   *   The source identifier.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the IIIF manifest.
   */
  public function buildManifest(string $subsysname, string $source): JsonResponse {
    $source_storage = $this->entityTypeManager->getStorage('wdb_source');
    $source_entities = $source_storage->loadByProperties(['source_identifier' => $source]);
    if (empty($source_entities)) {
      throw new NotFoundHttpException();
    }
    $source_entity = reset($source_entities);

    $manifest_uri = Url::fromRoute('wdb_core.iiif_manifest_v3', ['subsysname' => $subsysname, 'source' => $source], ['absolute' => TRUE])->toString();

    $manifest = [
      '@context' => 'http://iiif.io/api/presentation/3/context.json',
      'id' => $manifest_uri,
      'type' => 'Manifest',
      'label' => ['en' => [$source_entity->label()]],
      'items' => [],
    ];

    $all_pages = $this->wdbDataService->getAllPagesForSource($source_entity);
    $iiif_base_url = $this->wdbDataService->getIiifBaseUrlForSubsystem($subsysname);
    if (!$iiif_base_url) {
      $this->getLogger('wdb_core')->error('IIIF base URL is not configured for subsystem: @subsys', ['@subsys' => $subsysname]);
      throw new NotFoundHttpException('IIIF configuration is incomplete for this subsystem.');
    }

    $subsys_config = $this->wdbDataService->getSubsystemConfig($subsysname);
    if (!$subsys_config) {
      throw new NotFoundHttpException('Subsystem configuration not found.');
    }

    // Add the viewingDirection property from the subsystem configuration.
    $page_navigation = $subsys_config->get('pageNavigation');
    if ($page_navigation) {
      $manifest['viewingDirection'] = $page_navigation;
    }

    $image_ext = ltrim($subsys_config->get('iiif_fileExt') ?? 'jpg', '.');

    foreach ($all_pages as $page_entity) {
      $image_identifier = $page_entity->get('image_identifier')->value;
      if (empty($image_identifier)) {
        $page_num = $page_entity->get('page_number')->value;
        $image_identifier = 'wdb/' . $subsysname . '/' . $source . '/' . $page_num . '.' . $image_ext;
      }

      $info_json_url = $iiif_base_url . '/' . rawurlencode($image_identifier) . '/info.json';

      // Fetch image dimensions from the IIIF server's info.json file.
      try {
        $client = $this->httpClientFactory->fromOptions(['timeout' => 10]);
        $response = $client->get($info_json_url);
        $info_data = json_decode($response->getBody(), TRUE);
        $width = $info_data['width'] ?? 2000;
        $height = $info_data['height'] ?? 2000;
      }
      catch (\Exception $e) {
        $this->getLogger('wdb_core')->error('Failed to fetch info.json for @url: @message', ['@url' => $info_json_url, '@message' => $e->getMessage()]);
        // Use fallback dimensions on failure.
        $width = 2000;
        $height = 2000;
      }

      $canvas_uri = $page_entity->getCanvasUri();

      // Generate the URL for the annotation list associated with this canvas.
      $annotation_list_uri = Url::fromRoute('wdb_core.word_annotation_list_v3', [
        'wdb_annotation_page' => $page_entity->id(),
      ], [
        'absolute' => TRUE,
      ])->toString();

      // Define the base URI for the image service (without /info.json).
      $image_service_uri = $iiif_base_url . '/' . rawurlencode($image_identifier);

      // Define the URI for the actual image content to be displayed.
      $image_content_uri = $image_service_uri . '/full/max/0/default.jpg';

      // Generate the thumbnail image URL (150px width, auto height).
      $thumbnail_image_uri = $image_service_uri . '/full/150,/0/default.jpg';

      $canvas = [
        'id' => $canvas_uri,
        'type' => 'Canvas',
        'height' => $height,
        'width' => $width,
        'label' => ['en' => [$page_entity->label()]],
        // Add the thumbnail property.
        'thumbnail' => [
          [
            'id' => $thumbnail_image_uri,
            'type' => 'Image',
            'format' => 'image/jpeg',
            'service' => [
              [
                'id' => $image_service_uri,
                'type' => 'ImageService3',
                'profile' => 'level2',
              ],
            ],
          ],
        ],
        'items' => [
          [
            'id' => $canvas_uri . '/page/1',
            'type' => 'AnnotationPage',
            'items' => [
              [
                'id' => $canvas_uri . '/page/1/image',
                'type' => 'Annotation',
                'motivation' => 'painting',
                'body' => [
                  'id' => $image_content_uri,
                  'type' => 'Image',
                  'format' => 'image/jpeg',
                  'width' => $width,
                  'height' => $height,
                  'service' => [
                    [
                      'id' => $image_service_uri,
                      'type' => 'ImageService3',
                      'profile' => 'level2',
                    ],
                  ],
                ],
                'target' => $canvas_uri,
              ],
            ],
          ],
        ],
        'annotations' => [
          [
            'id' => $annotation_list_uri,
            'type' => 'AnnotationPage',
            'label' => ['en' => ['Word-level Annotations for this page']],
          ],
        ],
      ];
      $manifest['items'][] = $canvas;
    }

    $response = new JsonResponse($manifest);
    $response->headers->set('Content-Type', 'application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');

    return $response;
  }

}
