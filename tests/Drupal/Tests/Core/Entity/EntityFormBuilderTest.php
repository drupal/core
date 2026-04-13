<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests Drupal\Core\Entity\EntityFormBuilder.
 */
#[CoversClass(EntityFormBuilder::class)]
#[Group('Entity')]
class EntityFormBuilderTest extends UnitTestCase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface&MockObject $entityTypeManager;

  /**
   * The form builder.
   */
  protected FormBuilderInterface&MockObject $formBuilder;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->formBuilder = $this->createMock('Drupal\Core\Form\FormBuilderInterface');
    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->entityFormBuilder = new EntityFormBuilder($this->entityTypeManager, $this->formBuilder);
  }

  /**
   * Tests the getForm() method.
   */
  public function testGetForm(): void {
    $form_controller = $this->createStub(EntityFormInterface::class);
    $form_controller
      ->method('getFormId')
      ->willReturn('the_form_id');
    $this->entityTypeManager
      ->method('getFormObject')
      ->with('the_entity_type', 'default')
      ->willReturn($form_controller);

    $this->formBuilder->expects($this->once())
      ->method('buildForm')
      ->with($form_controller, $this->isInstanceOf('Drupal\Core\Form\FormStateInterface'))
      ->willReturn('the form contents');

    $entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('the_entity_type');

    $this->assertSame('the form contents', $this->entityFormBuilder->getForm($entity));
  }

}
