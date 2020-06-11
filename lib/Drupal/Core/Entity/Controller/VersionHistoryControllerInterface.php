<?php

namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Basic implementation required for Entity Revision routes
 */
interface VersionHistoryControllerInterface {

  /**
   * Generates an overview table of older revisions of an entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array
   *   A render array.
   */
  public function renderVersionHistory(RouteMatchInterface $route_match);

  /**
   * Generates the route title for the version_history route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Route title
   */
  public function versionHistoryTitle(RouteMatchInterface $route_match);

}