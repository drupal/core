<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests Drupal\Core\Config\ConfigFactory.
 */
#[CoversClass(ConfigFactory::class)]
#[Group('Config')]
class ConfigFactoryTest extends UnitTestCase {

  /**
   * Config factory under test.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Storage.
   */
  protected StorageInterface&MockObject $storage;

  /**
   * Event Dispatcher.
   */
  protected EventDispatcherInterface&Stub $eventDispatcher;

  /**
   * Typed Config.
   */
  protected TypedConfigManagerInterface&Stub $typedConfig;

  /**
   * The mocked cache tags invalidator.
   */
  protected CacheTagsInvalidatorInterface&MockObject $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->storage = $this->createMock('Drupal\Core\Config\StorageInterface');
    $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $this->typedConfig = $this->createStub(TypedConfigManagerInterface::class);
    $this->configFactory = new ConfigFactory($this->storage, $this->eventDispatcher, $this->typedConfig);

    $this->cacheTagsInvalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidatorInterface');

    $container = new ContainerBuilder();
    $container->set('cache_tags.invalidator', $this->cacheTagsInvalidator);
    \Drupal::setContainer($container);
  }

  /**
   * Tests rename.
   */
  public function testRename(): void {
    $old = new Config($this->randomMachineName(), $this->storage, $this->eventDispatcher, $this->typedConfig);
    $new = new Config($this->randomMachineName(), $this->storage, $this->eventDispatcher, $this->typedConfig);

    $this->storage->expects($this->exactly(2))
      ->method('readMultiple')
      ->willReturnMap([
        [[$old->getName()], $old->getRawData()],
        [[$new->getName()], $new->getRawData()],
      ]);

    $this->cacheTagsInvalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($old->getCacheTags());

    $this->storage->expects($this->once())
      ->method('rename')
      ->with($old->getName(), $new->getName());

    $this->configFactory->rename($old->getName(), $new->getName());
  }

}
