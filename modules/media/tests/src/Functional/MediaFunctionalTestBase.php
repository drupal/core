<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Base class for Media functional tests.
 */
abstract class MediaFunctionalTestBase extends BrowserTestBase {

  use MediaFunctionalTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'field_ui',
    'views_ui',
    'media',
    'media_test_source',
  ];

}
