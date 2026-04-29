<?php

namespace Drupal\views;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\ViewsPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the cache backend factory.
 */
class ViewExecutableFactory {

  public function __construct(
    protected AccountInterface $user,
    protected RequestStack $requestStack,
    protected ViewsData $viewsData,
    protected RouteProviderInterface $routeProvider,
    #[Autowire(service: 'plugin.manager.views.display')]
    protected ViewsPluginManager $displayPluginManager,
  ) {
  }

  /**
   * Instantiates a ViewExecutable class.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   A view entity instance.
   *
   * @return \Drupal\views\ViewExecutable
   *   A ViewExecutable instance.
   */
  public function get(ViewEntityInterface $view) {
    $view_executable = new ViewExecutable($view, $this->user, $this->viewsData, $this->routeProvider, $this->displayPluginManager);
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      $view_executable->setRequest($request);
    }
    return $view_executable;
  }

}
