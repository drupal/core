<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\EventDispatcher;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\Component\EventDispatcher\Event;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

/**
 * Unit tests for the ContainerAwareEventDispatcher.
 *
 * NOTE: Most of this code is a literal copy of Symfony 3.4's
 * Symfony\Component\EventDispatcher\Tests\AbstractEventDispatcherTest.
 *
 * This file does NOT follow Drupal coding standards, so as to simplify future
 * synchronizations.
 *
 * @group EventDispatcher
 */
class ContainerAwareEventDispatcherTest extends TestCase {

  use ExpectDeprecationTrait;

  /* Some pseudo events */
  const PRE_FOO = 'pre.foo';
  const POST_FOO = 'post.foo';
  const PRE_BAR = 'pre.bar';
  const POST_BAR = 'post.bar';

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  private $dispatcher;
  private $listener;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->dispatcher = $this->createEventDispatcher();
    $this->listener = new TestEventListener();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->dispatcher = NULL;
    $this->listener = NULL;
  }

  protected function createEventDispatcher() {
    $container = new Container();

    return new ContainerAwareEventDispatcher($container);
  }

  public function testGetListenersWithCallables(): void {
    // When passing in callables exclusively as listeners into the event
    // dispatcher constructor, the event dispatcher must not attempt to
    // resolve any services.
    $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
    $container->expects($this->never())->method($this->anything());

    $firstListener = new CallableClass();
    $secondListener = function () {

    };
    $thirdListener = [new TestEventListener(), 'preFoo'];
    $listeners = [
      'test_event' => [
        0 => [
          ['callable' => $firstListener],
          ['callable' => $secondListener],
          ['callable' => $thirdListener],
        ],
      ],
    ];

    $dispatcher = new ContainerAwareEventDispatcher($container, $listeners);
    $actualListeners = $dispatcher->getListeners();

    $expectedListeners = [
      'test_event' => [
        $firstListener,
        $secondListener,
        $thirdListener,
      ],
    ];

    $this->assertSame($expectedListeners, $actualListeners);
  }

  public function testDispatchWithCallables(): void {
    // When passing in callables exclusively as listeners into the event
    // dispatcher constructor, the event dispatcher must not attempt to
    // resolve any services.
    $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
    $container->expects($this->never())->method($this->anything());

    $firstListener = new CallableClass();
    $secondListener = function () {

    };
    $thirdListener = [new TestEventListener(), 'preFoo'];
    $listeners = [
      'test_event' => [
        0 => [
          ['callable' => $firstListener],
          ['callable' => $secondListener],
          ['callable' => $thirdListener],
        ],
      ],
    ];

    $dispatcher = new ContainerAwareEventDispatcher($container, $listeners);
    $dispatcher->dispatch(new Event(), 'test_event');

    $this->assertTrue($thirdListener[0]->preFooInvoked);
  }

  public function testGetListenersWithServices(): void {
    $container = new ContainerBuilder();
    $container->register('listener_service', TestEventListener::class);

    $listeners = [
      'test_event' => [
        0 => [
          ['service' => ['listener_service', 'preFoo']],
        ],
      ],
    ];

    $dispatcher = new ContainerAwareEventDispatcher($container, $listeners);
    $actualListeners = $dispatcher->getListeners();

    $listenerService = $container->get('listener_service');
    $expectedListeners = [
      'test_event' => [
        [$listenerService, 'preFoo'],
      ],
    ];

    $this->assertSame($expectedListeners, $actualListeners);
  }

  /**
   * Tests dispatching Symfony events with core's event dispatcher.
   */
  public function testSymfonyEventDispatching(): void {
    $container = new ContainerBuilder();
    $dispatcher = new ContainerAwareEventDispatcher($container, []);
    $dispatcher->dispatch(new GenericEvent());
  }

  public function testDispatchWithServices(): void {
    $container = new ContainerBuilder();
    $container->register('listener_service', TestEventListener::class);

    $listeners = [
      'test_event' => [
        0 => [
          ['service' => ['listener_service', 'preFoo']],
        ],
      ],
    ];

    $dispatcher = new ContainerAwareEventDispatcher($container, $listeners);

    $dispatcher->dispatch(new Event(), 'test_event');

    $listenerService = $container->get('listener_service');
    $this->assertTrue($listenerService->preFooInvoked);
  }

  public function testRemoveService(): void {
    $container = new ContainerBuilder();
    $container->register('listener_service', TestEventListener::class);
    $container->register('other_listener_service', TestEventListener::class);

    $listeners = [
      'test_event' => [
        0 => [
          ['service' => ['listener_service', 'preFoo']],
          ['service' => ['other_listener_service', 'preFoo']],
        ],
      ],
    ];

    $dispatcher = new ContainerAwareEventDispatcher($container, $listeners);

    $listenerService = $container->get('listener_service');
    $dispatcher->removeListener('test_event', [$listenerService, 'preFoo']);

    // Ensure that other service was not initialized during removal of the
    // listener service.
    $this->assertFalse($container->initialized('other_listener_service'));

    $dispatcher->dispatch(new Event(), 'test_event');

    $this->assertFalse($listenerService->preFooInvoked);
    $otherService = $container->get('other_listener_service');
    $this->assertTrue($otherService->preFooInvoked);
  }

  public function testGetListenerPriorityWithServices(): void {
    $container = new ContainerBuilder();
    $container->register('listener_service', TestEventListener::class);

    $listeners = [
      'test_event' => [
        5 => [
          ['service' => ['listener_service', 'preFoo']],
        ],
      ],
    ];

    $dispatcher = new ContainerAwareEventDispatcher($container, $listeners);
    $listenerService = $container->get('listener_service');
    $actualPriority = $dispatcher->getListenerPriority('test_event', [$listenerService, 'preFoo']);

    $this->assertSame(5, $actualPriority);
  }

  public function testInitialState(): void {
    $this->assertEquals([], $this->dispatcher->getListeners());
    $this->assertFalse($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertFalse($this->dispatcher->hasListeners(self::POST_FOO));
  }

  public function testAddListener(): void {
    $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
    $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo']);
    $this->assertTrue($this->dispatcher->hasListeners());
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertTrue($this->dispatcher->hasListeners(self::POST_FOO));
    $this->assertCount(1, $this->dispatcher->getListeners(self::PRE_FOO));
    $this->assertCount(1, $this->dispatcher->getListeners(self::POST_FOO));
    $this->assertCount(2, $this->dispatcher->getListeners());
  }

  public function testGetListenersSortsByPriority(): void {
    $listener1 = new TestEventListener();
    $listener2 = new TestEventListener();
    $listener3 = new TestEventListener();
    $listener1->name = '1';
    $listener2->name = '2';
    $listener3->name = '3';

    $this->dispatcher->addListener('pre.foo', [$listener1, 'preFoo'], -10);
    $this->dispatcher->addListener('pre.foo', [$listener2, 'preFoo'], 10);
    $this->dispatcher->addListener('pre.foo', [$listener3, 'preFoo']);

    $expected = [
      [$listener2, 'preFoo'],
      [$listener3, 'preFoo'],
      [$listener1, 'preFoo'],
    ];

    $this->assertSame($expected, $this->dispatcher->getListeners('pre.foo'));
  }

  public function testGetAllListenersSortsByPriority(): void {
    $listener1 = new TestEventListener();
    $listener2 = new TestEventListener();
    $listener3 = new TestEventListener();
    $listener4 = new TestEventListener();
    $listener5 = new TestEventListener();
    $listener6 = new TestEventListener();

    $this->dispatcher->addListener('pre.foo', $listener1, -10);
    $this->dispatcher->addListener('pre.foo', $listener2);
    $this->dispatcher->addListener('pre.foo', $listener3, 10);
    $this->dispatcher->addListener('post.foo', $listener4, -10);
    $this->dispatcher->addListener('post.foo', $listener5);
    $this->dispatcher->addListener('post.foo', $listener6, 10);

    $expected = [
      'pre.foo' => [$listener3, $listener2, $listener1],
      'post.foo' => [$listener6, $listener5, $listener4],
    ];

    $this->assertSame($expected, $this->dispatcher->getListeners());
  }

  public function testGetListenerPriority(): void {
    $listener1 = new TestEventListener();
    $listener2 = new TestEventListener();

    $this->dispatcher->addListener('pre.foo', $listener1, -10);
    $this->dispatcher->addListener('pre.foo', $listener2);

    $this->assertSame(-10, $this->dispatcher->getListenerPriority('pre.foo', $listener1));
    $this->assertSame(0, $this->dispatcher->getListenerPriority('pre.foo', $listener2));
    $this->assertNull($this->dispatcher->getListenerPriority('pre.bar', $listener2));
    $this->assertNull($this->dispatcher->getListenerPriority('pre.foo', function () {
    }));
  }

  public function testDispatch(): void {
    $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
    $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo']);
    $this->dispatcher->dispatch(new Event(), self::PRE_FOO);
    $this->assertTrue($this->listener->preFooInvoked);
    $this->assertFalse($this->listener->postFooInvoked);
    $this->assertInstanceOf(Event::class, $this->dispatcher->dispatch(new Event(), 'no_event'));
    $this->assertInstanceOf(Event::class, $this->dispatcher->dispatch(new Event(), self::PRE_FOO));
    // Any kind of object can be dispatched, not only instances of Event.
    $this->assertInstanceOf(\stdClass::class, $this->dispatcher->dispatch(new \stdClass(), self::PRE_FOO));
    $event = new Event();
    $return = $this->dispatcher->dispatch($event, self::PRE_FOO);
    $this->assertSame($event, $return);
  }

  public function testDispatchForClosure(): void {
    $invoked = 0;
    $listener = function () use (&$invoked) {
      ++$invoked;
    };
    $this->dispatcher->addListener('pre.foo', $listener);
    $this->dispatcher->addListener('post.foo', $listener);
    $this->dispatcher->dispatch(new Event(), self::PRE_FOO);
    $this->assertEquals(1, $invoked);
  }

  public function testStopEventPropagation(): void {
    $otherListener = new TestEventListener();

    // postFoo() stops the propagation, so only one listener should
    // be executed
    // Manually set priority to enforce $this->listener to be called first
    $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo'], 10);
    $this->dispatcher->addListener('post.foo', [$otherListener, 'postFoo']);
    $this->dispatcher->dispatch(new Event(), self::POST_FOO);
    $this->assertTrue($this->listener->postFooInvoked);
    $this->assertFalse($otherListener->postFooInvoked);
  }

  public function testDispatchByPriority(): void {
    $invoked = [];
    $listener1 = function () use (&$invoked) {
      $invoked[] = '1';
    };
    $listener2 = function () use (&$invoked) {
      $invoked[] = '2';
    };
    $listener3 = function () use (&$invoked) {
      $invoked[] = '3';
    };
    $this->dispatcher->addListener('pre.foo', $listener1, -10);
    $this->dispatcher->addListener('pre.foo', $listener2);
    $this->dispatcher->addListener('pre.foo', $listener3, 10);
    $this->dispatcher->dispatch(new Event(), self::PRE_FOO);
    $this->assertEquals(['3', '2', '1'], $invoked);
  }

  public function testRemoveListener(): void {
    $this->dispatcher->addListener('pre.bar', $this->listener);
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_BAR));
    $this->dispatcher->removeListener('pre.bar', $this->listener);
    $this->assertFalse($this->dispatcher->hasListeners(self::PRE_BAR));
    $this->dispatcher->removeListener('notExists', $this->listener);
  }

  public function testAddSubscriber(): void {
    $eventSubscriber = new TestEventSubscriber();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertTrue($this->dispatcher->hasListeners(self::POST_FOO));
  }

  public function testAddSubscriberWithPriorities(): void {
    $eventSubscriber = new TestEventSubscriber();
    $this->dispatcher->addSubscriber($eventSubscriber);

    $eventSubscriber = new TestEventSubscriberWithPriorities();
    $this->dispatcher->addSubscriber($eventSubscriber);

    $listeners = $this->dispatcher->getListeners('pre.foo');
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertCount(2, $listeners);
    $this->assertInstanceOf(TestEventSubscriberWithPriorities::class, $listeners[0][0]);
  }

  public function testAddSubscriberWithMultipleListeners(): void {
    $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
    $this->dispatcher->addSubscriber($eventSubscriber);

    $listeners = $this->dispatcher->getListeners('pre.foo');
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertCount(2, $listeners);
    $this->assertEquals('preFoo2', $listeners[0][1]);
  }

  public function testRemoveSubscriber(): void {
    $eventSubscriber = new TestEventSubscriber();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertTrue($this->dispatcher->hasListeners(self::POST_FOO));
    $this->dispatcher->removeSubscriber($eventSubscriber);
    $this->assertFalse($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertFalse($this->dispatcher->hasListeners(self::POST_FOO));
  }

  public function testRemoveSubscriberWithPriorities(): void {
    $eventSubscriber = new TestEventSubscriberWithPriorities();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->dispatcher->removeSubscriber($eventSubscriber);
    $this->assertFalse($this->dispatcher->hasListeners(self::PRE_FOO));
  }

  public function testRemoveSubscriberWithMultipleListeners(): void {
    $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTrue($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertCount(2, $this->dispatcher->getListeners(self::PRE_FOO));
    $this->dispatcher->removeSubscriber($eventSubscriber);
    $this->assertFalse($this->dispatcher->hasListeners(self::PRE_FOO));
  }

  public function testEventReceivesTheDispatcherInstanceAsArgument(): void {
    $listener = new TestWithDispatcher();
    $this->dispatcher->addListener('test', [$listener, 'foo']);
    $this->assertNull($listener->name);
    $this->assertNull($listener->dispatcher);
    $this->dispatcher->dispatch(new Event(), 'test');
    $this->assertEquals('test', $listener->name);
    $this->assertSame($this->dispatcher, $listener->dispatcher);
  }

  /**
   * @see https://bugs.php.net/bug.php?id=62976
   *
   * This bug affects:
   *  - The PHP 5.3 branch for versions < 5.3.18
   *  - The PHP 5.4 branch for versions < 5.4.8
   *  - The PHP 5.5 branch is not affected
   */
  public function testWorkaroundForPhpBug62976(): void {
    $dispatcher = $this->createEventDispatcher();
    $dispatcher->addListener('bug.62976', new CallableClass());
    $dispatcher->removeListener('bug.62976', function () {

    });
    $this->assertTrue($dispatcher->hasListeners('bug.62976'));
  }

  public function testHasListenersWhenAddedCallbackListenerIsRemoved(): void {
    $listener = function () {

    };
    $this->dispatcher->addListener('foo', $listener);
    $this->dispatcher->removeListener('foo', $listener);
    $this->assertFalse($this->dispatcher->hasListeners());
  }

  public function testGetListenersWhenAddedCallbackListenerIsRemoved(): void {
    $listener = function () {

    };
    $this->dispatcher->addListener('foo', $listener);
    $this->dispatcher->removeListener('foo', $listener);
    $this->assertSame([], $this->dispatcher->getListeners());
  }

  public function testHasListenersWithoutEventsReturnsFalseAfterHasListenersWithEventHasBeenCalled(): void {
    $this->assertFalse($this->dispatcher->hasListeners('foo'));
    $this->assertFalse($this->dispatcher->hasListeners());
  }

  public function testHasListenersIsLazy(): void {
    $called = 0;
    $listener = [
      function () use (&$called) {
        ++$called;
      },
      'onFoo',
    ];
    $this->dispatcher->addListener('foo', $listener);
    $this->assertTrue($this->dispatcher->hasListeners());
    $this->assertTrue($this->dispatcher->hasListeners('foo'));
    $this->assertSame(0, $called);
  }

  public function testDispatchLazyListener(): void {
    $called = 0;
    $factory = function () use (&$called) {
      ++$called;

      return new TestWithDispatcher();
    };
    $this->dispatcher->addListener('foo', [$factory, 'foo']);
    $this->assertSame(0, $called);
    $this->dispatcher->dispatch(new Event(), 'foo');
    $this->dispatcher->dispatch(new Event(), 'foo');
    $this->assertSame(1, $called);
  }

  public function testRemoveFindsLazyListeners(): void {
    $test = new TestWithDispatcher();
    $factory = function () use ($test) {
      return $test;
    };

    $this->dispatcher->addListener('foo', [$factory, 'foo']);
    $this->assertTrue($this->dispatcher->hasListeners('foo'));
    $this->dispatcher->removeListener('foo', [$test, 'foo']);
    $this->assertFalse($this->dispatcher->hasListeners('foo'));

    $this->dispatcher->addListener('foo', [$test, 'foo']);
    $this->assertTrue($this->dispatcher->hasListeners('foo'));
    $this->dispatcher->removeListener('foo', [$factory, 'foo']);
    $this->assertFalse($this->dispatcher->hasListeners('foo'));
  }

  public function testPriorityFindsLazyListeners(): void {
    $test = new TestWithDispatcher();
    $factory = function () use ($test) {
      return $test;
    };

    $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
    $this->assertSame(3, $this->dispatcher->getListenerPriority('foo', [$test, 'foo']));
    $this->dispatcher->removeListener('foo', [$factory, 'foo']);

    $this->dispatcher->addListener('foo', [$test, 'foo'], 5);
    $this->assertSame(5, $this->dispatcher->getListenerPriority('foo', [$factory, 'foo']));
  }

  public function testGetLazyListeners(): void {
    $test = new TestWithDispatcher();
    $factory = function () use ($test) {
      return $test;
    };

    $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
    $this->assertSame([[$test, 'foo']], $this->dispatcher->getListeners('foo'));

    $this->dispatcher->removeListener('foo', [$test, 'foo']);
    $this->dispatcher->addListener('bar', [$factory, 'foo'], 3);
    $this->assertSame(['bar' => [[$test, 'foo']]], $this->dispatcher->getListeners());
  }

}

class CallableClass {

  public function __invoke() {

  }

}

class TestEventListener {

  public $name;
  public $preFooInvoked = FALSE;
  public $postFooInvoked = FALSE;

  /**
   * Listener methods.
   */
  public function preFoo(object $e) {
    $this->preFooInvoked = TRUE;
  }

  public function postFoo(Event $e) {
    $this->postFooInvoked = TRUE;

    $e->stopPropagation();
  }

}

class TestWithDispatcher {

  public $name;
  public $dispatcher;

  public function foo(Event $e, $name, $dispatcher) {
    $this->name = $name;
    $this->dispatcher = $dispatcher;
  }

}

class TestEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return ['pre.foo' => 'preFoo', 'post.foo' => 'postFoo'];
  }

}

class TestEventSubscriberWithPriorities implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'pre.foo' => ['preFoo', 10],
      'post.foo' => ['postFoo'],
    ];
  }

}

class TestEventSubscriberWithMultipleListeners implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'pre.foo' => [
        ['preFoo1'],
        ['preFoo2', 10],
      ],
    ];
  }

}

class SymfonyInheritedEvent extends SymfonyEvent {}
