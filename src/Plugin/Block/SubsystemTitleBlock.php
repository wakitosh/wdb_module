<?php

namespace Drupal\wdb_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'WDB Subsystem Title' block.
 *
 * @Block(
 *   id = "wdb_subsystem_title_block",
 *   admin_label = @Translation("WDB Subsystem Title"),
 *   category = @Translation("WDB")
 * )
 */
class SubsystemTitleBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new SubsystemTitleBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $title = '';
    $subsystem_name = NULL;

    // --- FIX: More robust logic to determine the subsystem ---

    // 1. First, try to get the subsystem from the route parameter, as it's the most reliable method.
    $subsystem_name = $this->routeMatch->getParameter('subsysname');

    // 2. If the route parameter is not found, parse the URL path as a fallback.
    // This covers pages like '/wdb/hdb/' where 'hdb' might not be a formal route parameter.
    if (empty($subsystem_name)) {
      $path = $this->requestStack->getCurrentRequest()->getPathInfo();
      // Use a regular expression to find a pattern like '/wdb/anything/'.
      if (preg_match('#/wdb/([^/]+)#', $path, $matches)) {
        $subsystem_name = $matches[1];
      }
    }
    // --- END OF FIX ---

    if ($subsystem_name) {
      // Find the subsystem taxonomy term to get its ID.
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $terms = $term_storage->loadByProperties(['vid' => 'subsystem', 'name' => $subsystem_name]);

      if ($subsystem_term = reset($terms)) {
        // Load the configuration for this specific subsystem.
        $config_id = 'wdb_core.subsystem.' . $subsystem_term->id();
        $config = $this->configFactory->get($config_id);
        $title = $config->get('display_title');
      }
    }

    if (!empty($title)) {
      $build['title'] = [
        '#markup' => '<h1 class="subsystem-title">' . $title . '</h1>',
      ];
    }

    // This tells Drupal that the block's content is dependent on the URL,
    // so it will generate a different cache entry for each page.
    $build['#cache']['contexts'][] = 'url.path';

    return $build;
  }

}
