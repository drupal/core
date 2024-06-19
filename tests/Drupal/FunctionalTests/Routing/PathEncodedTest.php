<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Routing;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Tests URL generation and routing for route paths with encoded characters.
 *
 * @group path
 * @group routing
 */
class PathEncodedTest extends BrowserTestBase {

  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'path_encoded_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testGetEncoded(): void {
    $route_paths = [
      'path_encoded_test.colon' => '/hi/llama:party',
      'path_encoded_test.at_sign' => '/blog/@Dries',
      'path_encoded_test.parentheses' => '/cat(box)',
    ];
    foreach ($route_paths as $route_name => $path) {
      $this->drupalGet(Url::fromRoute($route_name));
      $this->assertSession()->pageTextContains('PathEncodedTestController works');
    }
  }

  public function testAliasToEncoded(): void {
    $route_paths = [
      'path_encoded_test.colon' => '/hi/llama:party',
      'path_encoded_test.at_sign' => '/blog/@Dries',
      'path_encoded_test.parentheses' => '/cat(box)',
    ];
    $aliases = [];
    foreach ($route_paths as $route_name => $path) {
      $aliases[$route_name] = $this->randomMachineName();
      $this->createPathAlias($path, '/' . $aliases[$route_name]);
    }
    foreach ($route_paths as $route_name => $path) {
      // The alias may be only a suffix of the generated path when the test is
      // run with Drupal installed in a subdirectory.
      $this->assertMatchesRegularExpression('@/' . rawurlencode($aliases[$route_name]) . '$@', Url::fromRoute($route_name)->toString());
      $this->drupalGet(Url::fromRoute($route_name));
      $this->assertSession()->pageTextContains('PathEncodedTestController works');
    }
  }

}
