<?php

namespace Drupal\Component\Plugin\Discovery;

use Drupal\Component\Plugin\Attribute\AttributeInterface;
use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\FileCache\FileCacheInterface;

/**
 * Defines a discovery mechanism to find plugins with attributes.
 */
class AttributeClassDiscovery implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * The file cache object.
   */
  protected FileCacheInterface $fileCache;

  /**
   * List of root namespaces abbreviated to two levels.
   *
   * This list of namespaces is derived from the namespaces to look for plugin
   * implementations in, with each namespace in the list reduced to the first
   * two levels only, such as "Drupal\Component". Checking class namespaces
   * against this list provides a way to check that dependencies' classes exist
   * without using the "*_exists()" functions, which loads every class into
   * memory and can throw errors.
   *
   * @var list<string>
   */
  protected readonly array $rootTwoLevelNamespaces;

  /**
   * Constructs a new instance.
   *
   * @param array<string, list<string>> $pluginNamespaces
   *   (optional) An array of namespace that may contain plugin implementations.
   *   Defaults to an empty array.
   * @param string $pluginDefinitionAttributeName
   *   (optional) The name of the attribute that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Plugin\Attribute\Plugin'.
   */
  public function __construct(
    protected readonly array $pluginNamespaces = [],
    protected readonly string $pluginDefinitionAttributeName = Plugin::class,
  ) {
    $file_cache_suffix = str_replace('\\', '_', $this->pluginDefinitionAttributeName);
    $this->fileCache = FileCacheFactory::get('attribute_discovery:' . $this->getFileCacheSuffix($file_cache_suffix));
    $this->rootTwoLevelNamespaces = array_unique(array_map(fn($namespace) => $this->getTwoLevelNamespace($namespace), array_keys($this->getPluginNamespaces())));
  }

  /**
   * Gets the file cache suffix.
   *
   * This method allows classes that extend this class to add additional
   * information to the file cache collection name.
   *
   * @param string $default_suffix
   *   The default file cache suffix.
   *
   * @return string
   *   The file cache suffix.
   */
  protected function getFileCacheSuffix(string $default_suffix): string {
    return $default_suffix;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = [];

    // Search for classes within all PSR-4 namespace locations.
    foreach ($this->getPluginNamespaces() as $namespace => $dirs) {
      foreach ($dirs as $dir) {
        if (file_exists($dir)) {
          $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
          );
          foreach ($iterator as $fileinfo) {
            assert($fileinfo instanceof \SplFileInfo);
            if ($fileinfo->getExtension() === 'php') {
              if ($cached = $this->fileCache->get($fileinfo->getPathName())) {
                if (isset($cached['id'])) {
                  // Explicitly unserialize this to create a new object
                  // instance.
                  $dependencies = isset($cached['dependencies']) ? unserialize($cached['dependencies']) : [];
                  if (!$this->hasMissingDependencies($dependencies ?? [])) {
                    $definitions[$cached['id']] = unserialize($cached['content']);
                  }
                }
                continue;
              }

              $sub_path = $iterator->getSubIterator()->getSubPath();
              $sub_path = $sub_path ? str_replace(DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
              $class = $namespace . '\\' . $sub_path . $fileinfo->getBasename('.php');
              // Plugins may rely on classes, interfaces, or traits defined by
              // modules that are not installed. In such a case, an error may
              // be thrown from reflection. However, this is an unavoidable
              // situation with optional dependencies and plugins. Therefore,
              // silently skip over this class and avoid writing to the cache,
              // so that it is scanned each time. This ensures that the plugin
              // definition will be found if the module it requires is enabled.
              try {
                if (!class_exists($class)) {
                  continue;
                }
              }
              catch (\Error) {
                continue;
              }
              $result = $this->parseClass($class, $fileinfo);
              ['id' => $id, 'content' => $content] = $result;
              if ($id) {
                if (!$this->hasMissingDependencies($result['dependencies'] ?? [])) {
                  $definitions[$id] = $content;
                }
                // Explicitly serialize this to create a new object instance.
                $this->fileCache->set($fileinfo->getPathName(), [
                  'id' => $id,
                  'content' => serialize($content),
                  'dependencies' => serialize($result['dependencies'] ?? NULL),
                ]);
              }
              else {
                // Store a NULL object, so that the file is not parsed again.
                $this->fileCache->set($fileinfo->getPathName(), [NULL]);
              }
            }
          }
        }
      }
    }

    return $definitions;
  }

  /**
   * Parses attributes from a class.
   *
   * @param class-string $class
   *   The class to parse.
   * @param \SplFileInfo $fileinfo
   *   The SPL file information for the class.
   *
   * @return array
   *   An array with the keys 'id', 'content', and 'dependencies'. The 'id' is
   *   the plugin ID, 'content' is the plugin definition, and 'dependencies' is
   *   a list of class, interface or trait names in the plugin class hierarchy.
   *
   * @throws \ReflectionException
   * @throws \Error
   */
  protected function parseClass(string $class, \SplFileInfo $fileinfo): array {
    // @todo Consider performance improvements over using reflection.
    // @see https://www.drupal.org/project/drupal/issues/3395260.
    $reflection_class = new \ReflectionClass($class);

    $id = $content = NULL;
    if ($attributes = $reflection_class->getAttributes($this->pluginDefinitionAttributeName, \ReflectionAttribute::IS_INSTANCEOF)) {
      /** @var \Drupal\Component\Plugin\Attribute\AttributeInterface $attribute */
      $attribute = $attributes[0]->newInstance();
      $this->prepareAttributeDefinition($attribute, $class);
      if (($dependencies = $this->getClassDependencies($reflection_class))) {
        // Include the dependencies in the plugin definition content in case
        // plugins need to know about them.
        $attribute->setDependencies($dependencies);
      }
      $id = $attribute->getId();
      $content = $attribute->get();
    }
    return ['id' => $id, 'content' => $content, 'dependencies' => $dependencies ?? NULL];
  }

  /**
   * Prepares the attribute definition.
   *
   * @param \Drupal\Component\Plugin\Attribute\AttributeInterface $attribute
   *   The attribute derived from the plugin.
   * @param string $class
   *   The class used for the plugin.
   */
  protected function prepareAttributeDefinition(AttributeInterface $attribute, string $class): void {
    $attribute->setClass($class);
  }

  /**
   * Gets an array of PSR-4 namespaces to search for plugin classes.
   *
   * @return string[][]
   *   An array of namespaces to search.
   */
  protected function getPluginNamespaces(): array {
    return $this->pluginNamespaces;
  }

  /**
   * Gets a string containing the first two levels of a class name or namespace.
   *
   * @param string $namespace
   *   The class name or namespace.
   *
   * @return string
   *   A namespace string containing only two levels.
   */
  protected function getTwoLevelNamespace(string $namespace): string {
    return implode('\\', array_slice(explode('\\', $namespace), 0, 2));
  }

  /**
   * Gets the list of class, interface, and trait dependencies for the class.
   *
   * @param \ReflectionClass $reflection_class
   *   Plugin class reflection object.
   *
   * @return array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null
   *   The list of dependencies, keyed by type. If the type is 'class', 'trait',
   *   or 'interface', the values for the type are class names. If the type is
   *   'provider', the values for the type are provider names. NULL if there are
   *   no dependencies.
   */
  protected function getClassDependencies(\ReflectionClass $reflection_class): ?array {
    $dependencies = [];
    if (($interfaces = $reflection_class->getInterfaceNames())) {
      $dependencies['interface'] = $interfaces;
    }
    if (($traits = $reflection_class->getTraitNames())) {
      $dependencies['trait'] = $traits;
    }

    $child_class = $reflection_class;
    while (($parent_class = $child_class->getParentClass())) {
      $dependencies['class'][] = $parent_class->getName();
      if (($traits = $parent_class->getTraitNames())) {
        $dependencies['trait'] ??= [];
        $dependencies['trait'] = array_unique(array_merge($dependencies['trait'], $traits));
      }
      $child_class = $parent_class;
    }

    return $dependencies ?: NULL;
  }

  /**
   * Whether the plugin definition has missing dependencies.
   *
   * @param array<string, array<class-string>> $dependencies
   *   An array of dependencies' class names or namespaces, keyed by type.
   *
   * @return bool
   *   TRUE if any dependencies are missing. FALSE otherwise.
   */
  protected function hasMissingDependencies(array $dependencies): bool {
    foreach ($dependencies as $type_dependencies) {
      foreach ($type_dependencies as $dependency) {
        $namespace = $this->getTwoLevelNamespace($dependency);
        if (!str_starts_with($namespace, 'Drupal')) {
          // Not checking non-Drupal dependencies.
          continue;
        }

        if (!in_array($namespace, $this->rootTwoLevelNamespaces)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
