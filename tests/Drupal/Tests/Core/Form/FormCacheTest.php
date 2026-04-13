<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormCache;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactory;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\Core\Form\FormCache.
 */
#[CoversClass(FormCache::class)]
#[Group('Form')]
class FormCacheTest extends UnitTestCase {

  /**
   * The form cache object under test.
   *
   * @var \Drupal\Core\Form\FormCache
   */
  protected $formCache;

  /**
   * The expirable key value factory.
   */
  protected KeyValueExpirableFactory $keyValueExpirableFactory;

  /**
   * The current user.
   */
  protected AccountInterface&Stub $account;

  /**
   * The CSRF token generator.
   */
  protected CsrfTokenGenerator&Stub $csrfToken;

  /**
   * The mocked module handler.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $moduleHandler;

  /**
   * The expirable key value store used by form cache.
   */
  protected KeyValueStoreExpirableInterface&Stub $formCacheStore;

  /**
   * The expirable key value store used by form state cache.
   */
  protected KeyValueStoreExpirableInterface&Stub $formStateCacheStore;

  /**
   * The logger channel.
   */
  protected LoggerInterface&Stub $logger;

  /**
   * The request stack.
   */
  protected RequestStack&Stub $requestStack;

  /**
   * A policy rule determining the cacheability of a request.
   */
  protected RequestPolicyInterface&Stub $requestPolicy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->prophesize('Drupal\Core\Extension\ModuleHandlerInterface');

    $this->formCacheStore = $this->createStub(KeyValueStoreExpirableInterface::class);
    $this->formStateCacheStore = $this->createStub(KeyValueStoreExpirableInterface::class);
    $this->keyValueExpirableFactory = new KeyValueExpirableFactory(new ContainerBuilder());
    // Use reflection to set the factory stores so we avoid dealing with using a
    // container.
    $reflection = new \ReflectionProperty($this->keyValueExpirableFactory, 'stores');
    $reflection->setValue($this->keyValueExpirableFactory, [
      'form' => $this->formCacheStore,
      'form_state' => $this->formStateCacheStore,
    ]);

    $this->csrfToken = $this->createStub(CsrfTokenGenerator::class);
    $this->account = $this->createStub(AccountInterface::class);

    $this->logger = $this->createStub(LoggerInterface::class);
    $this->requestStack = $this->createStub(RequestStack::class);
    $this->requestPolicy = $this->createStub(RequestPolicyInterface::class);

