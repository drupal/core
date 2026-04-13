<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Factory\FactoryInterface;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Menu\LocalActionManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Prophecy\Argument;
use Prophecy\Prophet;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Tests Drupal\Core\Menu\LocalActionManager.
 */
#[CoversClass(LocalActionManager::class)]
#[Group('Menu')]
class LocalActionManagerTest extends UnitTestCase {

  /**
   * The mocked argument resolver.
   */
  protected ArgumentResolverInterface&MockObject $argumentResolver;

  /**
   * The request.
   */
  protected Request&Stub $request;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface&Stub $moduleHandler;

  /**
   * The router provider.
   */
  protected RouteProviderInterface&Stub $routeProvider;

  /**
   * The cache backend.
   */
  protected CacheBackendInterface&Stub $cacheBackend;

  /**
   * The access manager.
   */
  protected AccessManagerInterface&Stub $accessManager;

  /**
   * The account.
   */
  protected AccountInterface&Stub $account;

  /**
   * The factory.
   */
  protected FactoryInterface&Stub $factory;

  /**
   * The plugin discovery.
   */
  protected DiscoveryInterface&Stub $discovery;

  /**
   * The tested local action manager.
   *
   * @var \Drupal\Tests\Core\Menu\TestLocalActionManager
   */
  protected $localActionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->argumentResolver = $this->createMock('\Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface');
    $this->request = $this->createStub(Request::class);
    $this->routeProvider = $this->createStub(RouteProviderInterface::class);
    $this->moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $this->cacheBackend = $this->createStub(CacheBackendInterface::class);

    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens(Argument::any())
      ->willReturn(TRUE);

    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager->reveal());
    \Drupal::setContainer($container);

    $access_result = (new AccessResultForbidden())->cachePerPermissions();
    $this->accessManager = $this->createStub(AccessManagerInterface::class);
    $this->accessManager
      ->method('checkNamedRoute')
      ->willReturn($access_result);
    $this->account = $this->createStub(AccountInterface::class);
    $this->discovery = $this->createStub(DiscoveryInterface::class);
    $this->factory = $this->createStub(FactoryInterface::class);
    $route_match = $this->createStub(RouteMatchInterface::class);

    $this->localActionManager = new TestLocalActionManager($this->argumentResolver, $this->request, $route_match, $this->routeProvider, $this->moduleHandler, $this->cacheBackend, $this->accessManager, $this->account, $this->discovery, $this->factory);
  }

  /**
   * Tests get title.
   */
  public function testGetTitle(): void {
    $local_action = $this->createMock('Drupal\Core\Menu\LocalActionInterface');
    $local_action->expects($this->once())
      ->method('getTitle')
      ->with('test');

    $this->argumentResolver->expects($this->once())
      ->method('getArguments')
      ->with($this->request, [$local_action, 'getTitle'])
      ->willReturn(['test']);

    $this->localActionManager->getTitle($local_action);
  }

  /**
   * Tests get actions for route.
   */
  #[DataProvider('getActionsForRouteProvider')]
  public function testGetActionsForRoute($route_appears, array $plugin_definitions, array $expected_actions): void {
    $this->discovery
      ->method('getDefinitions')
      ->willReturn($plugin_definitions);
    $map = [];
    foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
      $plugin = $this->createStub(LocalActionDefault::class);
      $plugin
        ->method('getCacheContexts')
        ->willReturn([]);
      $plugin
        ->method('getCacheTags')
        ->willReturn([]);
      $plugin
        ->method('getCacheMaxAge')
        ->willReturn(0);
      $plugin
        ->method('getRouteName')
        ->willReturn($plugin_definition['route_name']);
      $plugin
        ->method('getRouteParameters')
        ->willReturn($plugin_definition['route_parameters'] ?? []);
      $plugin
        ->method('getTitle')
        ->willReturn($plugin_definition['title']);
      $this->argumentResolver
        ->method('getArguments')
        ->with($this->request, [$plugin, 'getTitle'])
        ->willReturn([]);

      $plugin
        ->method('getWeight')
        ->willReturn($plugin_definition['weight']);
      $this->argumentResolver
        ->method('getArguments')
        ->with($this->request, [$plugin, 'getTitle'])
        ->willReturn([]);
      $map[] = [$plugin_id, [], $plugin];
    }
    $this->factory
      ->method('createInstance')
      ->willReturnMap($map);

    $this->assertEquals($expected_actions, $this->localActionManager->getActionsForRoute($route_appears));
  }

  public static function getActionsForRouteProvider(): array {
    $originalContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;

    $cache_contexts_manager = (new Prophet())->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens(Argument::any())
      ->willReturn(TRUE);

    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager->reveal());
    \Drupal::setContainer($container);

    // Single available and single expected plugins.
    $data[] = [
      'test_route',
      [
        'plugin_id_1' => [
          'appears_on' => [
            'test_route',
          ],
          'route_name' => 'test_route_2',
          'title' => 'Plugin ID 1',
          'weight' => 0,
        ],
      ],
      [
        '#cache' => [
          'tags' => [],
          'contexts' => ['route', 'user.permissions'],
          'max-age' => 0,
        ],
        'plugin_id_1' => [
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => 'Plugin ID 1',
            'url' => Url::fromRoute('test_route_2'),
            'localized_options' => '',
          ],
          '#access' => AccessResult::forbidden()->cachePerPermissions(),
          '#weight' => 0,
        ],
      ],
    ];
    // Multiple available and single expected plugins.
    $data[] = [
      'test_route',
      [
        'plugin_id_1' => [
          'appears_on' => [
            'test_route',
          ],
          'route_name' => 'test_route_2',
          'title' => 'Plugin ID 1',
          'weight' => 0,
        ],
        'plugin_id_2' => [
          'appears_on' => [
            'test_route2',
          ],
          'route_name' => 'test_route_3',
          'title' => 'Plugin ID 2',
          'weight' => 0,
        ],
      ],
      [
        '#cache' => [
          'tags' => [],
          'contexts' => ['route', 'user.permissions'],
          'max-age' => 0,
        ],
        'plugin_id_1' => [
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => 'Plugin ID 1',
            'url' => Url::fromRoute('test_route_2'),
            'localized_options' => '',
          ],
          '#access' => AccessResult::forbidden()->cachePerPermissions(),
          '#weight' => 0,
        ],
      ],
    ];

    // Multiple available and multiple expected plugins and specified weight.
    $data[] = [
      'test_route',
      [
        'plugin_id_1' => [
          'appears_on' => [
            'test_route',
          ],
          'route_name' => 'test_route_2',
          'title' => 'Plugin ID 1',
          'weight' => 1,
        ],
        'plugin_id_2' => [
          'appears_on' => [
            'test_route',
          ],
          'route_name' => 'test_route_3',
          'title' => 'Plugin ID 2',
          'weight' => 0,
        ],
      ],
      [
        '#cache' => [
          'contexts' => ['route', 'user.permissions'],
          'tags' => [],
          'max-age' => 0,
        ],
        'plugin_id_1' => [
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => 'Plugin ID 1',
            'url' => Url::fromRoute('test_route_2'),
            'localized_options' => '',
          ],
          '#access' => AccessResult::forbidden()->cachePerPermissions(),
          '#weight' => 1,
        ],
        'plugin_id_2' => [
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => 'Plugin ID 2',
            'url' => Url::fromRoute('test_route_3'),
            'localized_options' => '',
          ],
          '#access' => AccessResult::forbidden()->cachePerPermissions(),
          '#weight' => 0,
        ],
      ],
    ];

    // Two plugins with the same route name but different route parameters.
    $data[] = [
      'test_route',
      [
        'plugin_id_1' => [
          'appears_on' => [
            'test_route',
          ],
          'route_name' => 'test_route_2',
          'route_parameters' => ['test1'],
          'title' => 'Plugin ID 1',
          'weight' => 1,
        ],
        'plugin_id_2' => [
          'appears_on' => [
            'test_route',
          ],
          'route_name' => 'test_route_2',
          'route_parameters' => ['test2'],
          'title' => 'Plugin ID 2',
          'weight' => 0,
        ],
      ],
      [
        '#cache' => [
          'contexts' => ['route', 'user.permissions'],
          'tags' => [],
          'max-age' => 0,
        ],
        'plugin_id_1' => [
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => 'Plugin ID 1',
            'url' => Url::fromRoute('test_route_2', ['test1']),
            'localized_options' => '',
          ],
          '#access' => AccessResult::forbidden()->cachePerPermissions(),
          '#weight' => 1,
        ],
        'plugin_id_2' => [
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => 'Plugin ID 2',
            'url' => Url::fromRoute('test_route_2', ['test2']),
            'localized_options' => '',
          ],
          '#access' => AccessResult::forbidden()->cachePerPermissions(),
          '#weight' => 0,
        ],
      ],
    ];

    // Restore the original container if needed.
    if ($originalContainer) {
      \Drupal::setContainer($originalContainer);
    }

    return $data;
  }

}

/**
 * Stub class for testing LocalActionManager.
 */
class TestLocalActionManager extends LocalActionManager {

  public function __construct(ArgumentResolverInterface $argument_resolver, Request $request, RouteMatchInterface $route_match, RouteProviderInterface $route_provider, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, AccessManagerInterface $access_manager, AccountInterface $account, DiscoveryInterface $discovery, FactoryInterface $factory) {
    $this->discovery = $discovery;
    $this->factory = $factory;
    $this->routeProvider = $route_provider;
    $this->accessManager = $access_manager;
    $this->account = $account;
    $this->argumentResolver = $argument_resolver;
    $this->requestStack = new RequestStack();
    $this->requestStack->push($request);
    $this->routeMatch = $route_match;
    $this->moduleHandler = $module_handler;
    $this->alterInfo('menu_local_actions');
    $this->setCacheBackend($cache_backend, 'local_action_plugins');
  }

}
