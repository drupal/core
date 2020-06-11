<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Routing\Route;

/**
 * Tests some integration of the revision overview:
 *
 * - Are the routes added properly.
 * - Are the local tasks added properly.
 *
 * @group Entity
 */
class RevisionOverviewIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'entity_test', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'router');

    \Drupal::service('router.builder')->rebuild();
  }

  public function testIntegration() {
    /** @var \Drupal\Core\Menu\LocalTaskManagerInterface $local_tasks_manager */
    $local_tasks_manager = \Drupal::service('plugin.manager.menu.local_task');

    $tasks = $local_tasks_manager->getDefinitions();
    $this->assertArrayHasKey('entity.revisions_overview:entity_test_rev', $tasks);
    $this->assertArrayNotHasKey('entity.revisions_overview:node', $tasks, 'Node should have been excluded because it provides their own');

    $this->assertEquals('entity.entity_test_rev.version_history', $tasks['entity.revisions_overview:entity_test_rev']['route_name']);
    $this->assertEquals('entity.entity_test_rev.canonical', $tasks['entity.revisions_overview:entity_test_rev']['base_route']);

    /** @var \Drupal\Core\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');

    $route = $route_provider->getRouteByName('entity.entity_test_rev.version_history');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('\Drupal\Core\Entity\Controller\RevisionOverviewController::revisionOverviewController', $route->getDefault('_controller'));
  }

}
