<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config\Entity;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityDisplayModeBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Entity\EntityDisplayModeBase.
 */
#[CoversClass(EntityDisplayModeBase::class)]
#[Group('Config')]
class EntityDisplayModeBaseUnitTest extends UnitTestCase {

  /**
   * The entity under test.
   *
   * @var \Drupal\Core\Entity\EntityDisplayModeBase|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entity;

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityInfo;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The UUID generator used for testing.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityType = $this->randomMachineName();

    $this->entityInfo = $this->createStub(EntityTypeInterface::class);
    $this->entityInfo
      ->method('getProvider')
      ->willReturn('entity');

    $this->entityTypeManager = $this->createStub(EntityTypeManagerInterface::class);

    $this->uuid = $this->createStub(UuidInterface::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);

    \Drupal::setContainer($container);
  }

  /**
   * Reinitializes the entity type manager as a mock object.
   */
  protected function setUpMockEntityTypeManger(): void {
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    \Drupal::getContainer()->set('entity_type.manager', $this->entityTypeManager);
  }

  /**
   * Tests calculate dependencies.
   */
  public function testCalculateDependencies(): void {
    $this->setUpMockEntityTypeManger();
    $target_entity_type_id = $this->randomMachineName(16);

    $target_entity_type = $this->createStub(EntityTypeInterface::class);
    $target_entity_type
      ->method('getProvider')
      ->willReturn('test_module');
    $values = ['targetEntityType' => $target_entity_type_id];

    $this->entityTypeManager->expects($this->exactly(2))
      ->method('getDefinition')
      ->willReturnMap([
        [$target_entity_type_id, TRUE, $target_entity_type],
        [$this->entityType, TRUE, $this->entityInfo],
      ]);

    $this->entity = new EntityDisplayModeBaseTestableClass($values, $this->entityType);
    $dependencies = $this->entity->calculateDependencies()->getDependencies();
    $this->assertContains('test_module', $dependencies['module']);
  }

  /**
   * Tests set target type.
   */
  public function testSetTargetType(): void {
    $mock = new EntityDisplayModeBaseTestableClass(['something' => 'nothing'], 'test_type');

    // Some test values.
    $bad_target = 'uninitialized';
    $target = 'test_target_type';

    // Gain access to the protected property.
    $property = new \ReflectionProperty($mock, 'targetEntityType');
    // Set the property to a known state.
    $property->setValue($mock, $bad_target);

    // Set the target type.
    $mock->setTargetType($target);

    // Test the outcome.
    $this->assertNotEquals($bad_target, $property->getValue($mock));
    $this->assertEquals($target, $property->getValue($mock));
  }

  /**
   * Tests get target type.
   */
  public function testGetTargetType(): void {
    $mock = new EntityDisplayModeBaseTestableClass(['something' => 'nothing'], 'test_type');

    // A test value.
    $target = 'test_target_type';

    // Gain access to the protected property.
    $property = new \ReflectionProperty($mock, 'targetEntityType');
    // Set the property to a known state.
    $property->setValue($mock, $target);

    // Get the target type.
    $value = $mock->getTargetType($target);

    // Test the outcome.
    $this->assertEquals($value, $property->getValue($mock));
  }

}

/**
 * A class extending EntityDisplayModeBase for testing purposes.
 */
class EntityDisplayModeBaseTestableClass extends EntityDisplayModeBase {
}
