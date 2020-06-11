<?php

namespace Drupal\Core\Entity\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides local tasks for the revision version_history route.
 */
class VersionHistoryDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Symfony router.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * Creates a new RevisionsOverviewDeriver instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RouterInterface $router) {
    $this->entityTypeManager = $entityTypeManager;
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('router')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->hasLinkTemplate('version-history')) {
        continue;
      }

      $version_history_route = $this->router->getRouteCollection()->get("entity.$entity_type_id.version_history");
      // set in \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider to ensure that the "Revision" Entity Local
      // Task is only added automatically if the version_history route was generated there, this essentially prevents
      // two "Revisions" tabs for Entity Types which already define their own revision routes and local task
      if ($version_history_route == NULL || $version_history_route->getOption('add_core_version_history_local_task') == NULL) {
        continue;
      }

      $this->derivatives["entity.$entity_type_id.version_history"] = [
        'route_name' => "entity.$entity_type_id.version_history",
        'title' => t('Revisions'),
        'base_route' => "entity.$entity_type_id.canonical",
        'weight' => 20,
      ] + $base_plugin_definition;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
