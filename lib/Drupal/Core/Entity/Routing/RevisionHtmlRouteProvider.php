<?php

namespace Drupal\Core\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides revision routes.
 */
class RevisionHtmlRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = new RouteCollection();
    $entity_type_id = $entity_type->id();
    if ($view_route = $this->getRevisionViewRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.revision", $view_route);
    }
    if ($view_route = $this->getRevisionRevertRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.revision_revert_form", $view_route);
    }

    if ($view_route = $this->getRevisionHistoryRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.version_history", $view_route);
    }

    return $collection;
  }

  /**
   * Gets the entity revision view route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionViewRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('revision'));
      $route->addDefaults([
        '_controller' => '\Drupal\Core\Entity\Controller\EntityViewController::viewRevision',
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
      ]);
      $route->addRequirements([
        '_entity_access_revision' => "$entity_type_id.view",
      ]);
      $route->setOption('parameters', [
        $entity_type->id() => [
          'type' => 'entity:' . $entity_type->id(),
        ],
        $entity_type->id() . '_revision' => [
          'type' => 'entity_revision:' . $entity_type->id(),
        ],
      ]);
      return $route;
    }
  }

  /**
   * Gets the entity revision revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision-revert-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('revision-revert-form'));
      $route->addDefaults([
        '_form' => '\Drupal\Core\Entity\Form\RevisionRevertForm',
        'title' => 'Revert to earlier revision',
      ]);
      $route->addRequirements([
        '_entity_access_revision' => "$entity_type_id.update",
      ]);
      $route->setOption('parameters', [
        $entity_type->id() => [
          'type' => 'entity:' . $entity_type->id(),
        ],
        $entity_type->id() . '_revision' => [
          'type' => 'entity_revision:' . $entity_type->id(),
        ],
      ]);
      $route->setOption('_admin_route', TRUE);
      return $route;
    }
  }

  /**
   * Gets the entity revision version history route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionHistoryRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('version-history')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('version-history'));
      $route->addDefaults([
        '_controller' => '\Drupal\Core\Entity\Controller\RevisionOverviewController::revisionOverviewController',
        '_title' => 'Revisions',
      ]);
      $route->setRequirement('_entity_access_revision', "$entity_type_id.list");
      $route->setOption('_admin_route', TRUE);
      $route->setOption('entity_type_id', $entity_type->id());
      $route->setOption('parameters', [
        $entity_type->id() => [
          'type' => 'entity:' . $entity_type->id(),
        ],
      ]);
      return $route;
    }
  }

}
