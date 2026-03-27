<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Unit;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageEffectBase;
use Drupal\image\ImageEffectManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests Drupal\image\Entity\ImageStyle.
 */
#[CoversClass(ImageStyle::class)]
#[Group('Image')]
class ImageStyleTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entityType;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Gets a mocked image style for testing.
   *
   * @param string $image_effect_id
   *   The image effect ID.
   * @param \Drupal\image\ImageEffectInterface|\PHPUnit\Framework\MockObject\MockObject $image_effect
   *   The image effect used for testing.
   * @param array $stubs
   *   An array of additional method names to mock.
   *
   * @return \Drupal\image\Entity\ImageStyle&\PHPUnit\Framework\MockObject\MockObject
   *   The mocked image style.
   */
  protected function getImageStyleMock($image_effect_id, $image_effect, $stubs = []): ImageStyle&MockObject {
    $effectManager = $this->createStub(ImageEffectManager::class);
    $effectManager
      ->method('createInstance')
      ->willReturn($image_effect);
    $default_stubs = ['getImageEffectPluginManager', 'fileDefaultScheme'];
    $image_style = $this->getMockBuilder('\Drupal\image\Entity\ImageStyle')
      ->setConstructorArgs([
        ['effects' => [$image_effect_id => ['id' => $image_effect_id]]],
        $this->entityTypeId,
      ])
      ->onlyMethods(array_merge($default_stubs, $stubs))
      ->getMock();

    $image_style
      ->method('getImageEffectPluginManager')
      ->willReturn($effectManager);
    $image_style
      ->method('fileDefaultScheme')
      ->willReturnCallback([$this, 'fileDefaultScheme']);

    return $image_style;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeId = $this->randomMachineName();
    $provider = $this->randomMachineName();
    $this->entityType = $this->createStub(EntityTypeInterface::class);
    $this->entityType
      ->method('getProvider')
      ->willReturn($provider);
    $this->entityTypeManager = $this->createStub(EntityTypeManagerInterface::class);
  }

  /**
   * Tests get derivative extension.
   */
  public function testGetDerivativeExtension(): void {
    $image_effect_id = $this->randomMachineName();
    $image_effect = $this->createStub(ImageEffectBase::class);
    $image_effect
      ->method('getDerivativeExtension')
      ->willReturn('png');

    $image_style = $this->getImageStyleMock($image_effect_id, $image_effect);
    $image_style->expects($this->once())
      ->method('getImageEffectPluginManager');

    $extensions = ['jpeg', 'gif', 'png'];
    foreach ($extensions as $extension) {
      $extensionReturned = $image_style->getDerivativeExtension($extension);
      $this->assertEquals('png', $extensionReturned);
    }
  }

  /**
   * Tests build uri.
   */
  public function testBuildUri(): void {
    // Image style that changes the extension.
    $image_effect_id = $this->randomMachineName();
    $image_effect = $this->createStub(ImageEffectBase::class);
    $image_effect
      ->method('getDerivativeExtension')
      ->willReturn('png');

    $image_style = $this->getImageStyleMock($image_effect_id, $image_effect);
    $image_style->expects($this->once())
      ->method('fileDefaultScheme');
    $this->assertEquals($image_style->buildUri('public://test.jpeg'), 'public://styles/' . $image_style->id() . '/public/test.jpeg.png');

    // Image style that doesn't change the extension.
    $image_effect_id = $this->randomMachineName();
    $image_effect = $this->createStub('\Drupal\image\ImageEffectBase');
    $image_effect
      ->method('getDerivativeExtension')
      ->willReturnArgument(0);

    $image_style = $this->getImageStyleMock($image_effect_id, $image_effect);
    $image_style->expects($this->once())
      ->method('fileDefaultScheme');
    $this->assertEquals($image_style->buildUri('public://test.jpeg'), 'public://styles/' . $image_style->id() . '/public/test.jpeg');
  }

  /**
   * Tests get path token.
   */
  public function testGetPathToken(): void {
    $logger = $this->createStub(LoggerInterface::class);
    $private_key = $this->randomMachineName();
    $hash_salt = $this->randomMachineName();

    // Image style that changes the extension.
    $image_effect_id = $this->randomMachineName();
    $image_effect = $this->getMockBuilder('\Drupal\image\ImageEffectBase')
      ->setConstructorArgs([[], $image_effect_id, [], $logger])
      ->getMock();
    $image_effect->expects($this->atLeastOnce())
      ->method('getDerivativeExtension')
      ->willReturn('png');

    $image_style = $this->getImageStyleMock($image_effect_id, $image_effect, ['getPrivateKey', 'getHashSalt']);
    $image_style->expects($this->atLeastOnce())
      ->method('getPrivateKey')
      ->willReturn($private_key);
    $image_style->expects($this->atLeastOnce())
      ->method('getHashSalt')
      ->willReturn($hash_salt);

    // Assert the extension has been added to the URI before creating the token.
    $this->assertEquals($image_style->getPathToken('public://test.jpeg.png'), $image_style->getPathToken('public://test.jpeg'));
    $this->assertEquals(substr(Crypt::hmacBase64($image_style->id() . ':public://test.jpeg.png', $private_key . $hash_salt), 0, 8), $image_style->getPathToken('public://test.jpeg'));
    $this->assertNotEquals(substr(Crypt::hmacBase64($image_style->id() . ':public://test.jpeg', $private_key . $hash_salt), 0, 8), $image_style->getPathToken('public://test.jpeg'));

    // Image style that doesn't change the extension.
    $image_effect_id = $this->randomMachineName();
    $image_effect = $this->createStub(ImageEffectBase::class);
    $image_effect
      ->method('getDerivativeExtension')
      ->willReturnArgument(0);

    $image_style = $this->getImageStyleMock($image_effect_id, $image_effect, ['getPrivateKey', 'getHashSalt']);
    $image_style->expects($this->atLeastOnce())
      ->method('getPrivateKey')
      ->willReturn($private_key);
    $image_style->expects($this->atLeastOnce())
      ->method('getHashSalt')
      ->willReturn($hash_salt);
    // Assert no extension has been added to the uri before creating the token.
    $this->assertNotEquals($image_style->getPathToken('public://test.jpeg.png'), $image_style->getPathToken('public://test.jpeg'));
    $this->assertNotEquals(substr(Crypt::hmacBase64($image_style->id() . ':public://test.jpeg.png', $private_key . $hash_salt), 0, 8), $image_style->getPathToken('public://test.jpeg'));
    $this->assertEquals(substr(Crypt::hmacBase64($image_style->id() . ':public://test.jpeg', $private_key . $hash_salt), 0, 8), $image_style->getPathToken('public://test.jpeg'));
  }

  /**
   * Tests flush.
   */
  public function testFlush(): void {
    $cache_tag_invalidator = $this->createMock('\Drupal\Core\Cache\CacheTagsInvalidator');
    $file_system = $this->createStub(FileSystemInterface::class);
    $module_handler = $this->createStub(ModuleHandlerInterface::class);
    $stream_wrapper_manager = $this->createStub(StreamWrapperManagerInterface::class);
    $stream_wrapper_manager
      ->method('getWrappers')
      ->willReturn([]);
    $theme_registry = $this->createMock('\Drupal\Core\Theme\Registry');

    $container = new ContainerBuilder();
    $container->set('cache_tags.invalidator', $cache_tag_invalidator);
    $container->set('file_system', $file_system);
    $container->set('module_handler', $module_handler);
    $container->set('stream_wrapper_manager', $stream_wrapper_manager);
    $container->set('theme.registry', $theme_registry);
    \Drupal::setContainer($container);

    $image_effect_id = $this->randomMachineName();
    $image_effect = $this->createStub(ImageEffectBase::class);

    $image_style = $this->getImageStyleMock($image_effect_id, $image_effect, ['buildUri', 'getCacheTagsToInvalidate']);
    $image_style->expects($this->atLeastOnce())
      ->method('buildUri')
      ->willReturn('test.jpg');
    $image_style->expects($this->atLeastOnce())
      ->method('getCacheTagsToInvalidate')
      ->willReturn([]);

    // Assert the theme registry is reset.
    $theme_registry
      ->expects($this->once())
      ->method('reset');
    // Assert the cache tags are invalidated.
    $cache_tag_invalidator
      ->expects($this->once())
      ->method('invalidateTags');

    $image_style->flush();

    // Assert the theme registry is not reset a path is flushed.
    $theme_registry
      ->expects($this->never())
      ->method('reset');
    // Assert the cache tags are not reset a path is flushed.
    $cache_tag_invalidator
      ->expects($this->never())
      ->method('invalidateTags');

    $image_style->flush('test.jpg');

  }

  /**
   * Mock function for ImageStyle::fileDefaultScheme().
   */
  public function fileDefaultScheme() {
    return 'public';
  }

}
