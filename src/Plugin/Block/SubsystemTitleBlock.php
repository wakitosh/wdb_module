<?php

namespace Drupal\wdb_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
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
    $link_url_string = '';
    $subsystem_name = NULL;
    $config_id = NULL;

    $subsystem_name = $this->routeMatch->getParameter('subsysname');
    if (empty($subsystem_name)) {
      $path = $this->requestStack->getCurrentRequest()->getPathInfo();
      if (preg_match('#/wdb/([^/]+)#', $path, $matches)) {
        $subsystem_name = $matches[1];
      }
    }

    if ($subsystem_name) {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $terms = $term_storage->loadByProperties(['vid' => 'subsystem', 'name' => $subsystem_name]);

      if ($subsystem_term = reset($terms)) {
        $config_id = 'wdb_core.subsystem.' . $subsystem_term->id();
        $config = $this->configFactory->get($config_id);
        $title = $config->get('display_title');
        $link_url_string = $config->get('display_title_link');
      }
    }

    if (!empty($title)) {
      if (!empty($link_url_string)) {
        try {
          $url = NULL;
          if (strpos($link_url_string, 'http://') === 0 || strpos($link_url_string, 'https://') === 0) {
            $url = Url::fromUri($link_url_string);
          }
          else {
            $url = Url::fromUserInput($link_url_string);
          }

          $link = Link::fromTextAndUrl($title, $url);
          $build['title'] = [
            '#markup' => '<h1 class="subsystem-title">' . $link->toString() . '</h1>',
          ];
        }
        catch (\InvalidArgumentException $e) {
          \Drupal::logger('wdb_core')->warning('Invalid URL provided for subsystem title link: @url', ['@url' => $link_url_string]);
          $build['title'] = [
            '#markup' => '<h1 class="subsystem-title">' . $this->t($title) . '</h1>',
          ];
        }
      }
      else {
        $build['title'] = [
          '#markup' => '<h1 class="subsystem-title">' . $this->t($title) . '</h1>',
        ];
      }
    }

    $build['#cache']['contexts'][] = 'url.path';

    if ($config_id) {
      $build['#cache']['tags'][] = 'config:' . $config_id;
    }

    return $build;
  }

}