    $this->formCache = new FormCache($this->root, $this->keyValueExpirableFactory, $this->moduleHandler->reveal(), $this->account, $this->csrfToken, $this->logger, $this->requestStack, $this->requestPolicy);
  }

  /**
   * Reinitializes the account as a mock object.
   */
  protected function setUpMockAccount(): void {
    $this->account = $this->createMock(AccountInterface::class);
    $reflection = new \ReflectionProperty($this->formCache, 'currentUser');
    $reflection->setValue($this->formCache, $this->account);
  }

  /**
   * Reinitializes the CSRF token generator as a mock object.
   */
  protected function setUpMockCsrfTokenGenerator(): void {
    $this->csrfToken = $this->createMock(CsrfTokenGenerator::class);
    $reflection = new \ReflectionProperty($this->formCache, 'csrfToken');
    $reflection->setValue($this->formCache, $this->csrfToken);
  }

  /**
   * Reinitializes the form cache store as a mock object.
   */
  protected function setUpMockFormCacheStore(): void {
    $this->formCacheStore = $this->createMock(KeyValueStoreExpirableInterface::class);
    $reflection = new \ReflectionProperty($this->keyValueExpirableFactory, 'stores');
    $value = $reflection->getValue($this->keyValueExpirableFactory);
    $value['form'] = $this->formCacheStore;
    $reflection->setValue($this->keyValueExpirableFactory, $value);
  }

  /**
   * Reinitializes the form state cache store as a mock object.
   */
  protected function setUpMockFormStateCacheStore(): void {
    $this->formStateCacheStore = $this->createMock(KeyValueStoreExpirableInterface::class);
    $reflection = new \ReflectionProperty($this->keyValueExpirableFactory, 'stores');
    $value = $reflection->getValue($this->keyValueExpirableFactory);
    $value['form_state'] = $this->formStateCacheStore;
    $reflection->setValue($this->keyValueExpirableFactory, $value);
  }

  /**
   * Reinitializes the logger as a mock object.
   */
  protected function setUpMockLogger(): void {
    $this->logger = $this->createMock(LoggerInterface::class);
    $reflection = new \ReflectionProperty($this->formCache, 'logger');
    $reflection->setValue($this->formCache, $this->logger);
  }

  /**
   * Tests get cache valid token.
   */
  public function testGetCacheValidToken(): void {
    $this->setUpMockAccount();
    $this->setUpMockCsrfTokenGenerator();
    $this->setUpMockFormCacheStore();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cache_token = 'the_cache_token';
    $cached_form = ['#cache_token' => $cache_token];

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with($cache_token)
      ->willReturn(TRUE);
    $this->account->expects($this->never())
      ->method('isAnonymous');

    $form = $this->formCache->getCache($form_build_id, $form_state);
    $this->assertSame($cached_form, $form);
  }

  /**
   * Tests get cache invalid token.
   */
  public function testGetCacheInvalidToken(): void {
    $this->setUpMockAccount();
    $this->setUpMockCsrfTokenGenerator();
    $this->setUpMockFormCacheStore();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cache_token = 'the_cache_token';
    $cached_form = ['#cache_token' => $cache_token];

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with($cache_token)
      ->willReturn(FALSE);
    $this->account->expects($this->never())
      ->method('isAnonymous');

    $form = $this->formCache->getCache($form_build_id, $form_state);
    $this->assertNull($form);
  }

  /**
   * Tests get cache anon user.
   */
  public function testGetCacheAnonUser(): void {
    $this->setUpMockAccount();
    $this->setUpMockCsrfTokenGenerator();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cached_form = ['#cache_token' => NULL];
    $this->setUpMockFormCacheStore();

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->account->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(TRUE);
    $this->csrfToken->expects($this->never())
      ->method('validate');

    $form = $this->formCache->getCache($form_build_id, $form_state);
    $this->assertSame($cached_form, $form);
  }

  /**
   * Tests get cache auth user.
   */
  public function testGetCacheAuthUser(): void {
    $this->setUpMockAccount();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cached_form = ['#cache_token' => NULL];
    $this->setUpMockFormCacheStore();

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->account->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(FALSE);

    $form = $this->formCache->getCache($form_build_id, $form_state);
    $this->assertNull($form);
  }

  /**
   * Tests get cache no form.
   */
  public function testGetCacheNoForm(): void {
    $this->setUpMockAccount();
    $this->setUpMockFormCacheStore();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cached_form = NULL;

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->account->expects($this->never())
      ->method('isAnonymous');

    $form = $this->formCache->getCache($form_build_id, $form_state);
    $this->assertNull($form);
  }

  /**
   * Tests load cached form state.
   */
  public function testLoadCachedFormState(): void {
    $this->setUpMockAccount();
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cached_form = ['#cache_token' => NULL];

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->account->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(TRUE);

    $cached_form_state = ['storage' => ['foo' => 'bar']];
    $this->formStateCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form_state);

    $this->formCache->getCache($form_build_id, $form_state);
    $this->assertSame($cached_form_state['storage'], $form_state->getStorage());
  }

  /**
   * Tests load cached form state with files.
   */
  public function testLoadCachedFormStateWithFiles(): void {
    $this->setUpMockAccount();
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();

    $form_build_id = 'the_form_build_id';
    $form_state = new FormState();
    $cached_form = ['#cache_token' => NULL];

    $this->formCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form);
    $this->account->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(TRUE);

    $cached_form_state = [
      'build_info' => [
        'files' => [
          [
            'module' => 'a_module',
            'type' => 'the_type',
            'name' => 'some_name',
          ],
          ['module' => 'another_module'],
        ],
      ],
    ];
    $this->moduleHandler->loadInclude('a_module', 'the_type', 'some_name')
      ->shouldBeCalledOnce();
    $this->moduleHandler->loadInclude('another_module', 'inc', 'another_module')
      ->shouldBeCalledOnce();
    $this->formStateCacheStore->expects($this->once())
      ->method('get')
      ->with($form_build_id)
      ->willReturn($cached_form_state);

    $this->formCache->getCache($form_build_id, $form_state);
  }

  /**
   * Tests set cache with form.
   */
  public function testSetCacheWithForm(): void {
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();

    $form_build_id = 'the_form_build_id';
    $form = [
      '#form_id' => 'the_form_id',
    ];
    $form_state = new FormState();

    $this->formCacheStore->expects($this->once())
      ->method('setWithExpire')
      ->with($form_build_id, $form, $this->isInt());

    $form_state_data = $form_state->getCacheableArray();
    $this->formStateCacheStore->expects($this->once())
      ->method('setWithExpire')
      ->with($form_build_id, $form_state_data, $this->isInt());

    $this->formCache->setCache($form_build_id, $form, $form_state);
  }

  /**
   * Tests set cache without form.
   */
  public function testSetCacheWithoutForm(): void {
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();

    $form_build_id = 'the_form_build_id';
    $form = NULL;
    $form_state = new FormState();

    $this->formCacheStore->expects($this->never())
      ->method('setWithExpire');

    $form_state_data = $form_state->getCacheableArray();
    $this->formStateCacheStore->expects($this->once())
      ->method('setWithExpire')
      ->with($form_build_id, $form_state_data, $this->isInt());

    $this->formCache->setCache($form_build_id, $form, $form_state);
  }

  /**
   * Tests set cache auth user.
   */
  public function testSetCacheAuthUser(): void {
    $this->setUpMockAccount();
    $this->setUpMockCsrfTokenGenerator();
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();

    $form_build_id = 'the_form_build_id';
    $form = [];
    $form_state = new FormState();

    $cache_token = 'the_cache_token';
    $form_data = $form;
    $form_data['#cache_token'] = $cache_token;
    $this->formCacheStore->expects($this->once())
      ->method('setWithExpire')
      ->with($form_build_id, $form_data, $this->isInt());

    $form_state_data = $form_state->getCacheableArray();
    $this->formStateCacheStore->expects($this->once())
      ->method('setWithExpire')
      ->with($form_build_id, $form_state_data, $this->isInt());

    $this->csrfToken->expects($this->once())
      ->method('get')
      ->willReturn($cache_token);
    $this->account->expects($this->once())
      ->method('isAuthenticated')
      ->willReturn(TRUE);

    $this->formCache->setCache($form_build_id, $form, $form_state);
  }

  /**
   * Tests set cache build id mismatch.
   */
  public function testSetCacheBuildIdMismatch(): void {
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();
    $this->setUpMockLogger();

    $form_build_id = 'the_form_build_id';
    $form = [
      '#form_id' => 'the_form_id',
      '#build_id' => 'stale_form_build_id',
    ];
    $form_state = new FormState();

    $this->formCacheStore->expects($this->never())
      ->method('setWithExpire');
    $this->formStateCacheStore->expects($this->never())
      ->method('setWithExpire');
    $this->logger->expects($this->once())
      ->method('error')
      ->with('Form build-id mismatch detected while attempting to store a form in the cache.');
    $this->formCache->setCache($form_build_id, $form, $form_state);
  }

  /**
   * Tests delete cache.
   */
  public function testDeleteCache(): void {
    $this->setUpMockFormCacheStore();
    $this->setUpMockFormStateCacheStore();
    $form_build_id = 'the_form_build_id';

    $this->formCacheStore->expects($this->once())
      ->method('delete')
      ->with($form_build_id);
    $this->formStateCacheStore->expects($this->once())
      ->method('delete')
      ->with($form_build_id);
    $this->formCache->deleteCache($form_build_id);
  }

}
