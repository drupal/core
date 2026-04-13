<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\TempStore;

use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\Lock;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\Core\TempStore\PrivateTempStore.
 */
#[CoversClass(PrivateTempStore::class)]
#[Group('TempStore')]
class PrivateTempStoreTest extends UnitTestCase {

  /**
   * The mocked key value expirable backend.
   */
  protected KeyValueStoreExpirableInterface&MockObject $keyValue;

  /**
   * The lock backend.
   */
  protected LockBackendInterface&Stub $lock;

  /**
   * The temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The current user.
   */
  protected AccountProxyInterface&Stub $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A tempstore object belonging to the owner.
   *
   * @var object
   */
  protected $ownObject;

  /**
   * A tempstore object not belonging to the owner.
   *
   * @var object
   */
  protected $otherObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->keyValue = $this->createMock('Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface');
    $this->lock = $this->createStub(LockBackendInterface::class);
    $this->currentUser = $this->createStub(AccountProxyInterface::class);
    $this->currentUser
      ->method('id')
      ->willReturn(1);

    $this->requestStack = new RequestStack();
    $request = Request::createFromGlobals();
    $this->requestStack->push($request);

    $this->tempStore = new PrivateTempStore($this->keyValue, $this->lock, $this->currentUser, $this->requestStack, 604800);

    $this->ownObject = (object) [
      'data' => 'test_data',
      'owner' => $this->currentUser->id(),
      'updated' => (int) $request->server->get('REQUEST_TIME'),
    ];

    // Clone the object but change the owner.
    $this->otherObject = clone $this->ownObject;
    $this->otherObject->owner = 2;
  }

  /**
   * Reinitializes the lock backend as a mock object.
   */
  protected function setUpMockLock(): void {
    $this->lock = $this->createMock(LockBackendInterface::class);
    $reflection = new \ReflectionProperty($this->tempStore, 'lockBackend');
    $reflection->setValue($this->tempStore, $this->lock);
  }

  /**
   * Tests the get() method.
   */
  public function testGet(): void {
    $calls = ['1:test_2', '1:test', '1:test'];
    $this->keyValue->expects($this->exactly(count($calls)))
      ->method('get')
      ->with($this->callback(function (string $key) use (&$calls): bool {
        return array_shift($calls) == $key;
      }))
      ->willReturnOnConsecutiveCalls(
        FALSE,
        $this->ownObject,
        $this->otherObject,
      );

    $this->assertNull($this->tempStore->get('test_2'));
    $this->assertSame($this->ownObject->data, $this->tempStore->get('test'));
    $this->assertNull($this->tempStore->get('test'));
  }

  /**
   * Tests the set() method with no lock available.
   */
  public function testSetWithNoLockAvailable(): void {
    $this->setUpMockLock();

    $this->lock->expects($this->exactly(2))
      ->method('acquire')
      ->with('1:test')
      ->willReturn(FALSE);
    $this->lock->expects($this->once())
      ->method('wait')
      ->with('1:test');

    $this->keyValue->expects($this->once())
      ->method('getCollectionName');

    $this->expectException(TempStoreException::class);
    $this->tempStore->set('test', 'value');
  }

  /**
   * Tests a successful set() call.
   */
  public function testSet(): void {
    $this->setUpMockLock();

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with('1:test')
      ->willReturn(TRUE);
    $this->lock->expects($this->never())
      ->method('wait');
    $this->lock->expects($this->once())
      ->method('release')
      ->with('1:test');

    $this->keyValue->expects($this->once())
      ->method('setWithExpire')
      ->with('1:test', $this->ownObject, 604800);

    $this->tempStore->set('test', 'test_data');
  }

  /**
   * Tests the getMetadata() method.
   */
  public function testGetMetadata(): void {
    $this->keyValue->expects($this->exactly(2))
      ->method('get')
      ->with('1:test')
      ->willReturnOnConsecutiveCalls($this->ownObject, FALSE);

    $metadata = $this->tempStore->getMetadata('test');
    $this->assertInstanceOf(Lock::class, $metadata);
    $this->assertObjectHasProperty('ownerId', $metadata);
    $this->assertObjectHasProperty('updated', $metadata);
    // Data should get removed.
    $this->assertObjectNotHasProperty('data', $metadata);

    $this->assertNull($this->tempStore->getMetadata('test'));
  }

  /**
   * Tests the locking in the delete() method.
   */
  public function testDeleteLocking(): void {
    $this->setUpMockLock();

    $this->keyValue->expects($this->once())
      ->method('get')
      ->with('1:test')
      ->willReturn($this->ownObject);
    $this->lock->expects($this->once())
      ->method('acquire')
      ->with('1:test')
      ->willReturn(TRUE);
    $this->lock->expects($this->never())
      ->method('wait');
    $this->lock->expects($this->once())
      ->method('release')
      ->with('1:test');

    $this->keyValue->expects($this->once())
      ->method('delete')
      ->with('1:test');

    $this->assertTrue($this->tempStore->delete('test'));
  }

  /**
   * Tests the delete() method with no lock available.
   */
  public function testDeleteWithNoLockAvailable(): void {
    $this->setUpMockLock();

    $this->keyValue->expects($this->once())
      ->method('get')
      ->with('1:test')
      ->willReturn($this->ownObject);
    $this->lock->expects($this->exactly(2))
      ->method('acquire')
      ->with('1:test')
      ->willReturn(FALSE);
    $this->lock->expects($this->once())
      ->method('wait')
      ->with('1:test');

    $this->keyValue->expects($this->once())
      ->method('getCollectionName');

    $this->expectException(TempStoreException::class);
    $this->tempStore->delete('test');
  }

  /**
   * Tests the delete() method.
   */
  public function testDelete(): void {
    $this->setUpMockLock();

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with('1:test_2')
      ->willReturn(TRUE);

    $calls = ['1:test_1', '1:test_2', '1:test_3'];
    $this->keyValue->expects($this->exactly(count($calls)))
      ->method('get')
      ->with($this->callback(function (string $key) use (&$calls): bool {
        return array_shift($calls) == $key;
      }))
      ->willReturnOnConsecutiveCalls(
        FALSE,
        $this->ownObject,
        $this->otherObject,
      );
    $this->keyValue->expects($this->once())
      ->method('delete')
      ->with('1:test_2');

    $this->assertTrue($this->tempStore->delete('test_1'));
    $this->assertTrue($this->tempStore->delete('test_2'));
    $this->assertFalse($this->tempStore->delete('test_3'));
  }

}
