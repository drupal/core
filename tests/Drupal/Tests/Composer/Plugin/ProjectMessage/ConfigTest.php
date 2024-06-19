<?php

declare(strict_types=1);

namespace Drupal\Tests\Composer\Plugin\ProjectMessage;

use Composer\Package\RootPackageInterface;
use Drupal\Composer\Plugin\ProjectMessage\Message;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass Drupal\Composer\Plugin\ProjectMessage\Message
 * @group ProjectMessage
 */
class ConfigTest extends TestCase {

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    vfsStream::setup('config_test', NULL, [
      'bespoke' => [
        'special_file.txt' => "Special\nFile",
      ],
    ]);
  }

  public static function provideGetMessageText() {
    return [
      [[], []],
      [
        ['Special', 'File'],
        [
          'drupal-core-project-message' => [
            'event-name-file' => vfsStream::url('config_test/bespoke/special_file.txt'),
          ],
        ],
      ],
      [
        ['I am the message.'],
        [
          'drupal-core-project-message' => [
            'event-name-message' => ['I am the message.'],
          ],
        ],
      ],
      [
        ['This message overrides file.'],
        [
          'drupal-core-project-message' => [
            'event-name-message' => ['This message overrides file.'],
            'event-name-file' => vfsStream::url('config_test/bespoke/special_file.txt'),
          ],
        ],
      ],
    ];
  }

  /**
   * @dataProvider provideGetMessageText
   * @covers ::getText
   */
  public function testGetMessageText($expected, $config): void {
    // Root package has our config.
    $root = $this->createMock(RootPackageInterface::class);
    $root->expects($this->once())
      ->method('getExtra')
      ->willReturn($config);

    $message = new Message($root, 'event-name');

    $this->assertSame($expected, $message->getText());
  }

  /**
   * @covers ::getText
   */
  public function testDefaultFile(): void {
    // Root package has no extra field.
    $root = $this->createMock(RootPackageInterface::class);
    $root->expects($this->once())
      ->method('getExtra')
      ->willReturn([]);

    // The default is to try to read from event-name-message.txt, so we expect
    // config to try that.
    $message = $this->getMockBuilder(Message::class)
      ->setConstructorArgs([$root, 'event-name'])
      ->onlyMethods(['getMessageFromFile'])
      ->getMock();
    $message->expects($this->once())
      ->method('getMessageFromFile')
      ->with('event-name-message.txt')
      ->willReturn([]);

    $this->assertSame([], $message->getText());
  }

}
