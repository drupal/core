<?php

namespace Drupal\Core\Condition;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Executable\ExecutableException;
use Drupal\Core\Executable\ExecutableInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\FilteredPluginManagerInterface;
use Drupal\Core\Plugin\FilteredPluginManagerTrait;

/**
 * A plugin manager for condition plugins.
 *
 * @see \Drupal\Core\Condition\Attribute\Condition
 * @see \Drupal\Core\Condition\ConditionInterface
 * @see \Drupal\Core\Condition\ConditionPluginBase
 *
 * @ingroup plugin_api
 */
class ConditionManager extends DefaultPluginManager implements ExecutableManagerInterface, CategorizingPluginManagerInterface, FilteredPluginManagerInterface {

  use CategorizingPluginManagerTrait;
  use FilteredPluginManagerTrait;

  /**
   * Constructs a ConditionManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('condition_info');
    $this->setCacheBackend($cache_backend, 'condition_plugins');

    parent::__construct(
      'Plugin/Condition',
      $namespaces,
      $module_handler,
      ConditionInterface::class,
      Condition::class,
      'Drupal\Core\Condition\Annotation\Condition'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'condition';
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin = $this->getFactory()->createInstance($plugin_id, $configuration);
    return $plugin->setExecutableManager($this);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ExecutableInterface $condition) {
    if ($condition instanceof ConditionInterface) {
      $result = $condition->evaluate();
      return $condition->isNegated() ? !$result : $result;
    }
    throw new ExecutableException("This manager object can only execute condition plugins");
  }

}
