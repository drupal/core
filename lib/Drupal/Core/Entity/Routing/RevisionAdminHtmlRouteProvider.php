<?php

namespace Drupal\Core\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides HTML routes for entities with administrative version_history and revision_revert_form pages.
 *
 * Use this class if the revision routes should use the
 * administrative theme.
 *
 * @see \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider.
 */
class RevisionAdminHtmlRouteProvider extends RevisionHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getVersionHistoryRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getVersionHistoryRoute($entity_type)) {
      $route->setOption('_admin_route', TRUE);
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getRevisionRevertRoute($entity_type)) {
      $route->setOption('_admin_route', TRUE);
      return $route;
    }
  }

}