<?php

namespace Drupal\wdb_cantaloupe_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\WriteSafeSessionHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\wdb_core\Service\WdbDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the Cantaloupe IIIF Server authorization endpoint.
 *
 * This controller handles delegate script requests from Cantaloupe to authorize
 * access to IIIF images based on the user's Drupal session and permissions.
 */
class AuthController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The write-safe session handler.
   *
   * @var \Drupal\Core\Session\WriteSafeSessionHandlerInterface
   */
  protected $sessionHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The WDB data service.
   *
   * @var \Drupal\wdb_core\Service\WdbDataService
   */
  protected WdbDataService $wdbDataService;

  /**
   * Constructs a new AuthController object.
   *
   * @param \Drupal\Core\Session\WriteSafeSessionHandlerInterface $session_handler
   *   The write-safe session handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\wdb_core\Service\WdbDataService $wdbDataService
   *   The WDB data service.
   */
  public function __construct(WriteSafeSessionHandlerInterface $session_handler, ConfigFactoryInterface $config_factory, WdbDataService $wdbDataService) {
    $this->sessionHandler = $session_handler;
    $this->configFactory = $config_factory;
    $this->wdbDataService = $wdbDataService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session_handler.write_safe'),
      $container->get('config.factory'),
      $container->get('wdb_core.data_service')
    );
  }

  /**
   * Authorizes a request from the Cantaloupe delegate script.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating whether the request is authorized.
   */
  public function authorizeRequest(Request $request): JsonResponse {
    $logger = $this->getLogger('wdb_cantaloupe_auth');
    $payload = json_decode($request->getContent(), TRUE);
    $identifier = $payload['identifier'] ?? '';
    $parts = explode('/', $identifier);
    $subsysname = $parts[1] ?? NULL;

    if (!$subsysname) {
      $logger->warning('Subsystem not identified in identifier: @id', ['@id' => $identifier]);
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'Subsystem not identified.']);
    }

    $subsystem_config = $this->wdbDataService->getSubsystemConfig($subsysname);
    if (!$subsystem_config) {
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'Subsystem configuration not found.'], 404);
    }

    if ($subsystem_config->get('allowAnonymous')) {
      return new JsonResponse(['authorized' => TRUE, 'reason' => 'Subsystem allows anonymous access.']);
    }

    $cookies = $payload['cookies'] ?? [];
    $session_cookie_name = session_name();
    $session_id = NULL;
    foreach ($cookies as $cookie_string) {
      if (strpos($cookie_string, $session_cookie_name) === 0) {
        [, $session_id] = explode('=', $cookie_string, 2);
        break;
      }
    }

    if (!$session_id) {
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'No session cookie found.']);
    }

    $session = $this->sessionHandler->read($session_id);
    if (empty($session)) {
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'Invalid session.']);
    }

    $session_data = $this->decodeSessionData($session);
    // The user ID is nested inside the '_sf2_attributes' key.
    $uid = $session_data['_sf2_attributes']['uid'] ?? 0;

    if ($uid <= 0) {
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'Anonymous user session.']);
    }

    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
    if ($user && $user->hasPermission('view wdb gallery pages')) {
      return new JsonResponse(['authorized' => TRUE, 'reason' => 'User has permission.']);
    }

    $logger->warning('Authorization denied for @subsys (user @uid lacks permission).', ['@uid' => $uid, '@subsys' => ($subsysname ?? 'N/A')]);
    return new JsonResponse(['authorized' => FALSE, 'reason' => 'User lacks permission.']);
  }

  /**
   * Decodes Drupal's session data string.
   *
   * This handles the specific format used by Symfony's NativeFileSessionHandler,
   * where keys are separated from values by a pipe character.
   *
   * @param string $session_string
   *   The raw session data string.
   *
   * @return array
   *   The decoded session data as an associative array.
   */
  private function decodeSessionData(string $session_string): array {
    $data = [];
    // Use a regular expression to split the session string into key-value pairs,
    // accounting for the leading underscore on some keys.
    preg_match_all('/(_sf2_attributes|_sf2_meta)\|(s:\d+:".*?"|a:\d+:{.*?}|i:\d+;)/', $session_string, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      $key = $match[1];
      $value = $match[2];
      $unserialized_value = @unserialize($value);
      if ($unserialized_value !== FALSE || $value === 'b:0;') {
        $data[$key] = $unserialized_value;
      }
    }
    return $data;
  }

}
