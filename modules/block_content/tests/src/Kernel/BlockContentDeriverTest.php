<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Plugin\PluginBase;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests block content plugin deriver.
 */
#[Group('block_content')]
#[RunTestsInSeparateProcesses]
class BlockContentDeriverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'block_content', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
  }

  /**
   * Tests that only reusable blocks are derived.
   */
  public function testReusableBlocksOnlyAreDerived(): void {
    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => 'spiffy',
      'label' => 'Very spiffy',
      'description' => "Provides a block type that increases your site's spiffy rating by up to 11%",
    ]);
    $block_content_type->save();
    // And a block content entity.
    $block_content = BlockContent::create([
      'info' => 'Spiffy prototype',
      'type' => 'spiffy',
    ]);
    $block_content->save();

    // Ensure the reusable block content is provided as a derivative block
    // plugin.
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = $this->container->get('plugin.manager.block');
    $plugin_id = 'block_content' . PluginBase::DERIVATIVE_SEPARATOR . $block_content->uuid();
    $this->assertTrue($block_manager->hasDefinition($plugin_id));

    // Set the block not to be reusable.
    $block_content->setNonReusable();
    $block_content->save();

    // Ensure the non-reusable block content is not provided a derivative block
    // plugin.
    $this->assertFalse($block_manager->hasDefinition($plugin_id));
  }

  /**
   * Tests the admin labels of derivative definitions.
   */
  public function testGetDerivativeDefinitionsAdminLabels(): void {
    $blockContentType = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic Block',
    ]);
    $blockContentType->save();
    $blockContentWithLabel = BlockContent::create([
      'info' => 'Basic prototype',
      'type' => 'basic',
    ]);
    $blockContentWithLabel->save();
    $blockContentNoLabel = BlockContent::create([
      'type' => 'basic',
    ]);
    $blockContentNoLabel->save();

    $blockPluginManager = \Drupal::service('plugin.manager.block');
    $plugin = $blockPluginManager->createInstance('block_content:' . $blockContentWithLabel->uuid());
    $this->assertEquals('Basic prototype', $plugin->getPluginDefinition()['admin_label']);

    $plugin = $blockPluginManager->createInstance('block_content:' . $blockContentNoLabel->uuid());
    $this->assertEquals('Basic Block: ' . $blockContentNoLabel->id(), $plugin->getPluginDefinition()['admin_label']);
  }

}
