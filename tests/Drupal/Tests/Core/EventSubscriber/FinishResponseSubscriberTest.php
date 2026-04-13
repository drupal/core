<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\EventSubscriber\FinishResponseSubscriber;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests Drupal\Core\EventSubscriber\FinishResponseSubscriber.
 */
#[CoversClass(FinishResponseSubscriber::class)]
#[Group('EventSubscriber')]
class FinishResponseSubscriberTest extends UnitTestCase {

  /**
   * The Kernel.
   */
  protected HttpKernelInterface&Stub $kernel;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface&Stub $languageManager;

  /**
   * The request policy.
   */
  protected RequestPolicyInterface&Stub $requestPolicy;

  /**
   * The response policy.
   */
  protected ResponsePolicyInterface&Stub $responsePolicy;

  /**
   * The cache contexts manager.
   */
  protected CacheContextsManager&Stub $cacheContextsManager;

  /**
   * The time service.
   */
  protected TimeInterface&Stub $time;

  protected function setUp(): void {
    parent::setUp();

    $this->kernel = $this->createStub(HttpKernelInterface::class);
    $this->languageManager = $this->createStub(LanguageManagerInterface::class);
    $this->requestPolicy = $this->createStub(RequestPolicyInterface::class);
    $this->responsePolicy = $this->createStub(ResponsePolicyInterface::class);
    $this->cacheContextsManager = $this->createStub(CacheContextsManager::class);
    $this->time = $this->createStub(TimeInterface::class);
  }

  /**
   * Finish subscriber should set some default header values.
   *
   * @legacy-covers ::onRespond
   */
  public function testDefaultHeaders(): void {
    $finishSubscriber = new FinishResponseSubscriber(
      $this->languageManager,
      $this->getConfigFactoryStub(),
      $this->requestPolicy,
      $this->responsePolicy,
      $this->cacheContextsManager,
      $this->time,
      FALSE
    );

    $this->languageManager->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));

    $request = $this->createStub(Request::class);
    $response = $this->createStub(Response::class);
    $response->headers = new ResponseHeaderBag();
    $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $finishSubscriber->onRespond($event);

    $this->assertEquals(['en'], $response->headers->all('Content-language'));
    $this->assertEquals(['nosniff'], $response->headers->all('X-Content-Type-Options'));
    $this->assertEquals(['SAMEORIGIN'], $response->headers->all('X-Frame-Options'));
  }

  /**
   * Finish subscriber should not overwrite existing header values.
   *
   * @legacy-covers ::onRespond
   */
  public function testExistingHeaders(): void {
    $finishSubscriber = new FinishResponseSubscriber(
      $this->languageManager,
      $this->getConfigFactoryStub(),
      $this->requestPolicy,
      $this->responsePolicy,
      $this->cacheContextsManager,
      $this->time,
      FALSE
    );

    $this->languageManager->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));

    $request = $this->createStub(Request::class);
    $response = $this->createStub(Response::class);
    $response->headers = new ResponseHeaderBag();
    $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

    $response->headers->set('X-Content-Type-Options', 'foo');
    $response->headers->set('X-Frame-Options', 'DENY');

    $finishSubscriber->onRespond($event);

    $this->assertEquals(['en'], $response->headers->all('Content-language'));
    // 'X-Content-Type-Options' will be unconditionally set by core.
    $this->assertEquals(['nosniff'], $response->headers->all('X-Content-Type-Options'));
    $this->assertEquals(['DENY'], $response->headers->all('X-Frame-Options'));
  }

}
