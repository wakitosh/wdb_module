<?php

namespace Drupal\wdb_cantaloupe_auth\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
// Use the native \SessionHandlerInterface for read() access (no use import).
use Drupal\wdb_core\Service\WdbDataService;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the Cantaloupe IIIF Server authorization endpoint.
 */
class AuthController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The write-safe session handler.
   *
   * @var \SessionHandlerInterface
   */
  protected \SessionHandlerInterface $sessionHandler;

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a new AuthController object.
   */
  public function __construct(\SessionHandlerInterface $session_handler, ConfigFactoryInterface $config_factory, WdbDataService $wdbDataService, Connection $database) {
    $this->sessionHandler = $session_handler;
    $this->configFactory = $config_factory;
    $this->wdbDataService = $wdbDataService;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session_handler.write_safe'),
      $container->get('config.factory'),
      $container->get('wdb_core.data_service'),
      $container->get('database')
    );
  }

  /**
   * Authorizes a request from the Cantaloupe delegate script.
   */
  public function authorizeRequest(Request $request): JsonResponse {
    $logger = $this->getLogger('wdb_cantaloupe_auth');
    $payload = json_decode($request->getContent(), TRUE) ?? [];
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

    // Build cookie map from payload cookies.
    $cookies = $payload['cookies'] ?? [];
    $cookie_map = [];
    foreach ($cookies as $cookie_string) {
      $pairs = preg_split('/;\s*/', (string) $cookie_string);
      foreach ($pairs as $pair) {
        if (strpos($pair, '=') !== FALSE) {
          [$name, $value] = explode('=', $pair, 2);
          if ($name !== '' && !array_key_exists($name, $cookie_map)) {
            $cookie_map[$name] = $value;
          }
        }
      }
    }

    // Candidate session IDs: session_name() first, then any SESS*/SSESS*.
    $session_cookie_name = session_name();
    $session_ids = [];
    if (isset($cookie_map[$session_cookie_name])) {
      $session_ids[] = trim(urldecode($cookie_map[$session_cookie_name]));
    }
    foreach ($cookie_map as $name => $value) {
      if (preg_match('/^S?SESS[0-9a-f]+$/i', $name)) {
        $sid = trim(urldecode($value));
        if ($sid !== '' && !in_array($sid, $session_ids, TRUE)) {
          $session_ids[] = $sid;
        }
      }
    }

    if (empty($session_ids)) {
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'No session cookie found.']);
    }

    // Resolve UID by trying each SID until one succeeds.
    $uid = 0;
    foreach ($session_ids as $sid) {
      $uid = (int) ($this->getUidFromSessionId($sid) ?? 0);
      if ($uid > 0) {
        break;
      }
    }

    if ($uid <= 0) {
      return new JsonResponse(['authorized' => FALSE, 'reason' => 'Anonymous user session.']);
    }

    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
    if ($user instanceof UserInterface && $user->hasPermission('view wdb gallery pages')) {
      return new JsonResponse(['authorized' => TRUE, 'reason' => 'User has permission.']);
    }

    $logger->warning('Authorization denied for @subsys (user @uid lacks permission).', [
      '@uid' => $uid,
      '@subsys' => ($subsysname ?? 'N/A'),
    ]);
    return new JsonResponse(['authorized' => FALSE, 'reason' => 'User lacks permission.']);
  }

  /**
   * Resolve a UID from a PHP session ID via the {sessions} table.
   */
  private function getUidFromSessionId(string $session_id): ?int {
    // 1) Read via the configured session handler (DB/Redis, etc.).
    try {
      $data = $this->sessionHandler->read($session_id);
      if (is_string($data) && $data !== '') {
        $uid = $this->extractUidFromSessionData($data);
        if ($uid !== NULL && $uid > 0) {
          return $uid;
        }
      }
    }
    catch (\Throwable $e) {
      // Swallow and try DB fallback.
    }

    // 2) Fallback: query the {sessions} table if available.
    try {
      $row = $this->database->select('sessions', 's')
        ->fields('s', ['uid', 'session'])
        ->condition('sid', $session_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($row) {
        if (!empty($row['uid'])) {
          return (int) $row['uid'];
        }
        $sessionData = $row['session'] ?? '';
        if (is_resource($sessionData)) {
          $sessionData = stream_get_contents($sessionData) ?: '';
        }
        $sessionData = (string) $sessionData;
        $uid = $this->extractUidFromSessionData($sessionData);
        if ($uid !== NULL && $uid > 0) {
          return $uid;
        }
      }
    }
    catch (\Throwable $e) {
      // Ignore and return NULL.
    }
    return NULL;
  }

  /**
   * Extracts uid from a serialized PHP session payload.
   */
  private function extractUidFromSessionData(string $sessionData): ?int {
    // Common patterns:
    // - Native PHP session serializer: uid|i:123;.
    if (preg_match('/(^|;)\s*uid\|i:(\d+);/', $sessionData, $m)) {
      return (int) $m[2];
    }
    // - uid stored as string (rare but possible): uid|s:\d+:"123";
    if (preg_match('/(^|;)\s*uid\|s:\d+:"(\d+)";/', $sessionData, $m)) {
      return (int) $m[2];
    }
    // - Inside a serialized array/bag: "uid";i:123;
    if (preg_match('/"uid";i:(\d+);/', $sessionData, $m)) {
      return (int) $m[1];
    }
    // - Inside a serialized array with uid as string: "uid";s:\d+:"123";
    if (preg_match('/"uid";s:\d+:"(\d+)";/', $sessionData, $m)) {
      return (int) $m[1];
    }
    return NULL;
  }

}
