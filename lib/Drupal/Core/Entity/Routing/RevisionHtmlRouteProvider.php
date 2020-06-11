<?php

namespace Drupal\Core\Entity\Routing;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\Controller\VersionHistoryControllerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\NoEntityRevisionRevertFormException;
use mysql_xdevapi\Exception;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides revision routes.
 *
 * @todo Temporary workaround until https://www.drupal.org/project/drupal/issues/2976861 is implemented.
 */
class RevisionHtmlRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = new RouteCollection();
    $entity_type_id = $entity_type->id();

    if ($version_history_route = $this->getVersionHistoryRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.version_history", $version_history_route);
    }

    if ($revision_route = $this->getRevisionViewRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.revision", $revision_route);
    }

    if ($revision_revert_route = $this->getRevisionRevertRoute($entity_type)) {
      $collection->add("entity.$entity_type_id.revision_revert_form", $revision_revert_route);
    }

    return $collection;
  }

  /**
   * Gets the entity revision version history route.
   *
   * @param EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return Route|null
   *   The generated route, if available.
   */
  protected function getVersionHistoryRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('version-history') && $version_history_class = $this->getHandlerClassWithImplementationChecks($entity_type, 'version_history', VersionHistoryControllerInterface::class)) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('version-history'));
      $route->addDefaults([
        '_controller' => "$version_history_class::renderVersionHistory",
        '_title_callback' => "$version_history_class::versionHistoryTitle",
      ]);
      $route->setRequirement('_entity_access_revision', "$entity_type_id.list");
      $route->setOption('entity_type_id', $entity_type->id());
      $route->setOption('parameters', [
        $entity_type->id() => [
          'type' => 'entity:' . $entity_type->id(),
        ],
      ]);

      // used in \Drupal\Core\Entity\Plugin\Derivative\VersionHistoryDeriver to ensure that the "Revision" Entity Local
      // Task is only added automatically if the version_history route was generated here, this essentially prevents
      // two "Revisions" tabs for Entity Types which already define their own revision routes and local task
      $route->setOption('add_core_version_history_local_task', TRUE);

      return $route;
    }
  }

  /**
   * Gets the entity revision view route.
   *
   * @param EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return Route|null
   *   The generated route, if available.
   */
  protected function getRevisionViewRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('revision'));
      $route->addDefaults([
        '_controller' => EntityViewController::class . '::viewRevision',
        '_title_callback' => EntityController::class . '::title',
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
   * @param EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return Route|null
   *   The generated route, if available.
   *
   * @throws NoEntityRevisionRevertFormException
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entity_type) {
    $revert_form_class = $entity_type->getFormClass('revision_revert');
    if ($revert_form_class == NULL) {
      throw new NoEntityRevisionRevertFormException('The revision form for '. $entity_type->getLabel() . ' could not be found.');
    }
    else {
      if ($entity_type->hasLinkTemplate('revision-revert-form')) {
        $entity_type_id = $entity_type->id();
        $route = new Route($entity_type->getLinkTemplate('revision-revert-form'));
        $route->addDefaults([
          '_form' => $revert_form_class,
          '_title' => 'Revert to earlier revision',
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
        return $route;
      }
    }
  }

  /**
   * Gets specified handler class name and performs compliance checks.
   *
   * @param EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $handler
   *   The name of the handler key.
   * @param string $implementing_class
   *   The name of the class to check if the handler class implements.
   *
   * @return string|null
   *   The name of the handler class or null if no handler class was defined
   *
   * @throws \RuntimeException
   *   Thrown if the handler class is not a instance of the implementing class.
   */
  private function getHandlerClassWithImplementationChecks(EntityTypeInterface $entity_type, $handler, $implementing_class) {
    if (!$entity_type->hasHandlerClass($handler)) {
      return NULL;
    }
    $handler_class = $entity_type->getHandlerClass($handler);
    $implementing_classes = class_implements($handler_class);
    if (in_array($implementing_class, $implementing_classes)) {
      return $handler_class;
    }
    else {
      throw new \RuntimeException("Attempted to get the $handler Handler Class for Entity Type {$entity_type->id()} however the class specified $handler_class is not an instance of $implementing_class");
    }
  }

}
