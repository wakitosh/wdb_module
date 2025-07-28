<?php

namespace Drupal\wdb_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles autocomplete requests for WDB forms.
 *
 * Provides a JSON response with suggestions for various entity fields based on
 * user input and the calling route.
 */
class AutocompleteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a new AutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Handles autocomplete suggestions.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function handleAutocomplete(Request $request): JsonResponse {
    $matches = [];
    $q = $request->query->get('q');
    $route_name = $request->attributes->get('_route');

    if (strlen($q) > 0) {
      // Set default target entity and field.
      $target_entity_type = 'wdb_word_unit';
      $target_field = 'realized_form';

      // Determine the target entity and field based on the route name.
      if ($route_name === 'wdb_core.autocomplete.basic_form') {
        $target_entity_type = 'wdb_word';
        $target_field = 'basic_form';
      }
      elseif ($route_name === 'wdb_core.autocomplete.sign') {
        $target_entity_type = 'wdb_sign';
        $target_field = 'sign_code';
      }

      // Build and execute the entity query.
      $query = $this->entityTypeManager->getStorage($target_entity_type)->getQuery();
      $query->condition($target_field, $q, 'CONTAINS');
      $query->groupBy($target_field);
      $query->range(0, 10);
      $ids = $query->accessCheck(FALSE)->execute();

      if (!empty($ids)) {
        $entities = $this->entityTypeManager->getStorage($target_entity_type)->loadMultiple($ids);
        $found_values = [];
        // Format the results and ensure uniqueness.
        foreach ($entities as $entity) {
          $value = $entity->get($target_field)->value;
          if (!in_array($value, $found_values)) {
            $matches[] = ['value' => $value, 'label' => $value];
            $found_values[] = $value;
          }
        }
      }
    }
    return new JsonResponse($matches);
  }

}
