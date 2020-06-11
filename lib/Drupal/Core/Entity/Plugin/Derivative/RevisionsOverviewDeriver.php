<?php

namespace Drupal\Core\Entity\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for the revision overview.
 */
class RevisionsOverviewDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new RevisionsOverviewDeriver instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $exclude = ['node'];

    $this->derivatives = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (in_array($entity_type_id, $exclude)) {
        continue;
      }

      if (!$entity_type->hasLinkTemplate('version-history')) {
        continue;
      }

      $this->derivatives[$entity_type_id] = [
        'route_name' => "entity.$entity_type_id.version_history",
        'title' => t('Revisions'),
        'base_route' => "entity.$entity_type_id.canonical",
        'weight' => 20,
      ] + $base_plugin_definition;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
