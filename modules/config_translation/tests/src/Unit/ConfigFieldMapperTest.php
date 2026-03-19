<?php

declare(strict_types=1);

namespace Drupal\Tests\config_translation\Unit;

use Drupal\config_translation\ConfigFieldMapper;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\locale\LocaleConfigManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the functionality provided by the configuration field mapper.
 */
#[CoversClass(ConfigFieldMapper::class)]
#[Group('config_translation')]
class ConfigFieldMapperTest extends UnitTestCase {

  /**
   * The configuration field mapper to test.
   *
   * @var \Drupal\config_translation\ConfigFieldMapper
   */
  protected $configFieldMapper;

  /**
   * The field config instance used for testing.
   *
   * @var \Drupal\field\FieldConfigInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entity;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createStub(EntityTypeManagerInterface::class);
    $this->entity = $this->createStub(FieldConfigInterface::class);

    $definition = [
      'class' => '\Drupal\config_translation\ConfigFieldMapper',
      'base_route_name' => 'entity.field_config.node_field_edit_form',
      'title' => '@label field',
      'names' => [],
      'entity_type' => 'field_config',
    ];

    $this->configFieldMapper = new ConfigFieldMapper(
      'node_fields',
      $definition,
      $this->getConfigFactoryStub(),
      $this->createStub(TypedConfigManagerInterface::class),
      $this->createStub(LocaleConfigManager::class),
      $this->createStub(ConfigMapperManagerInterface::class),
      $this->createStub(RouteProviderInterface::class),
      $this->getStringTranslationStub(),
      $this->entityTypeManager,
      $this->createStub(LanguageManagerInterface::class),
      $this->createStub(EventDispatcherInterface::class)
    );
  }

  /**
   * Tests ConfigFieldMapper::setEntity().
   */
  public function testSetEntity(): void {
    $entity_type = $this->createStub(ConfigEntityTypeInterface::class);
    $entity_type
      ->method('getConfigPrefix')
      ->willReturn('config_prefix');

    $this->entityTypeManager
      ->method('getDefinition')
      ->willReturn($entity_type);

    $field_storage = $this->createStub(FieldStorageConfigInterface::class);
    $field_storage
      ->method('id')
      ->willReturn('field_storage_id');

    $this->entity
      ->method('getFieldStorageDefinition')
      ->willReturn($field_storage);

    $result = $this->configFieldMapper->setEntity($this->entity);
    $this->assertTrue($result);

    // Ensure that the configuration name was added to the mapper.
    $plugin_definition = $this->configFieldMapper->getPluginDefinition();
    $this->assertContains('config_prefix.field_storage_id', $plugin_definition['names']);

    // Make sure setEntity() returns FALSE when called a second time.
    $result = $this->configFieldMapper->setEntity($this->entity);
    $this->assertFalse($result);
  }

}
