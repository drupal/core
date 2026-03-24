<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\argument_default;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\argument_default\Raw;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\views\Plugin\views\argument_default\Raw.
 */
#[CoversClass(Raw::class)]
#[Group('views')]
class RawTest extends UnitTestCase {

  /**
   * Tests the getArgument() method.
   *
   * @see \Drupal\views\Plugin\views\argument_default\Raw::getArgument()
   */
  public function testGetArgument(): void {
    $view = $this->createStub(ViewExecutable::class);
    $display_plugin = $this->createStub(DisplayPluginBase::class);
    $current_path = new CurrentPathStack(new RequestStack());

    $request = new Request();
    $current_path->setPath('/test/example', $request);
    $view
      ->method('getRequest')
      ->willReturn($request);
    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->never())
      ->method('getAliasByPath');

    // Don't use aliases. Check against NULL and nonexistent path component
    // values in addition to valid ones.
    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => FALSE,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals(NULL, $raw->getArgument());

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => FALSE,
      'index' => 0,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals('test', $raw->getArgument());

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => FALSE,
      'index' => 1,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals('example', $raw->getArgument());

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => FALSE,
      'index' => 2,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals(NULL, $raw->getArgument());

    // Setup an alias manager with a path alias.
    $alias_manager = $this->createMock(AliasManagerInterface::class);
    $alias_manager->expects($this->exactly(4))
      ->method('getAliasByPath')
      ->with($this->equalTo('/test/example'))
      ->willReturn('/other/example');

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => TRUE,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals(NULL, $raw->getArgument());

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => TRUE,
      'index' => 0,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals('other', $raw->getArgument());

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => TRUE,
      'index' => 1,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals('example', $raw->getArgument());

    $raw = new Raw([], 'raw', [], $alias_manager, $current_path);
    $options = [
      'use_alias' => TRUE,
      'index' => 2,
    ];
    $raw->init($view, $display_plugin, $options);
    $this->assertEquals(NULL, $raw->getArgument());
  }

}
