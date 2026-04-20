<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional;

use Drupal\Core\Database\Database;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UpdatePathTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the views config updater service.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ViewsConfigUpdaterTest extends BrowserTestBase {

  use UpdatePathTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $connection = Database::getConnection();

    // Enable views_test_config_updater via the database so post_update hooks
    // can run.
    $extensions = $connection->select('config')
      ->fields('config', ['data'])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute()
      ->fetchField();
    $extensions = unserialize($extensions);
    $extensions['module']['views_test_config_updater'] = 0;
    $connection->update('config')
      ->fields([
        'data' => serialize($extensions),
      ])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute();
  }

  /**
   * Tests ViewsConfigUpdater.
   */
  public function testViewsConfigUpdater(): void {
    // ViewsConfigUpdater currently contains no actual configuration update
    // logic. Replace this method with a real test when it does.
    $this->markTestSkipped();
  }

}
