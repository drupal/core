<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional\Views;

use Drupal\Tests\block_content\Functional\BlockContentTestBase;

/**
 * Tests block_content wizard and generic entity integration.
 *
 * @group block_content
 */
class BlockContentWizardTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block_content', 'views_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer views']));
  }

  /**
   * Tests creating a 'block_content' entity view.
   */
  public function testViewAddBlockContent(): void {
    $view = [];
    $view['label'] = $this->randomMachineName(16);
    $view['id'] = $this->randomMachineName(16);
    $view['description'] = $this->randomMachineName(16);
    $view['page[create]'] = FALSE;
    $view['show[wizard_key]'] = 'block_content';
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm($view, 'Save and edit');

    $view_storage_controller = $this->container->get('entity_type.manager')->getStorage('view');
    /** @var \Drupal\views\Entity\View $view */
    $view = $view_storage_controller->load($view['id']);

    $display_options = $view->getDisplay('default')['display_options'];

    $this->assertEquals('block_content', $display_options['filters']['reusable']['entity_type']);
    $this->assertEquals('reusable', $display_options['filters']['reusable']['entity_field']);
    $this->assertEquals('boolean', $display_options['filters']['reusable']['plugin_id']);
    $this->assertEquals('1', $display_options['filters']['reusable']['value']);
  }

}
