<?php

namespace Drupal\wdb_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\wdb_core\Service\WdbDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for WDB gallery pages based on subsystem configuration.
 *
 * This access checker determines if a user can view a gallery page by checking
 * the 'allowAnonymous' setting in the corresponding subsystem's configuration.
 */
class WdbAccessCheck implements AccessInterface, ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The WDB data service.
   *
   * @var \Drupal\wdb_core\Service\WdbDataService
   */
  protected WdbDataService $wdbDataService;

  /**
   * Constructs a new WdbAccessCheck object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\wdb_core\Service\WdbDataService $wdbDataService
   *   The WDB data service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, WdbDataService $wdbDataService) {
    $this->configFactory = $config_factory;
    $this->wdbDataService = $wdbDataService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('wdb_core.data_service')
    );
  }

  /**
   * Checks access for gallery pages.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param string $subsysname
   *   The machine name of the subsystem from the URL.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, Route $route, string $subsysname = ''): AccessResult {
    if (empty($subsysname)) {
      return AccessResult::neutral();
    }

    $subsystem_config = $this->wdbDataService->getSubsystemConfig($subsysname);
    if (!$subsystem_config) {
      return AccessResult::forbidden('Subsystem configuration does not exist.');
    }

    // 1. First, determine the access result.
    $access_result = NULL;
    if ($subsystem_config->get('allowAnonymous')) {
      $access_result = AccessResult::allowed();
    }
    else {
      $access_result = AccessResult::allowedIfHasPermission($account, 'view wdb gallery pages');
    }

    // 2. Then, add the configuration as a cacheable dependency.
    // This ensures that Drupal automatically invalidates the access cache for
    // this route whenever the subsystem's configuration is changed.
    return $access_result->addCacheableDependency($subsystem_config);
  }

}
