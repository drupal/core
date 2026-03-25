<?php

namespace Drupal\Core\Test;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Extension\ExtensionDiscovery;

/**
 * Discovers available tests.
 *
 * @internal
 *
 * @final
 */
class TestDiscovery {

  /**
   * Cached map of all test namespaces to respective directories.
   *
   * @var array<string,string>
   */
  protected array $testNamespaces;

  public function __construct(
    protected readonly string $root,
    protected readonly ClassLoader $classLoader,
  ) {
  }

  /**
   * Registers test namespaces of all extensions and core test classes.
   *
   * @return array<string,string>
   *   An associative array whose keys are PSR-4 namespace prefixes and whose
   *   values are directory names.
   */
  public function registerTestNamespaces(): array {
    if (isset($this->testNamespaces)) {
      return $this->testNamespaces;
    }
    $this->testNamespaces = [];

    $existing = $this->classLoader->getPrefixesPsr4();

    // Add PHPUnit test namespaces of Drupal core. Order the namespaces by the
    // test types that tend to be slowest first, to optimize overall test times
    // when multiple different test types are run concurrently by the same test
    // runner.
    $this->testNamespaces['Drupal\\FunctionalJavascriptTests\\'] = [$this->root . '/core/tests/Drupal/FunctionalJavascriptTests'];
    $this->testNamespaces['Drupal\\FunctionalTests\\'] = [$this->root . '/core/tests/Drupal/FunctionalTests'];
    $this->testNamespaces['Drupal\\BuildTests\\'] = [$this->root . '/core/tests/Drupal/BuildTests'];
    $this->testNamespaces['Drupal\\Tests\\'] = [$this->root . '/core/tests/Drupal/Tests'];
    $this->testNamespaces['Drupal\\KernelTests\\'] = [$this->root . '/core/tests/Drupal/KernelTests'];
    $this->testNamespaces['Drupal\\TestTools\\'] = [$this->root . '/core/tests/Drupal/TestTools'];

    foreach ($this->getExtensions() as $name => $extension) {
      $base_path = $this->root . '/' . $extension->getPath();

      // Add namespace of disabled/uninstalled extensions.
      if (!isset($existing["Drupal\\$name\\"])) {
        $this->classLoader->addPsr4("Drupal\\$name\\", "$base_path/src");
      }

      // Add PHPUnit test namespaces.
      $this->testNamespaces["Drupal\\Tests\\$name\\"][] = "$base_path/tests/src";
    }

    // Expose tests provided by core recipes.
    $base_path = $this->root . '/core/recipes';
    if ($handle = @opendir($base_path)) {
      while (($recipe = readdir($handle)) !== FALSE) {
        $this->testNamespaces["Drupal\\FunctionalTests\\Recipe\\Core\\$recipe\\"][] = "$base_path/$recipe/tests/src/Functional";
      }
      closedir($handle);
    }

    foreach ($this->testNamespaces as $prefix => $paths) {
      $this->classLoader->addPsr4($prefix, $paths);
    }

    return $this->testNamespaces;
  }

  /**
   * Returns all available extensions.
   *
   * @return array<string,\Drupal\Core\Extension\Extension>
   *   An array of Extension objects, keyed by extension name.
   */
  protected function getExtensions(): array {
    $listing = new ExtensionDiscovery($this->root);
    // Ensure that tests in all profiles are discovered.
    $listing->setProfileDirectories([]);
    $extensions = $listing->scan('module', TRUE);
    $extensions += $listing->scan('profile', TRUE);
    $extensions += $listing->scan('theme', TRUE);
    return $extensions;
  }

}
