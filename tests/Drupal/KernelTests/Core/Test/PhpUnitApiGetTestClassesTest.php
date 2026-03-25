<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Test;

use Drupal\Core\Test\PhpUnitTestDiscovery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ::getTestClasses().
 */
#[CoversMethod(PhpUnitTestDiscovery::class, 'getTestClasses')]
#[Group('TestSuites')]
#[Group('Test')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
class PhpUnitApiGetTestClassesTest extends KernelTestBase {

  /**
   * Checks PHPUnit API based discovery.
   */
  #[DataProvider('argumentsProvider')]
  public function testSuite(array $suites, ?string $extension = NULL, ?string $directory = NULL): void {
    $configurationFilePath = $this->container->getParameter('app.root') . \DIRECTORY_SEPARATOR . 'core';
    $phpUnitTestDiscovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath($configurationFilePath);
    $phpUnitList = $phpUnitTestDiscovery->getTestClasses($extension, $suites, $directory);
    $this->assertNotEmpty($phpUnitList);
  }

  /**
   * Provides test data to ::testSuite.
   */
  public static function argumentsProvider(): \Generator {
    yield 'All tests' => ['suites' => []];
    yield 'Testsuite: functional-javascript' => ['suites' => ['PHPUnit-FunctionalJavascript']];
    yield 'Testsuite: functional' => ['suites' => ['PHPUnit-Functional']];
    yield 'Testsuite: kernel' => ['suites' => ['PHPUnit-Kernel']];
    yield 'Testsuite: unit' => ['suites' => ['PHPUnit-Unit']];
    yield 'Testsuite: unit-component' => ['suites' => ['PHPUnit-Unit-Component']];
    yield 'Testsuite: build' => ['suites' => ['PHPUnit-Build']];
    yield 'Extension: system' => ['suites' => [], 'extension' => 'system'];
    yield 'Extension: system, testsuite: unit' => [
      'suites' => ['PHPUnit-Unit'],
      'extension' => 'system',
    ];
    yield 'Extension: system, directory' => [
      'suites' => [],
      'extension' => 'system',
      'directory' => 'core/modules/system/tests/src',
    ];
    yield 'Extension: system, testsuite: unit, directory' => [
      'suites' => ['PHPUnit-Unit'],
      'extension' => 'system',
      'directory' => 'core/modules/system/tests/src',
    ];
  }

}
