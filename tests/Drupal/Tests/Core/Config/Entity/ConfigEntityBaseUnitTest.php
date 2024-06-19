<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config\Entity;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\Core\Test\TestKernel;
use Drupal\Tests\Core\Config\Entity\Fixtures\ConfigEntityBaseWithPluginCollections;
use Drupal\Tests\Core\Plugin\Fixtures\TestConfigurablePlugin;
use Drupal\Tests\UnitTestCase;
use Drupal\TestTools\Random;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\Core\Config\Entity\ConfigEntityBase
 * @group Config
 */
class ConfigEntityBaseUnitTest extends UnitTestCase {

  /**
   * The entity under test.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityBase|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entity;

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

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
  protected $entityTypeId;

  /**
   * The UUID generator used for testing.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $uuid;

  /**
   * The provider of the entity type.
   */
  const PROVIDER = 'the_provider_of_the_entity_type';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The entity ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheTagsInvalidator;

  /**
   * The mocked typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $typedConfigManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $themeHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->id = $this->randomMachineName();
    $values = [
      'id' => $this->id,
      'langcode' => 'en',
      'uuid' => '3bb9ee60-bea5-4622-b89b-a63319d10b3a',
    ];
    $this->entityTypeId = $this->randomMachineName();
    $this->entityType = $this->createMock('\Drupal\Core\Config\Entity\ConfigEntityTypeInterface');
    $this->entityType->expects($this->any())
      ->method('getProvider')
      ->willReturn(static::PROVIDER);
    $this->entityType->expects($this->any())
      ->method('getConfigPrefix')
      ->willReturn('test_provider.' . $this->entityTypeId);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->willReturn($this->entityType);

    $this->uuid = $this->createMock('\Drupal\Component\Uuid\UuidInterface');

    $this->languageManager = $this->createMock('\Drupal\Core\Language\LanguageManagerInterface');
    $this->languageManager->expects($this->any())
      ->method('getLanguage')
      ->with('en')
      ->willReturn(new Language(['id' => 'en']));

    $this->cacheTagsInvalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidatorInterface');

    $this->typedConfigManager = $this->createMock('Drupal\Core\Config\TypedConfigManagerInterface');

    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->themeHandler = $this->prophesize(ThemeHandlerInterface::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);
    $container->set('language_manager', $this->languageManager);
    $container->set('cache_tags.invalidator', $this->cacheTagsInvalidator);
    $container->set('config.typed', $this->typedConfigManager);
    $container->set('module_handler', $this->moduleHandler->reveal());
    $container->set('theme_handler', $this->themeHandler->reveal());
    \Drupal::setContainer($container);

    $this->entity = $this->getMockBuilder(ConfigEntityBaseMockableClass::class)
      ->setConstructorArgs([$values, $this->entityTypeId])
      ->onlyMethods([])
      ->getMock();
  }

  /**
   * @covers ::calculateDependencies
   * @covers ::getDependencies
   */
  public function testCalculateDependencies(): void {
    // Calculating dependencies will reset the dependencies array.
    $this->entity->set('dependencies', ['module' => ['node']]);
    $this->assertEmpty($this->entity->calculateDependencies()->getDependencies());

    // Calculating dependencies will reset the dependencies array using enforced
    // dependencies.
    $this->entity->set('dependencies', ['module' => ['node'], 'enforced' => ['module' => 'views']]);
    $dependencies = $this->entity->calculateDependencies()->getDependencies();
    $this->assertStringContainsString('views', $dependencies['module']);
    $this->assertStringNotContainsString('node', $dependencies['module']);
  }

  /**
   * @covers ::preSave
   */
  public function testPreSaveDuringSync(): void {
    $this->moduleHandler->moduleExists('node')->willReturn(TRUE);

    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $storage = $this->createMock('\Drupal\Core\Config\Entity\ConfigEntityStorageInterface');

    $query->expects($this->any())
      ->method('execute')
      ->willReturn([]);
    $query->expects($this->any())
      ->method('condition')
      ->willReturn($query);
    $storage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);
    $storage->expects($this->any())
      ->method('loadUnchanged')
      ->willReturn($this->entity);

