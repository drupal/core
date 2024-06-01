<?php

namespace Drupal\Core\Access;

use Drupal\Core\Routing\Access\AccessInterface as RoutingAccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Defines an access checker that allows specifying a custom method for access.
 *
 * You should only use it when you are sure that the access callback will not be
 * reused. Good examples in core are Edit or Toolbar module.
 *
 * The method is called on another instance of the controller class, so you
 * cannot reuse any stored property of your actual controller instance used
 * to generate the output.
 */
class CustomAccessCheck implements RoutingAccessInterface {

  /**
   * Constructs a CustomAccessCheck instance.
   *
   * @param \Drupal\Core\Utility\CallableResolver $callableResolver
   *   The callable resolver.
   * @param \Drupal\Core\Access\AccessArgumentsResolverFactoryInterface $argumentsResolverFactory
   *   The arguments resolver factory.
   */
  public function __construct(
    protected CallableResolver $callableResolver,
    protected AccessArgumentsResolverFactoryInterface $argumentsResolverFactory,
  ) {
  }

  /**
   * Checks access for the account and route using the custom access checker.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object to be checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Optional, a request. Only supply this parameter when checking the
   *   incoming request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, ?Request $request = NULL) {
    try {
      $callable = $this->callableResolver->getCallableFromDefinition($route->getRequirement('_custom_access'));
    }
    catch (\InvalidArgumentException $e) {
      // The custom access controller method was not found.
      throw new \BadMethodCallException(sprintf('The "%s" method is not callable as a _custom_access callback in route "%s"', $route->getRequirement('_custom_access'), $route->getPath()));
    }

    $arguments_resolver = $this->argumentsResolverFactory->getArgumentsResolver($route_match, $account, $request);
    $arguments = $arguments_resolver->getArguments($callable);

    return call_user_func_array($callable, $arguments);
  }

}
