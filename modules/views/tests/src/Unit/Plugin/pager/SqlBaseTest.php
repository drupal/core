<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\pager;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\pager\SqlBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Drupal\views\Plugin\views\pager\SqlBase.
 */
#[CoversClass(SqlBase::class)]
#[Group('views')]
class SqlBaseTest extends UnitTestCase {

  /**
   * The mock pager plugin instance.
   *
   * @var \Drupal\views\Plugin\views\pager\SqlBase
   */
  protected $pager;

  /**
   * The mock view instance.
   *
   * @var \Drupal\views\ViewExecutable|\PHPUnit\Framework\MockObject\Stub
   */
  protected $view;

  /**
   * The mock display plugin instance.
   *
   * @var \Drupal\views\Plugin\views\display\DisplayPluginBase|\PHPUnit\Framework\MockObject\Stub
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->pager = new StubSqlBase(
      [],
      'test_plugin',
      [],
      $this->createStub(PagerManagerInterface::class),
      $this->createStub(PagerParametersInterface::class),
    );

    $this->view = $this->createStub(ViewExecutable::class);

    $query = $this->createStub(QueryPluginBase::class);

    $this->view->query = $query;

    $this->display = $this->createStub(DisplayPluginBase::class);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests the query() method.
   *
   * @see \Drupal\views\Plugin\views\pager\SqlBase::query()
   */
  public function testQuery(): void {
    $request = new Request([
      'items_per_page' => 'All',
    ]);
    $this->view
      ->method('getRequest')
      ->willReturn($request);

    $options = [];
    $this->pager->init($this->view, $this->display, $options);
    $this->pager->query();
    $this->assertSame(10, $this->pager->options['items_per_page']);

    $options = [
      'expose' => [
        'items_per_page' => TRUE,
        'items_per_page_options_all' => TRUE,
      ],
    ];
    $this->pager->init($this->view, $this->display, $options);
    $this->pager->query();
    $this->assertSame(0, $this->pager->options['items_per_page']);
  }

}
