<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\area;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\area\View as ViewAreaPlugin;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\area\View.
 */
#[CoversClass(ViewAreaPlugin::class)]
#[Group('views')]
class ViewTest extends UnitTestCase {

  /**
   * The mocked entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entityStorage;

  /**
   * The view handler.
   *
   * @var \Drupal\views\Plugin\views\area\View
   */
  protected $viewHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityStorage = $this->createStub(EntityStorageInterface::class);
    $this->viewHandler = new ViewAreaPlugin([], 'view', [], $this->entityStorage);
    $this->viewHandler->view = $this->createStub(ViewExecutable::class);
  }

  /**
   * Tests calculate dependencies.
   */
  public function testCalculateDependencies(): void {
    /** @var \Drupal\views\Entity\View $view_this */
    $view_this = $this->createStub(ViewEntityInterface::class);
    $view_this->method('getConfigDependencyKey')->willReturn('config');
    $view_this->method('getConfigDependencyName')->willReturn('view.this');
    $view_this->method('id')->willReturn('this');
    $view_other = $this->createStub(ViewEntityInterface::class);
    $view_other->method('getConfigDependencyKey')->willReturn('config');
    $view_other->method('getConfigDependencyName')->willReturn('view.other');
    $this->entityStorage
      ->method('load')
      ->willReturnMap([
        ['this', $view_this],
        ['other', $view_other],
      ]);
    $this->viewHandler->view->storage = $view_this;

    $this->viewHandler->options['view_to_insert'] = 'other:default';
    $this->assertEquals(['config' => ['view.other']], $this->viewHandler->calculateDependencies());

    $this->viewHandler->options['view_to_insert'] = 'this:default';
    $this->assertSame([], $this->viewHandler->calculateDependencies());
  }

}
