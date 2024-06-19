<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_link_content\Kernel;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Ensures that the menu tree adapts to path alias changes.
 *
 * @group menu_link_content
 * @group path
 */
class PathAliasMenuLinkContentTest extends KernelTestBase {

  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'system',
    'link',
    'path_alias',
    'test_page_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('path_alias');

    // Ensure that the weight of module_link_content is higher than system.
    // @see menu_link_content_install()
    module_set_weight('menu_link_content', 1);
  }

  /**
   * Tests the path aliasing changing.
   */
  public function testPathAliasChange(): void {
    $path_alias = $this->createPathAlias('/test-page', '/my-blog');
    $menu_link_content = MenuLinkContent::create([
      'title' => 'Menu title',
      'link' => ['uri' => 'internal:/my-blog'],
      'menu_name' => 'tools',
    ]);
    $menu_link_content->save();

    $tree = \Drupal::menuTree()->load('tools', new MenuTreeParameters());
    $this->assertEquals('test_page_test.test_page', $tree[$menu_link_content->getPluginId()]->link->getPluginDefinition()['route_name']);

    // Saving an alias should clear the alias manager cache.
    $path_alias->setPath('/test-render-title');
    $path_alias->setAlias('/my-blog');
    $path_alias->save();

    $tree = \Drupal::menuTree()->load('tools', new MenuTreeParameters());
    $this->assertEquals('test_page_test.render_title', $tree[$menu_link_content->getPluginId()]->link->getPluginDefinition()['route_name']);

    // Delete the alias.
    $path_alias->delete();
    $tree = \Drupal::menuTree()->load('tools', new MenuTreeParameters());
    $this->assertTrue(isset($tree[$menu_link_content->getPluginId()]));
    $this->assertEquals('', $tree[$menu_link_content->getPluginId()]->link->getRouteName());
    // Verify the plugin now references a path that does not match any route.
    $this->assertEquals('base:my-blog', $tree[$menu_link_content->getPluginId()]->link->getUrlObject()->getUri());
  }

}
