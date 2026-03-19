<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\serialization\Normalizer\TypedDataNormalizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\serialization\Normalizer\TypedDataNormalizer.
 */
#[CoversClass(TypedDataNormalizer::class)]
#[Group('serialization')]
class TypedDataNormalizerTest extends UnitTestCase {

  /**
   * The TypedDataNormalizer instance.
   *
   * @var \Drupal\serialization\Normalizer\TypedDataNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->normalizer = new TypedDataNormalizer();
  }

  /**
   * Tests the supportsNormalization() method.
   */
  public function testSupportsNormalization(): void {
    $this->assertTrue($this->normalizer->supportsNormalization($this->createStub(TypedDataInterface::class)));
    // Also test that an object not implementing TypedDataInterface fails.
    $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
  }

  /**
   * Tests the normalize() method.
   */
  public function testNormalize(): void {
    $typed_data = $this->createMock(TypedDataInterface::class);
    $typed_data->expects($this->once())
      ->method('getValue')
      ->willReturn('test');

    $this->assertEquals('test', $this->normalizer->normalize($typed_data));
  }

}
