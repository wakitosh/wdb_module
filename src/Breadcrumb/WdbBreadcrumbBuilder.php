<?php

namespace Drupal\wdb_core\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a custom breadcrumb builder for WDB gallery pages.
 */
class WdbBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected TitleResolverInterface $titleResolver;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new WdbBreadcrumbBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TitleResolverInterface $title_resolver, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->titleResolver = $title_resolver;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // This breadcrumb builder should only apply to the main gallery page route.
    return $route_match->getRouteName() == 'wdb_core.gallery_page';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $source_identifier = $route_match->getParameter('source');

    // Load the WdbSource entity from the source_identifier route parameter.
    $source_storage = $this->entityTypeManager->getStorage('wdb_source');
    $sources = $source_storage->loadByProperties(['source_identifier' => $source_identifier]);

    if ($source_entity = reset($sources)) {
      /** @var \Drupal\wdb_core\Entity\WdbSource $source_entity */
      // Add a link to the source document's canonical page.
      $breadcrumb->addLink(Link::createFromRoute($source_entity->label(), 'entity.wdb_source.canonical', ['wdb_source' => $source_entity->id()]));
    }

    // Add the current page's title as the last, non-linked breadcrumb item.
    $request = $this->requestStack->getCurrentRequest();
    $title = $this->titleResolver->getTitle($request, $route_match->getRouteObject());
    if ($title) {
      $breadcrumb->addLink(Link::createFromRoute($title, '<none>'));
    }

    // This breadcrumb depends on the URL path; add cache context.
    $breadcrumb->addCacheContexts(['url.path']);

    return $breadcrumb;
  }

}
