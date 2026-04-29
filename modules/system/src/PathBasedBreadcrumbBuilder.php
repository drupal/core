<?php

namespace Drupal\system;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Defines a class to build path-based breadcrumbs.
 *
 * @see \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
 */
class PathBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * Site config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The patch matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  public function __construct(
    protected RequestContext $context,
    protected AccessManagerInterface $accessManager,
    #[Autowire(service: 'router')]
    protected RequestMatcherInterface $router,
    protected InboundPathProcessorInterface $pathProcessor,
    ConfigFactoryInterface $config_factory,
    protected TitleResolverInterface $titleResolver,
    protected AccountInterface $currentUser,
    protected CurrentPathStack $currentPath,
    ?PathMatcherInterface $path_matcher = NULL,
  ) {
    $this->config = $config_factory->get('system.site');
    $this->pathMatcher = $path_matcher ?: \Drupal::service('path.matcher');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, CacheableMetadata $cacheable_metadata) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $links = [];

    // Add the url.path.parent cache context. This code ignores the last path
    // part so the result only depends on the path parents.
    $breadcrumb->addCacheContexts(['url.path.parent', 'url.path.is_front']);

    // Do not display a breadcrumb on the frontpage.
    if ($this->pathMatcher->isFrontPage()) {
      return $breadcrumb;
    }

    // General path-based breadcrumbs. Use the actual request path, prior to
    // resolving path aliases, so the breadcrumb can be defined by simply
    // creating a hierarchy of path aliases.
    $path = trim($this->context->getPathInfo(), '/');
    $path_elements = explode('/', $path);
    $exclude = [];
    // Don't show a link to the front-page path.
    $front = $this->config->get('page.front');
    $exclude[$front] = TRUE;
    // /user is just a redirect, so skip it.
    // @todo Find a better way to deal with /user.
    $exclude['/user'] = TRUE;
    while (count($path_elements) > 1) {
      array_pop($path_elements);
      // Copy the path elements for up-casting.
      $route_request = $this->getRequestForPath('/' . implode('/', $path_elements), $exclude);
      if ($route_request) {
        $route_match = RouteMatch::createFromRequest($route_request);
        $access = $this->accessManager->check($route_match, $this->currentUser, NULL, TRUE);
        // The set of breadcrumb links depends on the access result, so merge
        // the access result's cacheability metadata.
        $breadcrumb = $breadcrumb->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $title = $this->titleResolver->getTitle($route_request, $route_match->getRouteObject());
          if (isset($title)) {
            $url = Url::fromRouteMatch($route_match);
            $links[] = new Link($title, $url);
          }
        }
      }
    }

    // Add the Home link.
    $links[] = Link::createFromRoute($this->t('Home'), '<front>');

    return $breadcrumb->setLinks(array_reverse($links));
  }

  /**
   * Matches a path in the router.
   *
   * @param string $path
   *   The request path with a leading slash.
   * @param array $exclude
   *   An array of paths or system paths to skip.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A populated request object or NULL if the path couldn't be matched.
   */
  protected function getRequestForPath($path, array $exclude) {
    if (!empty($exclude[$path])) {
      return NULL;
    }
    try {
      $request = Request::create($path);
    }
    catch (BadRequestException) {
      return NULL;
    }
    // Performance optimization: set a short accept header to reduce overhead in
    // AcceptHeaderMatcher when matching the request.
    $request->headers->set('Accept', 'text/html');
    // Find the system path by resolving aliases, language prefix, etc.
    $processed = $this->pathProcessor->processInbound($path, $request);
    if (empty($processed) || !empty($exclude[$processed])) {
      // This resolves to the front page, which we already add.
      return NULL;
    }
    $this->currentPath->setPath($processed, $request);
    // Attempt to match this path to provide a fully built request.
    try {
      $request->attributes->add($this->router->matchRequest($request));
      return $request;
    }
    catch (ParamNotConvertedException | ResourceNotFoundException | MethodNotAllowedException | AccessDeniedHttpException | NotFoundHttpException) {
      return NULL;
    }
  }

}
