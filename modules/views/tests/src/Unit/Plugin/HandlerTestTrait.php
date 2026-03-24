<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin;

use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewsData;

/**
 * Test trait to mock dependencies of a handler.
 */
trait HandlerTestTrait {

  /**
   * The mocked view entity.
   *
   * @var \Drupal\views\Entity\View|\PHPUnit\Framework\MockObject\Stub
   */
  protected $view;

  /**
   * The mocked view executable.
   *
   * @var \Drupal\views\ViewExecutable|\PHPUnit\Framework\MockObject\Stub
   */
  protected $executable;

  /**
   * The mocked views data.
   *
   * @var \Drupal\views\ViewsData|\PHPUnit\Framework\MockObject\Stub
   */
  protected $viewsData;

  /**
   * The mocked display.
   *
   * @var \Drupal\views\Plugin\views\display\DisplayPluginBase|\PHPUnit\Framework\MockObject\Stub
   */
  protected $display;

  /**
   * Sets up a view executable and a view entity.
   */
  protected function setupExecutableAndView() {
    $this->view = $this->createStub(View::class);
    $this->executable = $this->createStub(ViewExecutable::class);
    $this->executable->storage = $this->view;
  }

  /**
   * Sets up a mocked views data object.
   */
  protected function setupViewsData() {
    $this->viewsData = $this->createStub(ViewsData::class);
  }

  /**
   * Sets up a mocked display object.
   */
  protected function setupDisplay() {
    $this->display = $this->createStub(DisplayPluginBase::class);
  }

}
