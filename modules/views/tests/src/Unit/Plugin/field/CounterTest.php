<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\Counter;
use Drupal\views\Plugin\views\pager\Full;
use Drupal\views\Plugin\ViewsPluginManager;
use Drupal\views\ResultRow;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\field\Counter.
 */
#[CoversClass(Counter::class)]
#[Group('views')]
class CounterTest extends UnitTestCase {

  /**
   * The pager plugin instance.
   *
   * @var \Drupal\views\Plugin\views\pager\PagerPluginBase
   */
  protected $pager;

  /**
   * The view executable.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * The display plugin instance.
   *
   * @var \Drupal\views\Plugin\views\display\DisplayPluginBase
   */
  protected $display;


  /**
   * Stores the test data.
   *
   * @var array
   */
  protected $testData = [];

  /**
   * The handler definition of the counter field.
   *
   * @var array
   */
  protected $definition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup basic stuff like the view and the display.
    $config = [];
    $config['display']['default'] = [
      'id' => 'default',
      'display_plugin' => 'default',
      'display_title' => 'Default',
    ];

    $storage = new View($config, 'view');
    $user = $this->createStub(AccountInterface::class);
    $views_data = $this->createStub(ViewsData::class);
    $route_provider = $this->createStub(RouteProviderInterface::class);
    $display_plugin_manager = $this->createStub(ViewsPluginManager::class);
    $this->view = new ViewExecutable($storage, $user, $views_data, $route_provider, $display_plugin_manager);

    $this->display = $this->createStub(DisplayPluginBase::class);

    $this->pager = new Full(
      [],
      'test_plugin',
      [],
      $this->createStub(PagerManagerInterface::class),
      $this->createStub(PagerParametersInterface::class),
    );

    $this->view->display_handler = $this->display;
    $this->view->pager = $this->pager;

    foreach (ViewTestData::dataSet() as $index => $set) {
      $this->testData[] = new ResultRow($set + ['index' => $index]);
    }

    $this->definition = ['title' => 'counter field', 'plugin_type' => 'field'];
  }

  /**
   * Provides some row index to test.
   *
   * @return array
   *   Returns an array of row index to test.
   */
  public static function providerRowIndexes(): array {
    return [
      [0],
      [1],
      [2],
    ];
  }

  /**
   * Tests a simple counter field.
   */
  #[DataProvider('providerRowIndexes')]
  public function testSimpleCounter(int $i): void {
    $counter_handler = new Counter([], 'counter', $this->definition);
    $options = [];
    $counter_handler->init($this->view, $this->display, $options);

    $this->view->row_index = $i;
    $expected = $i + 1;

    $counter = $counter_handler->getValue($this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the counter number');
    $counter = $this->renderCounter($counter_handler, $this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the rendered number');
  }

  /**
   * Tests a counter with a random start.
   *
   * @param int $i
   *   The row index to test.
   */
  #[DataProvider('providerRowIndexes')]
  public function testCounterRandomStart(int $i): void {
    // Setup a counter field with a random start.
    $rand_start = rand(5, 10);
    $counter_handler = new Counter([], 'counter', $this->definition);
    $options = [
      'counter_start' => $rand_start,
    ];
    $counter_handler->init($this->view, $this->display, $options);

    $this->view->row_index = $i;
    $expected = $rand_start + $i;

    $counter = $counter_handler->getValue($this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the counter number');
    $counter = $this->renderCounter($counter_handler, $this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the rendered number');
  }

  /**
   * Tests a counter field with a random pager offset.
   *
   * @param int $i
   *   The row index to test.
   */
  #[DataProvider('providerRowIndexes')]
  public function testCounterRandomPagerOffset(int $i): void {
    // Setup a counter field with a pager with a random offset.
    $offset = 3;
    $this->pager->setOffset($offset);

    $rand_start = rand(5, 10);
    $counter_handler = new Counter([], 'counter', $this->definition);
    $options = [
      'counter_start' => $rand_start,
    ];
    $counter_handler->init($this->view, $this->display, $options);

    $this->view->row_index = $i;
    $expected = $offset + $rand_start + $i;

    $counter = $counter_handler->getValue($this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the counter number');
    $counter = $this->renderCounter($counter_handler, $this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the rendered number');
  }

  /**
   * Tests a counter field on the second page.
   *
   * @param int $i
   *   The row index to test.
   */
  #[DataProvider('providerRowIndexes')]
  public function testCounterSecondPage(int $i): void {
    $offset = 3;
    // Setup a pager on the second page.
    $this->pager->setOffset($offset);
    $items_per_page = 5;
    $this->pager->setItemsPerPage($items_per_page);
    $current_page = 1;
    $this->pager->setCurrentPage($current_page);

    $rand_start = rand(5, 10);
    $counter_handler = new Counter([], 'counter', $this->definition);
    $options = [
      'counter_start' => $rand_start,
    ];
    $counter_handler->init($this->view, $this->display, $options);

    $this->view->row_index = $i;
    $expected = $items_per_page + $offset + $rand_start + $i;

    $counter = $counter_handler->getValue($this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the counter number');
    $counter = $this->renderCounter($counter_handler, $this->testData[$i]);
    $this->assertEquals($expected, $counter, 'The expected number matches with the rendered number');
  }

  /**
   * Renders the counter field handler.
   *
   * @param \Drupal\views\Plugin\views\field\Counter $handler
   *   The counter handler.
   * @param \Drupal\views\ResultRow $row
   *   A result row.
   *
   * @return string
   *   The counter rendered markup.
   */
  protected function renderCounter(Counter $handler, ResultRow $row): string|MarkupInterface|null {
    $markup = $handler->render($row);
    $handler->postRender($row, $markup);
    return $handler->last_render;
  }

}
