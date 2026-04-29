<?php

namespace Drupal\language;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\language\Attribute\LanguageNegotiation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages language negotiation methods.
 */
class LanguageNegotiationMethodManager extends DefaultPluginManager {

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct('Plugin/LanguageNegotiation', $namespaces, $module_handler, LanguageNegotiationMethodInterface::class, LanguageNegotiation::class, 'Drupal\language\Annotation\LanguageNegotiation');
    $this->cacheBackend = $cache_backend;
    $this->setCacheBackend($cache_backend, 'language_negotiation_plugins');
    $this->alterInfo('language_negotiation_info');
  }

}
