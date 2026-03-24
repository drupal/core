<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\field\BulkForm;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests Drupal\views\Plugin\views\field\BulkForm.
 */
#[CoversClass(BulkForm::class)]
#[Group('Views')]
class BulkFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Tests views form.
   */
  public function testViewsForm(): void {
    $row = new ResultRow();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->createStub(TranslationInterface::class));
    \Drupal::setContainer($container);

    $field = $this->getMockBuilder(BulkForm::class)
      ->onlyMethods(['getEntity'])
      ->disableOriginalConstructor()
      ->getMock();
    $field->expects($this->once())
      ->method('getEntity')
      ->willReturn(NULL);

    $query = $this->createStub(QueryPluginBase::class);
    $query
      ->method('getEntityTableInfo')
      ->willReturn([]);
    $view = $this->createStub(ViewExecutable::class);
    $view
      ->method('getQuery')
      ->willReturn($query);
    $view->result = [$row];
    $view->query = $query;
    $field->view = $view;
    $field->options = ['id' => 'bar', 'action_title' => 'zee'];
    $form = [];
    $field->viewsForm($form, $this->createStub(FormStateInterface::class));
    $this->assertNotEmpty($form);
    $this->assertIsArray($form[$field->options['id']][0]);
    $this->assertEmpty($form[$field->options['id']][0]);
  }

}
