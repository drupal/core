<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\serialization\Normalizer\ListNormalizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests Drupal\serialization\Normalizer\ListNormalizer.
 */
#[CoversClass(ListNormalizer::class)]
#[Group('serialization')]
class ListNormalizerTest extends UnitTestCase {

  /**
   * The ListNormalizer instance.
   *
   * @var \Drupal\serialization\Normalizer\ListNormalizer
   */
  protected $normalizer;

  /**
   * The mock list instance.
   *
   * @var \Drupal\Core\TypedData\ListInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $list;

  /**
   * The expected list values to use for testing.
   *
   * @var array
   */
  protected $expectedListValues = ['test', 'test', 'test'];

  /**
   * The mocked typed data.
   *
   * @var \Drupal\Core\TypedData\TypedDataInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $typedData;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the TypedDataManager to return a TypedDataInterface mock.
    $this->typedData = $this->createStub(TypedDataInterface::class);
    $typed_data_manager = $this->createStub(TypedDataManagerInterface::class);
    $typed_data_manager
      ->method('getPropertyInstance')
      ->willReturn($this->typedData);

    // Set up a container as ItemList() will call for the 'typed_data_manager'
    // service.
    $container = new ContainerBuilder();
    $container->set('typed_data_manager', $typed_data_manager);
    \Drupal::setContainer($container);

    $this->normalizer = new ListNormalizer();

    $this->list = new ItemList(new DataDefinition());
    $this->list->setValue($this->expectedListValues);
  }

  /**
   * Tests the supportsNormalization() method.
   */
  public function testSupportsNormalization(): void {
    $this->assertTrue($this->normalizer->supportsNormalization($this->list));
    $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
  }

  /**
   * Tests the normalize() method.
   */
  public function testNormalize(): void {
    $serializer = $this->prophesize(Serializer::class);
    $serializer->normalize($this->typedData, 'json', ['mu' => 'nu'])
      ->shouldBeCalledTimes(3)
      ->willReturn('test');

    $this->normalizer->setSerializer($serializer->reveal());

    $normalized = $this->normalizer->normalize($this->list, 'json', ['mu' => 'nu']);

    $this->assertEquals($this->expectedListValues, $normalized);
  }

}
