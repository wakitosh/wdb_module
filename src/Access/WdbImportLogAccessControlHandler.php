<?php

namespace Drupal\wdb_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Access control handler for WdbImportLog.
 */
class WdbImportLogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Allow view operations as per parent permissions.
    if ($operation === 'view') {
      return parent::checkAccess($entity, $operation, $account);
    }

    if ($operation === 'delete') {
      // Allow delete when rolled back OR there are zero created entities.
      if ($entity instanceof ContentEntityInterface) {
        $status = (bool) $entity->get('status')->value;
        $created_entities_json = $entity->get('created_entities')->value;
        $created_entities = !empty($created_entities_json) ? json_decode($created_entities_json, TRUE) : [];

        $is_rolled_back = ($status === FALSE);
        $has_zero_created = empty($created_entities);

        return AccessResult::allowedIf($is_rolled_back || $has_zero_created)
          ->andIf(AccessResult::allowedIfHasPermission($account, 'administer wdb import logs'))
          ->cachePerPermissions()
          ->addCacheableDependency($entity);
      }
      return AccessResult::forbidden();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
