<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Test;

use Drupal\Core\Test\Exception\MissingGroupException;
use Drupal\Core\Test\PhpUnitTestDiscovery;
use Drupal\Core\Test\TestDiscovery;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Unit tests for test discovery.
 */
#[CoversClass(PhpUnitTestDiscovery::class)]
#[CoversClass(TestDiscovery::class)]
#[Group('Test')]
class TestDiscoveryTest extends UnitTestCase {

  /**
   * Tests test info parser.
   */
  #[DataProvider('infoParserProvider')]
  #[RunInSeparateProcess]
  public function testTestInfoParser(array $expected, string $classname): void {
    $configurationFilePath = $this->root . \DIRECTORY_SEPARATOR . 'core';
    $phpUnitTestDiscovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath($configurationFilePath);
    $classes = $phpUnitTestDiscovery->getTestClasses(NULL, [$expected['type']]);
    $info = $classes[$expected['group']][$classname];
    // The 'file' key varies depending on the CI build directory, but that's
    // rather irrelevant here. Remove the key before comparison.
    unset($info['file']);
    $this->assertEquals($expected, $info);
  }

  public static function infoParserProvider(): array {
    // A module provided unit test.
    $tests[] = [
      // Expected result.
      [
        'name' => TestDatabaseTest::class,
        'group' => 'Test',
        'groups' => ['Test', 'simpletest', 'Template'],
        'description' => 'Tests Drupal\Core\Test\TestDatabase.',
        'type' => 'PHPUnit-Unit',
        'tests_count' => 4,
      ],
      // Classname.
      TestDatabaseTest::class,
    ];

    // A core unit test.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\Tests\Core\DrupalTest',
        'group' => 'DrupalTest',
        'groups' => ['DrupalTest'],
        'description' => 'Tests Drupal.',
        'type' => 'PHPUnit-Unit',
        'tests_count' => 34,
      ],
      // Classname.
      'Drupal\Tests\Core\DrupalTest',
    ];

    // A component unit test.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\Tests\Component\Plugin\PluginBaseTest',
        'group' => 'Plugin',
        'groups' => ['Plugin'],
        'description' => 'Tests Drupal\Component\Plugin\PluginBase.',
        'type' => 'PHPUnit-Unit-Component',
        'tests_count' => 7,
      ],
      // Classname.
      'Drupal\Tests\Component\Plugin\PluginBaseTest',
    ];

    // Functional PHPUnit test.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\FunctionalTests\BrowserTestBaseTest',
        'group' => 'browsertestbase',
        'groups' => ['browsertestbase'],
        'description' => 'Tests BrowserTestBase functionality.',
        'type' => 'PHPUnit-Functional',
        'tests_count' => 21,
      ],
      // Classname.
      'Drupal\FunctionalTests\BrowserTestBaseTest',
    ];

    // Kernel PHPUnit test.
    $tests['phpunit-kernel'] = [
      // Expected result.
      [
        'name' => 'Drupal\Tests\file\Kernel\FileItemValidationTest',
        'group' => 'file',
        'groups' => ['file'],
        'description' => 'Tests that files referenced in file and image fields are always validated.',
        'type' => 'PHPUnit-Kernel',
        'tests_count' => 2,
      ],
      // Classname.
      'Drupal\Tests\file\Kernel\FileItemValidationTest',
    ];

    return $tests;
  }

  /**
   * Tests for missing #[Group] attribute.
   */
  #[RunInSeparateProcess]
  public function testTestInfoParserMissingGroup(): void {
    $this->expectException(MissingGroupException::class);
    $this->expectExceptionMessage('Missing group metadata in test Drupal\\Tests\\Core\\Foo\\MissingAttributesTest::testNoMetadata');
    $configurationFilePath = $this->root . \DIRECTORY_SEPARATOR . 'core';
    $phpUnitTestDiscovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath($configurationFilePath);
    $phpUnitTestDiscovery->getTestClasses(NULL, [], $this->root . \DIRECTORY_SEPARATOR . 'core/tests/fixtures/test_driver/MissingAttributesTest.php');
  }

  /**
   * Tests for missing #[Group] attribute in a test with DataProvider.
   */
  #[RunInSeparateProcess]
  public function testTestInfoParserMissingGroupWithDataProvider(): void {
    $this->expectException(MissingGroupException::class);
    $this->expectExceptionMessage('Missing group metadata in test Drupal\\Tests\\Core\\Foo\\MissingAttributesWithDataProviderTest::testNoGroupMetadata#Test#1');
    $configurationFilePath = $this->root . \DIRECTORY_SEPARATOR . 'core';
    $phpUnitTestDiscovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath($configurationFilePath);
    $phpUnitTestDiscovery->getTestClasses(NULL, [], $this->root . \DIRECTORY_SEPARATOR . 'core/tests/fixtures/test_driver/MissingAttributesWithDataProviderTest.php');
  }

  /**
   * Tests PhpUnitTestDiscovery::getPhpunitTestSuite().
   */
  #[DataProvider('providerTestGetPhpunitTestSuite')]
  public function testGetPhpunitTestSuite(string $classname, string|false $expected): void {
    $this->assertEquals($expected, PhpUnitTestDiscovery::getPhpunitTestSuite($classname));
  }

  public static function providerTestGetPhpunitTestSuite(): array {
    $data = [];
    $data['simpletest-web test'] = ['\Drupal\rest\Tests\NodeTest', FALSE];
    $data['module-unittest'] = [static::class, 'Unit'];
    $data['module-kernel test'] = ['\Drupal\KernelTests\Core\Theme\TwigMarkupInterfaceTest', 'Kernel'];
    $data['module-functional test'] = ['\Drupal\FunctionalTests\BrowserTestBaseTest', 'Functional'];
    $data['module-functional javascript test'] = [
      '\Drupal\Tests\toolbar\FunctionalJavascript\ToolbarIntegrationTest',
      'FunctionalJavascript',
    ];
    $data['core-unittest'] = ['\Drupal\Tests\ComposerIntegrationTest', 'Unit'];
    $data['core-unittest2'] = ['Drupal\Tests\Core\DrupalTest', 'Unit'];
    $data['core-script-test'] = ['Drupal\KernelTests\Scripts\TestSiteApplicationTest', 'Kernel'];
    $data['core-kernel test'] = ['\Drupal\KernelTests\KernelTestBaseTest', 'Kernel'];
    $data['core-functional test'] = ['\Drupal\FunctionalTests\ExampleTest', 'Functional'];
    $data['core-functional javascript test'] = ['\Drupal\FunctionalJavascriptTests\ExampleTest', 'FunctionalJavascript'];
    $data['core-build test'] = ['\Drupal\BuildTests\Framework\Tests\BuildTestTest', 'Build'];

    return $data;
  }

}
