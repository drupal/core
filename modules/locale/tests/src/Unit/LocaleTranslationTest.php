<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\locale\LocaleTranslation;
use Drupal\locale\StringStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\locale\LocaleTranslation.
 */
#[CoversClass(LocaleTranslation::class)]
#[Group('locale')]
class LocaleTranslationTest extends UnitTestCase {

  /**
   * A stub storage to use when instantiating LocaleTranslation objects.
   */
  protected StringStorageInterface&Stub $storage;

  /**
   * A stub lock to use when instantiating LocaleTranslation objects.
   */
  protected LockBackendInterface&Stub $lock;

  /**
   * A stub cache to use when instantiating LocaleTranslation objects.
   */
  protected CacheBackendInterface&Stub $cache;

  /**
   * A stub language manager built from LanguageManagerInterface.
   */
  protected LanguageManagerInterface&Stub $languageManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->storage = $this->createStub(StringStorageInterface::class);
    $this->cache = $this->createStub(CacheBackendInterface::class);
    $this->lock = $this->createStub(LockBackendInterface::class);
    $this->languageManager = $this->createStub(LanguageManagerInterface::class);
    $this->requestStack = new RequestStack();
  }

  /**
   * Tests for \Drupal\locale\LocaleTranslation::destruct().
   */
  public function testDestruct(): void {
    $translation = new LocaleTranslation($this->storage, $this->cache, $this->lock, $this->getConfigFactoryStub(), $this->languageManager, $this->requestStack);
    // Prove that destruction works without errors when translations are empty.
    $this->assertNull($translation->destruct());
  }

}
