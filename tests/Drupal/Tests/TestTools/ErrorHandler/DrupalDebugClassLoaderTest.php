<?php

declare(strict_types=1);

namespace Drupal\Tests\TestTools\ErrorHandler;

use Drupal\TestTools\ErrorHandler\DrupalDebugClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Tests the DrupalDebugClassLoader.
 */
#[CoversClass(DrupalDebugClassLoader::class)]
#[Group('TestTools')]
class DrupalDebugClassLoaderTest extends TestCase {

  /**
   * A DrupalDebugClassLoader instance for testing.
   */
  private DrupalDebugClassLoader $loader;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loader = new DrupalDebugClassLoader(function (): void {});
  }

  /**
   * Tests that cross-module return type deprecations are generated.
   */
  public function testCrossModuleReturnTypeDeprecation(): void {
    $deprecations = $this->getReturnTypeDeprecations('Drupal\drupal_debug_test_other\ChildWithoutReturnType');
    $this->assertSame(['Method "Drupal\drupal_debug_test_core\ParentWithReturn::testMethod()" might add "string" as a native return type declaration in the future. Do the same in child class "Drupal\drupal_debug_test_other\ChildWithoutReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.'], $deprecations);
  }

  /**
   * Tests scenarios that should NOT trigger cross-module deprecations.
   */
  #[TestWith(['Drupal\drupal_debug_test_core\SameModuleChild'])]
  #[TestWith(['Drupal\drupal_debug_test_other\ChildWithNativeReturnType'])]
  #[TestWith(['Drupal\drupal_debug_test_other\ChildWithReturnAnnotation'])]
  #[TestWith(['Drupal\drupal_debug_test_other\ChildWithDeprecatedMethod'])]
  #[TestWith(['Drupal\drupal_debug_test_other\ChildWithoutOverride'])]
  public function testNoDeprecation(string $class): void {
    $this->assertEmpty($this->getReturnTypeDeprecations($class));
  }

  /**
   * Returns only the return-type deprecations for a given class.
   */
  private function getReturnTypeDeprecations(string $class): array {
    $deprecations = $this->loader->checkAnnotations(new \ReflectionClass($class), $class);
    return array_values(array_filter($deprecations, fn($d) => str_contains($d, 'might add')));
  }

}

namespace Drupal\drupal_debug_test_core;

/**
 * Fixture: parent class with @return annotations but no native return types.
 */
class ParentWithReturn {

  /**
   * @return string
   *   A test string.
   */
  public function testMethod() {
    return 'test';
  }

  /**
   * @return int
   *   A test integer.
   */
  public function anotherMethod() {
    return 42;
  }

}

/**
 * Fixture: child in the same module as the parent.
 */
class SameModuleChild extends ParentWithReturn {

  /**
   * {@inheritdoc}
   */
  public function testMethod() {
    return 'same module';
  }

}

namespace Drupal\drupal_debug_test_other;

use Drupal\drupal_debug_test_core\ParentWithReturn;

/**
 * Fixture: cross-module child without native return type.
 */
class ChildWithoutReturnType extends ParentWithReturn {

  /**
   * {@inheritdoc}
   */
  public function testMethod() {
    return 'overridden';
  }

}

/**
 * Fixture: cross-module child with native return type.
 */
class ChildWithNativeReturnType extends ParentWithReturn {

  /**
   * {@inheritdoc}
   */
  public function testMethod(): string {
    return 'overridden';
  }

}

/**
 * Fixture: cross-module child with own @return annotation.
 */
class ChildWithReturnAnnotation extends ParentWithReturn {

  /**
   * @return string
   *   A test string.
   */
  public function testMethod() {
    return 'overridden';
  }

}

/**
 * Fixture: cross-module child with deprecated method.
 */
class ChildWithDeprecatedMethod extends ParentWithReturn {

  /**
   * @deprecated in drupal:11.0.0 and is removed from drupal:12.0.0.
   *   Use something else instead.
   * @see https://www.drupal.org/node/9999999
   */
  public function testMethod() {
    return 'overridden';
  }

}

/**
 * Fixture: cross-module child that does not override the method.
 */
class ChildWithoutOverride extends ParentWithReturn {

}
