<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Unit\Menu;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\Core\Menu\LocalTaskIntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests content translation local tasks.
 */
#[Group('content_translation')]
class ContentTranslationLocalTasksTest extends LocalTaskIntegrationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->directoryList = [
      'content_translation' => 'core/modules/content_translation',
      'node' => 'core/modules/node',
    ];
    parent::setUp();

    $entity_type = $this->createStub(EntityTypeInterface::class);
    $entity_type
      ->method('getLinkTemplate')
      ->willReturnMap([
        ['canonical', 'entity.node.canonical'],
        [
          'drupal:content-translation-overview',
          'entity.node.content_translation_overview',
        ],
      ]);
    $content_translation_manager = $this->createStub(ContentTranslationManagerInterface::class);
    $content_translation_manager
      ->method('getSupportedEntityTypes')
      ->willReturn([
        'node' => $entity_type,
      ]);
    \Drupal::getContainer()->set('content_translation.manager', $content_translation_manager);
    \Drupal::getContainer()->set('string_translation', $this->getStringTranslationStub());
  }

  /**
   * Tests the block admin display local tasks.
   */
  #[DataProvider('providerTestBlockAdminDisplay')]
  public function testBlockAdminDisplay(string $route, array $expected): void {
    $this->assertLocalTasks($route, $expected);
  }

  /**
   * Provides a list of routes to test.
   */
  public static function providerTestBlockAdminDisplay(): array {
    return [
      [
        'entity.node.canonical',
        [
          [
            'content_translation.local_tasks:entity.node.content_translation_overview',
            'entity.node.canonical',
            'entity.node.edit_form',
            'entity.node.delete_form',
            'entity.node.version_history',
          ],
        ],
      ],
      [
        'entity.node.content_translation_overview',
        [
          [
            'content_translation.local_tasks:entity.node.content_translation_overview',
            'entity.node.canonical',
            'entity.node.edit_form',
            'entity.node.delete_form',
            'entity.node.version_history',
          ],
        ],
      ],
    ];
  }

}
