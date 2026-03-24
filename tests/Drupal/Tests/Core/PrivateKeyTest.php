<?php

declare(strict_types=1);

namespace Drupal\Tests\Core;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\PrivateKey;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the PrivateKey class.
 */
#[Group('PrivateKeyTest')]
class PrivateKeyTest extends UnitTestCase {

  /**
   * The random key to use in tests.
   *
   * @var string
   */
  protected $key;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->key = Crypt::randomBytesBase64(55);
  }

  /**
   * Tests PrivateKey::get().
   */
  public function testGet(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with('system.private_key')
      ->willReturn($this->key);

    $privateKey = new PrivateKey($state);
    $this->assertEquals($this->key, $privateKey->get());
  }

  /**
   * Tests PrivateKey::get() with no private key from state.
   */
  public function testGetNoState(): void {
    $privateKey = new PrivateKey($this->createStub(StateInterface::class));
    $this->assertIsString($privateKey->get());
  }

  /**
   * Tests PrivateKey::setPrivateKey().
   */
  public function testSet(): void {
    $random_name = $this->randomMachineName();

    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('set')
      ->with('system.private_key', $random_name)
      ->willReturn(TRUE);

    $privateKey = new PrivateKey($state);
    $privateKey->set($random_name);
  }

}
