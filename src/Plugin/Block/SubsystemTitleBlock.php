<?php

namespace Drupal\wdb_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Constructs a new SubsystemTitleBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $title = '';

    // Get the subsystem machine name from the current URL route.
    $subsystem_name = $this->routeMatch->getParameter('subsysname');

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
        // Using #markup is fine here as the title is sanitized on input.
        // You can add a class for CSS styling.
        '#markup' => '<h1 class="subsystem-title">' . $title . '</h1>',
      ];
      // Disable block caching because the title is dynamic per page/subsystem.
      $build['#cache']['max-age'] = 0;
    }

    return $build;
  }

}