    // Saving an entity will not reset the dependencies array during config
    // synchronization.
    $this->entity->set('dependencies', ['module' => ['node']]);
    $this->entity->preSave($storage);
    $this->assertEmpty($this->entity->getDependencies());

    $this->entity->setSyncing(TRUE);
    $this->entity->set('dependencies', ['module' => ['node']]);
    $this->entity->preSave($storage);
    $dependencies = $this->entity->getDependencies();
    $this->assertContains('node', $dependencies['module']);
  }

  /**
   * @covers ::addDependency
   */
  public function testAddDependency(): void {
    $method = new \ReflectionMethod('\Drupal\Core\Config\Entity\ConfigEntityBase', 'addDependency');
    $method->invoke($this->entity, 'module', static::PROVIDER);
    $method->invoke($this->entity, 'module', 'core');
    $method->invoke($this->entity, 'module', 'node');
    $dependencies = $this->entity->getDependencies();
    $this->assertNotContains(static::PROVIDER, $dependencies['module']);
    $this->assertNotContains('core', $dependencies['module']);
    $this->assertContains('node', $dependencies['module']);

    // Test sorting of dependencies.
    $method->invoke($this->entity, 'module', 'action');
    $dependencies = $this->entity->getDependencies();
    $this->assertEquals(['action', 'node'], $dependencies['module']);

    // Test sorting of dependency types.
    $method->invoke($this->entity, 'entity', 'system.action.id');
    $dependencies = $this->entity->getDependencies();
    $this->assertEquals(['entity', 'module'], array_keys($dependencies));
  }

  /**
   * @covers ::getDependencies
   * @covers ::calculateDependencies
   *
   * @dataProvider providerCalculateDependenciesWithPluginCollections
   */
  public function testCalculateDependenciesWithPluginCollections(array $definition, array $expected_dependencies): void {
    $this->moduleHandler->moduleExists('the_provider_of_the_entity_type')->willReturn(TRUE);
    $this->moduleHandler->moduleExists('test')->willReturn(TRUE);
    $this->moduleHandler->moduleExists('test_theme')->willReturn(FALSE);

    $this->themeHandler->themeExists('test_theme')->willReturn(TRUE);

    $values = [];
    $this->entity = $this->getMockBuilder('\Drupal\Tests\Core\Config\Entity\Fixtures\ConfigEntityBaseWithPluginCollections')
      ->setConstructorArgs([$values, $this->entityTypeId])
      ->onlyMethods(['getPluginCollections'])
      ->getMock();

    // Create a configurable plugin that would add a dependency.
    $instance_id = $this->randomMachineName();
    $instance = new TestConfigurablePlugin([], $instance_id, $definition);

    // Create a plugin collection to contain the instance.
    $pluginCollection = $this->getMockBuilder('\Drupal\Core\Plugin\DefaultLazyPluginCollection')
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();
    $pluginCollection->expects($this->atLeastOnce())
      ->method('get')
      ->with($instance_id)
      ->willReturn($instance);
    $pluginCollection->addInstanceId($instance_id);

    // Return the mocked plugin collection.
    $this->entity->expects($this->once())
      ->method('getPluginCollections')
      ->willReturn([$pluginCollection]);

    $this->assertEquals($expected_dependencies, $this->entity->calculateDependencies()->getDependencies());
  }

  /**
   * Data provider for testCalculateDependenciesWithPluginCollections.
   *
   * @return array
   */
  public static function providerCalculateDependenciesWithPluginCollections(): array {
    // Start with 'a' so that order of the dependency array is fixed.
    $instance_dependency_1 = 'a' . Random::machineName(10);
    $instance_dependency_2 = 'a' . Random::machineName(11);

    return [
      // Tests that the plugin provider is a module dependency.
      [
        ['provider' => 'test'],
        ['module' => ['test']],
      ],
      // Tests that the plugin provider is a theme dependency.
      [
        ['provider' => 'test_theme'],
        ['theme' => ['test_theme']],
      ],
      // Tests that a plugin that is provided by the same module as the config
      // entity is not added to the dependencies array.
      [
        ['provider' => static::PROVIDER],
        [],
      ],
      // Tests that a config entity that has a plugin which provides config
      // dependencies in its definition has them.
      [
        [
          'provider' => 'test',
          'config_dependencies' => [
            'config' => [$instance_dependency_1],
            'module' => [$instance_dependency_2],
          ],
        ],
        [
          'config' => [$instance_dependency_1],
          'module' => [$instance_dependency_2, 'test'],
        ],
      ],
    ];
  }

  /**
   * @covers ::calculateDependencies
   * @covers ::getDependencies
   * @covers ::onDependencyRemoval
   */
  public function testCalculateDependenciesWithThirdPartySettings(): void {
    $this->entity = $this->getMockBuilder(ConfigEntityBaseMockableClass::class)
      ->setConstructorArgs([[], $this->entityTypeId])
      ->onlyMethods([])
      ->getMock();
    $this->entity->setThirdPartySetting('test_provider', 'test', 'test');
    $this->entity->setThirdPartySetting('test_provider2', 'test', 'test');
    $this->entity->setThirdPartySetting(static::PROVIDER, 'test', 'test');

    $this->assertEquals(['test_provider', 'test_provider2'], $this->entity->calculateDependencies()->getDependencies()['module']);
    $changed = $this->entity->onDependencyRemoval(['module' => ['test_provider2']]);
    $this->assertTrue($changed, 'Calling onDependencyRemoval with an existing third party dependency provider returns TRUE.');
    $changed = $this->entity->onDependencyRemoval(['module' => ['test_provider3']]);
    $this->assertFalse($changed, 'Calling onDependencyRemoval with a non-existing third party dependency provider returns FALSE.');
    $this->assertEquals(['test_provider'], $this->entity->calculateDependencies()->getDependencies()['module']);
  }

  /**
   * @covers ::__sleep
   */
  public function testSleepWithPluginCollections(): void {
    $instance_id = 'the_instance_id';
    $instance = new TestConfigurablePlugin([], $instance_id, []);

    $plugin_manager = $this->prophesize(PluginManagerInterface::class);
    $plugin_manager->createInstance($instance_id, Argument::any())->willReturn($instance);

    // Also set up a container with the plugin manager so that we can assert
    // that the plugin manager itself is also not serialized.
    $container = TestKernel::setContainerWithKernel();
    $container->set('plugin.manager.foo', $plugin_manager->reveal());

    $entity_values = ['the_plugin_collection_config' => [$instance_id => ['id' => $instance_id, 'foo' => 'original_value']]];
    $entity = new TestConfigEntityWithPluginCollections($entity_values, $this->entityTypeId);
    $entity->setPluginManager($plugin_manager->reveal());

    // After creating the entity, change the plugin configuration.
    $instance->setConfiguration(['id' => $instance_id, 'foo' => 'new_value']);

    // After changing the plugin configuration, the entity still has the
    // original value.
    $expected_plugin_config = [$instance_id => ['id' => $instance_id, 'foo' => 'original_value']];
    $this->assertSame($expected_plugin_config, $entity->get('the_plugin_collection_config'));

    // Ensure the plugin collection and manager is not stored.
    $vars = $entity->__sleep();
    $this->assertNotContains('pluginCollection', $vars);
    $this->assertNotContains('pluginManager', $vars);
    $this->assertSame(['pluginManager' => 'plugin.manager.foo'], $entity->get('_serviceIds'));

    $expected_plugin_config = [$instance_id => ['id' => $instance_id, 'foo' => 'new_value']];
    // Ensure the updated values are stored in the entity.
    $this->assertSame($expected_plugin_config, $entity->get('the_plugin_collection_config'));
  }

  /**
   * @covers ::setOriginalId
   * @covers ::getOriginalId
   */
  public function testGetOriginalId(): void {
    $new_id = $this->randomMachineName();
    $this->entity->set('id', $new_id);
    $this->assertSame($this->id, $this->entity->getOriginalId());
    $this->assertSame($this->entity, $this->entity->setOriginalId($new_id));
    $this->assertSame($new_id, $this->entity->getOriginalId());

    // Check that setOriginalId() does not change the entity "isNew" status.
    $this->assertFalse($this->entity->isNew());
    $this->entity->setOriginalId($this->randomMachineName());
    $this->assertFalse($this->entity->isNew());
    $this->entity->enforceIsNew();
    $this->assertTrue($this->entity->isNew());
    $this->entity->setOriginalId($this->randomMachineName());
    $this->assertTrue($this->entity->isNew());
  }

  /**
   * @covers ::isNew
   */
  public function testIsNew(): void {
    $this->assertFalse($this->entity->isNew());
    $this->assertSame($this->entity, $this->entity->enforceIsNew());
    $this->assertTrue($this->entity->isNew());
    $this->entity->enforceIsNew(FALSE);
    $this->assertFalse($this->entity->isNew());
  }

  /**
   * @covers ::set
   * @covers ::get
   */
  public function testGet(): void {
    $name = 'id';
    $value = $this->randomMachineName();
    $this->assertSame($this->id, $this->entity->get($name));
    $this->assertSame($this->entity, $this->entity->set($name, $value));
    $this->assertSame($value, $this->entity->get($name));
  }

  /**
   * @covers ::setStatus
   * @covers ::status
   */
  public function testSetStatus(): void {
    $this->assertTrue($this->entity->status());
    $this->assertSame($this->entity, $this->entity->setStatus(FALSE));
    $this->assertFalse($this->entity->status());
    $this->entity->setStatus(TRUE);
    $this->assertTrue($this->entity->status());
  }

  /**
   * @covers ::enable
   * @depends testSetStatus
   */
  public function testEnable(): void {
    $this->entity->setStatus(FALSE);
    $this->assertSame($this->entity, $this->entity->enable());
    $this->assertTrue($this->entity->status());
  }

  /**
   * @covers ::disable
   * @depends testSetStatus
   */
  public function testDisable(): void {
    $this->entity->setStatus(TRUE);
    $this->assertSame($this->entity, $this->entity->disable());
    $this->assertFalse($this->entity->status());
  }

  /**
   * @covers ::setSyncing
   * @covers ::isSyncing
   */
  public function testIsSyncing(): void {
    $this->assertFalse($this->entity->isSyncing());
    $this->assertSame($this->entity, $this->entity->setSyncing(TRUE));
    $this->assertTrue($this->entity->isSyncing());
    $this->entity->setSyncing(FALSE);
    $this->assertFalse($this->entity->isSyncing());
  }

  /**
   * @covers ::createDuplicate
   */
  public function testCreateDuplicate(): void {
    $this->entityType->expects($this->exactly(2))
      ->method('getKey')
      ->willReturnMap([
        ['id', 'id'],
        ['uuid', 'uuid'],
      ]);

    $this->entityType->expects($this->once())
      ->method('hasKey')
      ->with('uuid')
      ->willReturn(TRUE);

    $new_uuid = '8607ef21-42bc-4913-978f-8c06207b0395';
    $this->uuid->expects($this->once())
      ->method('generate')
      ->willReturn($new_uuid);

    $duplicate = $this->entity->createDuplicate();
    $this->assertInstanceOf('\Drupal\Core\Entity\EntityBase', $duplicate);
    $this->assertNotSame($this->entity, $duplicate);
    $this->assertFalse($this->entity->isNew());
    $this->assertTrue($duplicate->isNew());
    $this->assertNull($duplicate->id());
    $this->assertNull($duplicate->getOriginalId());
    $this->assertNotEquals($this->entity->uuid(), $duplicate->uuid());
    $this->assertSame($new_uuid, $duplicate->uuid());
  }

  /**
   * @covers ::sort
   */
  public function testSort(): void {
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->willReturn([
        'entity_keys' => [
          'label' => 'label',
        ],
      ]);

    $entity_a = $this->createMock(ConfigEntityBase::class);
    $entity_a->expects($this->atLeastOnce())
      ->method('label')
      ->willReturn('foo');
    $entity_b = $this->createMock(ConfigEntityBase::class);
    $entity_b->expects($this->atLeastOnce())
      ->method('label')
      ->willReturn('bar');

    // Test sorting by label.
    $list = [$entity_a, $entity_b];
    usort($list, '\Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    $this->assertSame($entity_b, $list[0]);

    $list = [$entity_b, $entity_a];
    usort($list, '\Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    $this->assertSame($entity_b, $list[0]);

    // Test sorting by weight.
    $entity_a->weight = 0;
    $entity_b->weight = 1;
    $list = [$entity_b, $entity_a];
    usort($list, '\Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    $this->assertSame($entity_a, $list[0]);

    $list = [$entity_a, $entity_b];
    usort($list, '\Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    $this->assertSame($entity_a, $list[0]);
  }

  /**
   * @covers ::toArray
   */
  public function testToArray(): void {
    $this->typedConfigManager->expects($this->never())
      ->method('getDefinition');
    $this->entityType->expects($this->any())
      ->method('getPropertiesToExport')
      ->willReturn(['id' => 'configId', 'dependencies' => 'dependencies']);
    $properties = $this->entity->toArray();
    $this->assertIsArray($properties);
    $this->assertEquals(['configId' => $this->entity->id(), 'dependencies' => []], $properties);
  }

  /**
   * @covers ::toArray
   */
  public function testToArrayIdKey(): void {
    $entity = $this->getMockBuilder(ConfigEntityBaseMockableClass::class)
      ->setConstructorArgs([[], $this->entityTypeId])
      ->onlyMethods(['id', 'get'])
      ->getMock();
    $entity->expects($this->atLeastOnce())
      ->method('id')
      ->willReturn($this->id);
    $entity->expects($this->once())
      ->method('get')
      ->with('dependencies')
      ->willReturn([]);
    $this->typedConfigManager->expects($this->never())
      ->method('getDefinition');
    $this->entityType->expects($this->any())
      ->method('getPropertiesToExport')
      ->willReturn(['id' => 'configId', 'dependencies' => 'dependencies']);
    $this->entityType->expects($this->once())
      ->method('getKey')
      ->with('id')
      ->willReturn('id');
    $properties = $entity->toArray();
    $this->assertIsArray($properties);
    $this->assertEquals(['configId' => $entity->id(), 'dependencies' => []], $properties);
  }

  /**
   * @covers ::getThirdPartySetting
   * @covers ::setThirdPartySetting
   * @covers ::getThirdPartySettings
   * @covers ::unsetThirdPartySetting
   * @covers ::getThirdPartyProviders
   */
  public function testThirdPartySettings(): void {
    $key = 'test';
    $third_party = 'test_provider';
    $value = $this->getRandomGenerator()->string();

    // Test getThirdPartySetting() with no settings.
    $this->assertEquals($value, $this->entity->getThirdPartySetting($third_party, $key, $value));
    $this->assertNull($this->entity->getThirdPartySetting($third_party, $key));

    // Test setThirdPartySetting().
    $this->entity->setThirdPartySetting($third_party, $key, $value);
    $this->assertEquals($value, $this->entity->getThirdPartySetting($third_party, $key));
    $this->assertEquals($value, $this->entity->getThirdPartySetting($third_party, $key, $this->getRandomGenerator()->string()));

    // Test getThirdPartySettings().
    $this->entity->setThirdPartySetting($third_party, 'test2', 'value2');
    $this->assertEquals([$key => $value, 'test2' => 'value2'], $this->entity->getThirdPartySettings($third_party));

    // Test getThirdPartyProviders().
    $this->entity->setThirdPartySetting('test_provider2', $key, $value);
    $this->assertEquals([$third_party, 'test_provider2'], $this->entity->getThirdPartyProviders());

    // Test unsetThirdPartyProviders().
    $this->entity->unsetThirdPartySetting('test_provider2', $key);
    $this->assertEquals([$third_party], $this->entity->getThirdPartyProviders());
  }

  /**
   * @covers ::toArray
   */
  public function testToArraySchemaException(): void {
    $this->entityType->expects($this->any())
      ->method('getPropertiesToExport')
      ->willReturn(NULL);
    $this->entityType->expects($this->any())
      ->method('getClass')
      ->willReturn("FooConfigEntity");
    $this->expectException(SchemaIncompleteException::class);
    $this->expectExceptionMessage("Entity type 'FooConfigEntity' is missing 'config_export' definition in its annotation");
    $this->entity->toArray();
  }

  /**
   * @covers ::set
   * @dataProvider providerTestSetAndPreSaveWithPluginCollections
   */
  public function testSetWithPluginCollections(bool $syncing, string $expected_value): void {
    $instance_id = 'the_instance_id';
    $instance = new TestConfigurablePlugin(['foo' => 'original_value'], $instance_id, []);

    $plugin_manager = $this->prophesize(PluginManagerInterface::class);
    if ($syncing) {
      $plugin_manager->createInstance(Argument::cetera())->shouldNotBeCalled();
    }
    else {
      $plugin_manager->createInstance($instance_id, Argument::any())->willReturn($instance);
    }

    $entity_values = ['the_plugin_collection_config' => [$instance_id => ['id' => $instance_id, 'foo' => 'original_value']]];
    $entity = new TestConfigEntityWithPluginCollections($entity_values, $this->entityTypeId);
    $entity->setSyncing($syncing);
    $entity->setPluginManager($plugin_manager->reveal());

    // After creating the entity, change the configuration using the entity.
    $entity->set('the_plugin_collection_config', [$instance_id => ['id' => $instance_id, 'foo' => 'new_value']]);

    $this->assertSame($expected_value, $instance->getConfiguration()['foo']);
  }

  /**
   * @covers ::preSave
   * @dataProvider providerTestSetAndPreSaveWithPluginCollections
   */
  public function testPreSaveWithPluginCollections(bool $syncing, string $expected_value): void {
    $instance_id = 'the_instance_id';
    $instance = new TestConfigurablePlugin(['foo' => 'original_value'], $instance_id, ['provider' => 'core']);

    $plugin_manager = $this->prophesize(PluginManagerInterface::class);
    if ($syncing) {
      $plugin_manager->createInstance(Argument::cetera())->shouldNotBeCalled();
    }
    else {
      $plugin_manager->createInstance($instance_id, Argument::any())->willReturn($instance);
    }

    $entity_values = ['the_plugin_collection_config' => [$instance_id => ['id' => $instance_id, 'foo' => 'original_value']]];
    $entity = new TestConfigEntityWithPluginCollections($entity_values, $this->entityTypeId);
    $entity->setSyncing($syncing);
    $entity->setPluginManager($plugin_manager->reveal());

    // After creating the entity, change the plugin configuration.
    $instance->setConfiguration(['foo' => 'new_value']);

    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $storage = $this->createMock('\Drupal\Core\Config\Entity\ConfigEntityStorageInterface');

    $query->expects($this->any())
      ->method('execute')
      ->willReturn([]);
    $query->expects($this->any())
      ->method('condition')
      ->willReturn($query);
    $storage->expects($this->any())
      ->method('getQuery')
      ->willReturn($query);
    $storage->expects($this->any())
      ->method('loadUnchanged')
      ->willReturn($entity);

    $entity->preSave($storage);

    $this->assertSame($expected_value, $entity->get('the_plugin_collection_config')[$instance_id]['foo']);
  }

  public static function providerTestSetAndPreSaveWithPluginCollections(): array {
    return [
      'Not syncing' => [FALSE, 'new_value'],
      'Syncing' => [TRUE, 'original_value'],
    ];
  }

}

class TestConfigEntityWithPluginCollections extends ConfigEntityBaseWithPluginCollections {

  protected $pluginCollection;

  protected $pluginManager;

  protected array $the_plugin_collection_config = [];

  public function setPluginManager(PluginManagerInterface $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new DefaultLazyPluginCollection($this->pluginManager, $this->the_plugin_collection_config);
    }
    return ['the_plugin_collection_config' => $this->pluginCollection];
  }

}

/**
 * A class extending ConfigEntityBase for testing purposes.
 */
class ConfigEntityBaseMockableClass extends ConfigEntityBase {

}
