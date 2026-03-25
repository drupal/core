<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\Tests\Core\Foo;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MissingAttributesWithDataProviderTest extends UnitTestCase {

  #[DataProvider('provider')]
  public function testNoGroupMetadata(string $baz): void {
  }

  public static function provider(): \Generator {
    yield 'Test#1' => ['foo'];
    yield 'Test#2' => ['bar'];
  }

}
