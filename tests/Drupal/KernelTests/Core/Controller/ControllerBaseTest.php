<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\system_test\Controller\BrokenSystemTestController;
use Drupal\system_test\Controller\SystemTestController;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;

/**
 * Tests \Drupal\Core\Controller\ControllerBase.
 *
 * @coversDefaultClass \Drupal\Core\Controller\ControllerBase
 * @group Controller
 */
class ControllerBaseTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system_test', 'system'];

  /**
   * @covers ::create
   */
  public function testCreate(): void {
    /** @var \Drupal\system_test\Controller\SystemTestController $controller */
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(SystemTestController::class);

    $property = new \ReflectionProperty(SystemTestController::class, 'lock');
    $this->assertSame($this->container->get('lock'), $property->getValue($controller));

    $property = new \ReflectionProperty(SystemTestController::class, 'persistentLock');
    $this->assertSame($this->container->get('lock.persistent'), $property->getValue($controller));

    $property = new \ReflectionProperty(SystemTestController::class, 'currentUser');
    $this->assertSame($this->container->get('current_user'), $property->getValue($controller));

    // Test nullables types.
    $this->assertSame($this->container->get('page_cache_kill_switch'), $controller->killSwitch);
    $this->assertSame($this->container->get('page_cache_kill_switch'), $controller->killSwitch2);
  }

  /**
   * @covers ::create
   */
  public function testCreateException(): void {
    $this->expectException(AutowiringFailedException::class);
    $this->expectExceptionMessage('Cannot autowire service "Drupal\Core\Lock\LockBackendInterface": argument "$lock" of method "Drupal\system_test\Controller\BrokenSystemTestController::_construct()", you should configure its value explicitly.');
    $this->container->get('class_resolver')->getInstanceFromDefinition(BrokenSystemTestController::class);
  }

}
