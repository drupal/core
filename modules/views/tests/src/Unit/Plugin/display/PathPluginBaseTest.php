<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\display;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\Plugin\views\display\PathPluginBase;
use Drupal\views\Plugin\ViewsPluginManager;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests Drupal\views\Plugin\views\display\PathPluginBase.
 */
#[CoversClass(PathPluginBase::class)]
#[Group('views')]
class PathPluginBaseTest extends UnitTestCase {

  /**
   * The route provider that should be used.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $routeProvider;

  /**
   * The tested path plugin base.
   *
   * @var \Drupal\views\Plugin\views\display\PathPluginBase
   */
  protected $pathPlugin;

  /**
   * The mocked views access plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsPluginManager|\PHPUnit\Framework\MockObject\Stub
   */
  protected $accessPluginManager;

  /**
   * The mocked key value storage.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->routeProvider = $this->createStub(RouteProviderInterface::class);
    $this->state = $this->createStub(StateInterface::class);
    $this->pathPlugin = new PathPluginBaseStub([], 'path_base', [], $this->routeProvider, $this->state);
    $this->setupContainer();
  }

  /**
   * Setup access plugin manager and config factory in the Drupal class.
   */
  public function setupContainer(): void {
    $this->accessPluginManager = $this->createStub(ViewsPluginManager::class);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.views.access', $this->accessPluginManager);

    $config = [
      'views.settings' => [
        'display_extenders' => [],
      ],
    ];

    $container->set('config.factory', $this->getConfigFactoryStub($config));

    $language = $this->createStub(LanguageInterface::class);
    $language
      ->method('getId')
      ->willReturn('nl');

    $language_manager = $this->createStub(LanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn($language);
    $container->set('language_manager', $language_manager);

    $cache = $this->createStub(CacheBackendInterface::class);
    $cache
      ->method('get')
      ->willReturn([]);
    $container->set('cache.data', $cache);

    \Drupal::setContainer($container);
  }

  /**
   * Initializes a service locator as a mock object.
   */
  protected function setUpMockServiceLocator(): void {
    $locator = $this->createMock(ServiceLocator::class);
    $locator->expects($this->once())
      ->method('get')
      ->with('access')
      ->willReturn($this->accessPluginManager);
    \Drupal::getContainer()->set('views.plugin_managers', $locator);
  }

  /**
   * Tests the collectRoutes method.
   *
   * @see \Drupal\views\Plugin\views\display\PathPluginBase::collectRoutes()
   */
  public function testCollectRoutes(): void {
    $this->setUpMockServiceLocator();
    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $collection = new RouteCollection();
    $result = $this->pathPlugin->collectRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'view.test_id.page_1'], $result);

    $route = $collection->get('view.test_id.page_1');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertFalse($route->getOption('returns_response'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the collectRoutes method with a display returning a response.
   *
   * @see \Drupal\views\Plugin\views\display\PathPluginBase::collectRoutes()
   */
  public function testCollectRoutesWithDisplayReturnResponse(): void {
    $this->setUpMockServiceLocator();
    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin = new PathPluginBaseStub([], 'path_base', ['returns_response' => TRUE], $this->routeProvider, $this->state);
    $this->pathPlugin->initDisplay($view, $display);

    $collection = new RouteCollection();
    $this->pathPlugin->collectRoutes($collection);
    $route = $collection->get('view.test_id.page_1');
    $this->assertTrue($route->getOption('returns_response'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the collectRoutes method with arguments.
   *
   * @see \Drupal\views\Plugin\views\display\PathPluginBase::collectRoutes()
   */
  public function testCollectRoutesWithArguments(): void {
    $this->setUpMockServiceLocator();
    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route/%/example',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $collection = new RouteCollection();
    $result = $this->pathPlugin->collectRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'view.test_id.page_1'], $result);

    $route = $collection->get('view.test_id.page_1');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals(['arg_0' => 'arg_0'], $route->getOption('_view_argument_map'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the collectRoutes method with arguments not specified in the path.
   *
   * @see \Drupal\views\Plugin\views\display\PathPluginBase::collectRoutes()
   */
  public function testCollectRoutesWithArgumentsNotSpecifiedInPath(): void {
    $this->setUpMockServiceLocator();
    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_with_arguments',
    ];
    $display['display_options']['arguments'] = [
      'test_id' => [],
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $collection = new RouteCollection();
    $result = $this->pathPlugin->collectRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'view.test_id.page_1'], $result);

    $route = $collection->get('view.test_id.page_1');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals(['arg_0' => 'arg_0'], $route->getOption('_view_argument_map'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the collect routes method with an alternative route name in the UI.
   */
  public function testCollectRoutesWithSpecialRouteName(): void {
    $this->setUpMockServiceLocator();
    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
      'route_name' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $collection = new RouteCollection();
    $result = $this->pathPlugin->collectRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'test_route'], $result);

    $route = $collection->get('test_route');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the alter route method.
   */
  public function testAlterRoute(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $collection->add('test_route', new Route('test_route', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']));
    $route_2 = new Route('test_route/example', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']);
    $collection->add('test_route_2', $route_2);

    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'test_route'], $view_route_names);

    // Ensure that the test_route is overridden.
    $route = $collection->get('test_route');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));

    // Ensure that the test_route_2 is not overridden.
    $route = $collection->get('test_route_2');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertFalse($route->hasDefault('view_id'));
    $this->assertFalse($route->hasDefault('display_id'));
    $this->assertSame($collection->get('test_route_2'), $route_2);
  }

  /**
   * Tests the altering of a REST route.
   */
  public function testAlterPostRestRoute(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $route = new Route('test_route', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']);
    $route->setMethods(['POST']);
    $collection->add('test_route', $route);

    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $this->pathPlugin->collectRoutes($collection);
    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals([], $view_route_names);

    // Ensure that the test_route is not overridden.
    $this->assertCount(2, $collection);
    $route = $collection->get('test_route');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertFalse($route->hasDefault('view_id'));
    $this->assertFalse($route->hasDefault('display_id'));
    $this->assertSame($collection->get('test_route'), $route);

    $route = $collection->get('view.test_id.page_1');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the altering of a REST route.
   */
  public function testGetRestRoute(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $route = new Route('test_route', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']);
    $route->setMethods(['GET']);
    $route->setRequirement('_format', 'json');
    $collection->add('test_route', $route);

    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $this->pathPlugin->collectRoutes($collection);
    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals([], $view_route_names);

    // Ensure that the test_route is not overridden.
    $this->assertCount(2, $collection);
    $route = $collection->get('test_route');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertFalse($route->hasDefault('view_id'));
    $this->assertFalse($route->hasDefault('display_id'));
    $this->assertSame($collection->get('test_route'), $route);

    $route = $collection->get('view.test_id.page_1');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the alter route method with preexisting title callback.
   */
  public function testAlterRouteWithAlterCallback(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $collection->add('test_route', new Route(
      'test_route',
      [
        '_controller' => 'Drupal\Tests\Core\Controller\TestController::content',
        '_title_callback' => '\Drupal\Tests\views\Unit\Plugin\display\TestController::testTitle',
      ]));
    $route_2 = new Route('test_route/example', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']);
    $collection->add('test_route_2', $route_2);

    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'test_route'], $view_route_names);

    // Ensure that the test_route is overridden.
    $route = $collection->get('test_route');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('\Drupal\Tests\views\Unit\Plugin\display\TestController::testTitle', $route->getDefault('_title_callback'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));

    // Ensure that the test_route_2 is not overridden.
    $route = $collection->get('test_route_2');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertFalse($route->hasDefault('view_id'));
    $this->assertFalse($route->hasDefault('display_id'));
    $this->assertSame($collection->get('test_route_2'), $route_2);
  }

  /**
   * Tests the collectRoutes method with a path containing named parameters.
   *
   * @see \Drupal\views\Plugin\views\display\PathPluginBase::collectRoutes()
   */
  public function testCollectRoutesWithNamedParameters(): void {
    $this->setUpMockServiceLocator();

    /** @var \Drupal\views\ViewExecutable|\PHPUnit\Framework\MockObject\MockObject $view */
    [$view] = $this->setupViewExecutableAccessPlugin();

    $view->argument = [];
    $view->argument['nid'] = $this->createStub(ArgumentPluginBase::class);

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route/%node/example',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $collection = new RouteCollection();
    $result = $this->pathPlugin->collectRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'view.test_id.page_1'], $result);

    $route = $collection->get('view.test_id.page_1');
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('/test_route/{node}/example', $route->getPath());
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
    $this->assertEquals(['arg_0' => 'node'], $route->getOption('_view_argument_map'));
  }

  /**
   * Tests altering routes with parameters in the overridden route.
   */
  public function testAlterRoutesWithParameters(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $collection->add('test_route', new Route('test_route/{parameter}', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']));

    [$view] = $this->setupViewExecutableAccessPlugin();

    // Manually set up an argument handler.
    $view->argument['test_id'] = $this->createStub(ArgumentPluginBase::class);

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route/%',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'test_route'], $view_route_names);

    // Ensure that the test_route is overridden.
    $route = $collection->get('test_route');
    $this->assertInstanceOf('\Symfony\Component\Routing\Route', $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    // Ensure that the path did not changed and placeholders are respected.
    $this->assertEquals('/test_route/{parameter}', $route->getPath());
    $this->assertEquals(['arg_0' => 'parameter'], $route->getOption('_view_argument_map'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests altering routes with parameters and upcasting information.
   */
  public function testAlterRoutesWithParametersAndUpcasting(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $collection->add('test_route', new Route('test_route/{parameter}', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content'], [], ['parameters' => ['taxonomy_term' => 'entity:entity_test']]));

    [$view] = $this->setupViewExecutableAccessPlugin();

    // Manually set up an argument handler.
    $view->argument['test_id'] = $this->createStub(ArgumentPluginBase::class);

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route/%',
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'test_route'], $view_route_names);

    // Ensure that the test_route is overridden.
    $route = $collection->get('test_route');
    $this->assertInstanceOf('\Symfony\Component\Routing\Route', $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    $this->assertEquals(['taxonomy_term' => 'entity:entity_test'], $route->getOption('parameters'));
    // Ensure that the path did not changed and placeholders are respected  kk.
    $this->assertEquals('/test_route/{parameter}', $route->getPath());
    $this->assertEquals(['arg_0' => 'parameter'], $route->getOption('_view_argument_map'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests altering routes with optional parameters in the overridden route.
   */
  public function testAlterRoutesWithOptionalParameters(): void {
    $this->setUpMockServiceLocator();

    $collection = new RouteCollection();
    $collection->add('test_route', new Route('test_route/{parameter}', ['_controller' => 'Drupal\Tests\Core\Controller\TestController::content']));

    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route/%',
    ];
    $display['display_options']['arguments'] = [
      'test_id' => [],
      'test_id2' => [],
    ];
    $this->pathPlugin->initDisplay($view, $display);

    $view_route_names = $this->pathPlugin->alterRoutes($collection);
    $this->assertEquals(['test_id.page_1' => 'test_route'], $view_route_names);

    // Ensure that the test_route is overridden.
    $route = $collection->get('test_route');
    $this->assertInstanceOf('\Symfony\Component\Routing\Route', $route);
    $this->assertEquals('test_id', $route->getDefault('view_id'));
    $this->assertEquals('page_1', $route->getDefault('display_id'));
    // Ensure that the path did not changed and placeholders are respected.
    $this->assertEquals('/test_route/{parameter}/{arg_1}', $route->getPath());
    $this->assertEquals(['arg_0' => 'parameter'], $route->getOption('_view_argument_map'));
    $this->assertEquals('Drupal\views\Routing\ViewPageController::getTitle', $route->getDefault('_title_callback'));
  }

  /**
   * Tests the getRouteName method.
   */
  public function testGetRouteName(): void {
    [$view] = $this->setupViewExecutableAccessPlugin();

    $display = [];
    $display['display_plugin'] = 'page';
    $display['id'] = 'page_1';
    $display['display_options'] = [
      'path' => 'test_route',
    ];
    $this->pathPlugin->initDisplay($view, $display);
    $route_name = $this->pathPlugin->getRouteName();
    // Ensure that the expected route name is returned.
    $this->assertEquals('view.test_id.page_1', $route_name);
  }

  /**
   * Returns some mocked view entity, view executable, and access plugin.
   */
  protected function setupViewExecutableAccessPlugin(): array {
    $view_entity = $this->createStub(View::class);
    $view_entity
      ->method('id')
      ->willReturn('test_id');
    $view_entity
      ->method('getCacheTags')
      ->willReturn([]);

    $view = $this->createStub(ViewExecutable::class);

    $view->storage = $view_entity;

    $access_plugin = $this->createStub(AccessPluginBase::class);
    $this->accessPluginManager
      ->method('createInstance')
      ->willReturn($access_plugin);

    return [$view, $view_entity, $access_plugin];
  }

}

/**
 * A page controller for use by tests in this file.
 */
class TestController {

  /**
   * A page title callback.
   *
   * @return string
   *   The page title.
   */
  public function testTitle() {
    return 'Test title';
  }

}
